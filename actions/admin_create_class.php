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
$barangay_id = (int)($_POST['barangay_id'] ?? 0);
$skill_level = trim($_POST['skill_level'] ?? 'Beginner');
$capacity    = max(1, (int)($_POST['max_capacity'] ?? 20));
$status      = trim($_POST['status'] ?? 'Open');

$teacher_ids   = is_array($_POST['teacher_ids'] ?? null) ? $_POST['teacher_ids'] : [];
$teacher_roles = is_array($_POST['teacher_roles'] ?? null) ? $_POST['teacher_roles'] : [];

$room        = trim($_POST['room_location'] ?? '');
$sched_date  = trim($_POST['session_date'] ?? '');
$start_time  = trim($_POST['start_time'] ?? '');
$end_time    = trim($_POST['end_time'] ?? '');

if (!in_array($status, ['Open', 'Ongoing', 'Completed'], true)) {
    $status = 'Open';
}
if (!in_array($skill_level, ['Beginner', 'Intermediate', 'Advanced'], true)) {
    $skill_level = 'Beginner';
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

    $role = trim((string)($teacher_roles[$idx] ?? 'Co-teacher'));
    if (!in_array($role, ['Lead Instructor', 'Co-teacher', 'Substitute'], true)) {
        $role = 'Co-teacher';
    }
    if ($role === 'Lead Instructor') {
        $lead_count++;
    }
    $assignments[] = ['teacher_id' => $tid, 'role' => $role];
}

if ($title === '' || $barangay_id <= 0 || empty($assignments) || $sched_date === '' || $start_time === '' || $room === '') {
    back('class_error=missing');
}
if ($lead_count !== 1) {
    back('class_error=lead_count');
}

$conn->begin_transaction();
try {
    // 1. Insert Class
    $stmt = $conn->prepare("
        INSERT INTO classes (barangay_id, class_title, description, skill_level, max_capacity, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isssis', $barangay_id, $title, $description, $skill_level, $capacity, $status);
    $stmt->execute();
    $class_id = (int)$conn->insert_id;
    $stmt->close();

    // 2. Insert Assigned Teachers (Lead Instructor, Co-teachers, Substitutes)
    $t_stmt = $conn->prepare("INSERT INTO class_teacher (class_id, teacher_id, role) VALUES (?, ?, ?)");
    foreach ($assignments as $a) {
        $t_stmt->bind_param('iis', $class_id, $a['teacher_id'], $a['role']);
        $t_stmt->execute();
    }
    $t_stmt->close();

    // 3. Insert First Session Schedule
    $end_db = ($end_time === '') ? null : $end_time;
    $s_stmt = $conn->prepare("
        INSERT INTO class_schedules (class_id, session_date, start_time, end_time, room_location)
        VALUES (?, ?, ?, ?, ?)
    ");
    $s_stmt->bind_param('issss', $class_id, $sched_date, $start_time, $end_db, $room);
    $s_stmt->execute();
    $s_stmt->close();

    $conn->commit();
    back('class_created=1');
} catch (Throwable $e) {
    $conn->rollback();
    error_log('admin_create_class failed: ' . $e->getMessage());
    back('class_error=server');
}