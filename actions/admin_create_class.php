<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';

function back(string $qs): void {
    header('Location: ../admin_classes.php?' . $qs);
    exit;
}

if (empty($_SESSION['logged_in']) || ($_SESSION['role_id'] ?? 0) !== ROLE_ADMIN) {
    header('Location: ../login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('');
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    back('class_error=csrf');
}

$title       = trim($_POST['class_title'] ?? '');
$description = trim($_POST['description'] ?? '');
$capacity    = max(1, (int)($_POST['max_capacity'] ?? 20));
$status      = trim($_POST['status'] ?? 'Open');

$teacher_ids   = is_array($_POST['teacher_ids'] ?? null) ? $_POST['teacher_ids'] : [];
$teacher_roles = is_array($_POST['teacher_roles'] ?? null) ? $_POST['teacher_roles'] : [];

$venue       = trim($_POST['venue'] ?? ($_POST['room_location'] ?? ''));
$sched_date  = trim($_POST['session_date'] ?? '');
$start_time  = trim($_POST['start_time'] ?? '');
$end_time    = trim($_POST['end_time'] ?? '');

if (!in_array($status, ['Draft', 'Open', 'Ongoing', 'Completed', 'Cancelled'], true)) {
    $status = 'Open';
}

// Parse & validate teachers array
$assignments = [];
$seen_ids    = [];
$lead_count  = 0;

foreach ($teacher_ids as $idx => $raw_tid) {
    $tid = (int)$raw_tid;
    if ($tid <= 0) {
        continue;
    }
    if (isset($seen_ids[$tid])) {
        back('class_error=duplicate');
    }
    $seen_ids[$tid] = true;

    $raw_role = trim((string)($teacher_roles[$idx] ?? 'Co-Teacher'));
    if (strcasecmp($raw_role, 'Co-teacher') === 0) {
        $raw_role = 'Co-Teacher';
    }
    if (!in_array($raw_role, ['Lead Instructor', 'Co-Teacher', 'Substitute'], true)) {
        $raw_role = 'Co-Teacher';
    }
    if ($raw_role === 'Lead Instructor') {
        $lead_count++;
    }
    $assignments[] = ['teacher_id' => $tid, 'role' => $raw_role];
}

if ($title === '' || empty($assignments) || $sched_date === '' || $start_time === '' || $end_time === '' || $venue === '') {
    back('class_error=missing');
}
if ($lead_count !== 1) {
    back('class_error=lead_count');
}

// Resolve admin_id for created_by FK
$created_by = 0;
$acc_id = (int)($_SESSION['account_id'] ?? 0);
if ($acc_id > 0) {
    $adm_stmt = $conn->prepare("SELECT admin_id FROM administrators WHERE account_id = ? LIMIT 1");
    $adm_stmt->bind_param('i', $acc_id);
    $adm_stmt->execute();
    if ($adm_row = $adm_stmt->get_result()->fetch_assoc()) {
        $created_by = (int)$adm_row['admin_id'];
    }
    $adm_stmt->close();
}
if ($created_by <= 0) {
    $res = $conn->query("SELECT admin_id FROM administrators ORDER BY admin_id ASC LIMIT 1");
    if ($res && ($adm_row = $res->fetch_assoc())) {
        $created_by = (int)$adm_row['admin_id'];
    }
}
if ($created_by <= 0) {
    back('class_error=server');
}

$teachers_json = json_encode($assignments, JSON_UNESCAPED_UNICODE);

try {
    $stmt = $conn->prepare("CALL sp_create_class(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        'ississssss',
        $created_by,
        $title,
        $description,
        $capacity,
        $teachers_json,
        $status,
        $sched_date,
        $start_time,
        $end_time,
        $venue
    );
    $stmt->execute();
    $stmt->close();
    while ($conn->more_results() && $conn->next_result()) {;}

    back('class_created=1');
} catch (Throwable $e) {
    error_log('admin_create_class failed: ' . $e->getMessage());
    back('class_error=server');
}
