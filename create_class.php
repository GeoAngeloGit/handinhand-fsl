<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/class_helpers.php';

// Admin-only, POST-only endpoint
require_login([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_classes.php');
    exit;
}

/** Remember the input + an error, then go back to the form. */
function fail_back(string $message, array $old): void
{
    $_SESSION['flash_error'] = $message;
    $_SESSION['flash_old']   = $old;
    header('Location: ../admin_class_new.php');
    exit;
}

// ---------- Collect input ----------
$old = [
    'class_title'  => trim($_POST['class_title']  ?? ''),
    'description'  => trim($_POST['description']  ?? ''),
    'max_capacity' => trim($_POST['max_capacity'] ?? ''),
    'status'       => trim($_POST['status']       ?? 'Open'),
    'teachers'     => collect_teacher_rows($_POST),
    'venue'        => trim($_POST['venue']        ?? ''),
    'session_date' => trim($_POST['session_date'] ?? ''),
    'start_time'   => trim($_POST['start_time']   ?? ''),
    'end_time'     => trim($_POST['end_time']     ?? ''),
];

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    fail_back('Your session expired. Please try again.', $old);
}

// ---------- Validate ----------
if ($old['class_title'] === '' || mb_strlen($old['class_title']) > 200) {
    fail_back('Class title is required (max 200 characters).', $old);
}
if (!ctype_digit($old['max_capacity']) || (int)$old['max_capacity'] < 1 || (int)$old['max_capacity'] > 500) {
    fail_back('Max capacity must be a whole number between 1 and 500.', $old);
}
if (!in_array($old['status'], ['Draft', 'Open'], true)) {
    fail_back('New classes can only start as Draft or Open.', $old);
}
if (mb_strlen($old['venue']) > 255) {
    fail_back('Venue is too long (max 255 characters).', $old);
}

$date = DateTime::createFromFormat('Y-m-d', $old['session_date']);
if (!$date || $date->format('Y-m-d') !== $old['session_date']) {
    fail_back('Please enter a valid first session date.', $old);
}
if ($date < new DateTime('today')) {
    fail_back('The first session cannot be in the past.', $old);
}

$start = DateTime::createFromFormat('H:i', $old['start_time']);
$end   = DateTime::createFromFormat('H:i', $old['end_time']);
if (!$start || !$end) {
    fail_back('Please enter valid start and end times.', $old);
}
if ($end <= $start) {
    fail_back('End time must be later than start time.', $old);
}

$capacity     = (int)$old['max_capacity'];
$description  = $old['description'] !== '' ? $old['description'] : null;
$venue        = $old['venue'] !== '' ? $old['venue'] : null;
$start_sql    = $start->format('H:i:s');
$end_sql      = $end->format('H:i:s');

try {
    // class.created_by references administrators.admin_id (not account_id)
    $stmt = $conn->prepare('SELECT admin_id FROM administrators WHERE account_id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['account_id']);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$admin) {
        fail_back('Your account has no administrator profile, so you cannot create classes.', $old);
    }
    $admin_id = (int)$admin['admin_id'];

    // Teachers (each with a role); blank rows are ignored
    [$assignments, $teacher_error] = parse_teacher_assignments($conn, $_POST, 0);
    if ($teacher_error !== null) {
        fail_back($teacher_error, $old);
    }

    // ---------- Insert (all-or-nothing) ----------
    $conn->begin_transaction();

    // 1) class
    $stmt = $conn->prepare(
        'INSERT INTO class (created_by, class_title, description, max_capacity, status)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issis', $admin_id, $old['class_title'], $description, $capacity, $old['status']);
    $stmt->execute();
    $class_id = (int)$conn->insert_id;
    $stmt->close();

    // 2) class_schedule: the FIRST session only. More sessions are added one by one
    //    from admin_class_sessions.php.
    $stmt = $conn->prepare(
        'INSERT INTO class_schedule (class_id, session_date, start_time, end_time, venue)
         VALUES (?, ?, ?, ?, ?)'
    );
    $session_sql = $date->format('Y-m-d');
    $stmt->bind_param('issss', $class_id, $session_sql, $start_sql, $end_sql, $venue);
    $stmt->execute();
    $stmt->close();

    // 3) class_teacher (any number of teachers, each with a role)
    save_class_teachers($conn, $class_id, $assignments);

    $conn->commit();

    unset($_SESSION['flash_old']);
    $_SESSION['flash_success'] = 'Class "' . $old['class_title'] . '" created with its first session. You can add more sessions from the Sessions page.';
    header('Location: ../admin_classes.php');
    exit;

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('create_class failed: ' . $e->getMessage());
    fail_back('Could not create the class. Please try again.', $old);
}
