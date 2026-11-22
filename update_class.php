<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/class_helpers.php';
require_login([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_classes.php');
    exit;
}

$class_id = ctype_digit($_POST['class_id'] ?? '') ? (int)$_POST['class_id'] : 0;
$back     = '../admin_class_edit.php?id=' . $class_id;

$old = [
    'class_id'     => (string)$class_id,
    'class_title'  => trim($_POST['class_title']  ?? ''),
    'description'  => trim($_POST['description']  ?? ''),
    'max_capacity' => trim($_POST['max_capacity'] ?? ''),
    'status'       => trim($_POST['status']       ?? ''),
    'teachers'     => collect_teacher_rows($_POST),
];

function fail_back(string $message, array $old, string $back): void
{
    $_SESSION['flash_error'] = $message;
    $_SESSION['flash_old']   = $old;
    header('Location: ' . $back);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    fail_back('Your session expired. Please try again.', $old, $back);
}

// ---------- Validate ----------
if ($old['class_title'] === '' || mb_strlen($old['class_title']) > 200) {
    fail_back('Class title is required (max 200 characters).', $old, $back);
}
if (!ctype_digit($old['max_capacity']) || (int)$old['max_capacity'] < 1 || (int)$old['max_capacity'] > 500) {
    fail_back('Max capacity must be a whole number between 1 and 500.', $old, $back);
}
if (!in_array($old['status'], CLASS_STATUSES, true)) {
    fail_back('Please choose a valid status.', $old, $back);
}

$capacity    = (int)$old['max_capacity'];
$description = $old['description'] !== '' ? $old['description'] : null;

try {
    // Class must exist; capacity cannot drop below current enrolment
    $stmt = $conn->prepare(
        "SELECT c.current_enrolled,
                (SELECT COUNT(*) FROM class_enrollments e
                  WHERE e.class_id = c.class_id AND e.status = 'enrolled') AS enrolled_count
           FROM class c WHERE c.class_id = ?"
    );
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $_SESSION['flash_error'] = 'Class not found.';
        header('Location: ../admin_classes.php');
        exit;
    }
    $min_capacity = max((int)$row['current_enrolled'], (int)$row['enrolled_count']);
    if ($capacity < $min_capacity) {
        fail_back("Capacity cannot be lower than the {$min_capacity} learner(s) already enrolled.", $old, $back);
    }

    [$assignments, $teacher_error] = parse_teacher_assignments($conn, $_POST, $class_id);
    if ($teacher_error !== null) {
        fail_back($teacher_error, $old, $back);
    }

    // ---------- Save (all-or-nothing) ----------
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'UPDATE class SET class_title = ?, description = ?, max_capacity = ?, status = ? WHERE class_id = ?'
    );
    $stmt->bind_param('ssisi', $old['class_title'], $description, $capacity, $old['status'], $class_id);
    $stmt->execute();
    $stmt->close();

    // Replace the whole teacher list with what the form submitted
    save_class_teachers($conn, $class_id, $assignments);

    $conn->commit();

    $_SESSION['flash_success'] = 'Class updated.';
    header('Location: ../admin_classes.php');
    exit;

} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    error_log('update_class failed: ' . $e->getMessage());
    fail_back('Could not save changes. Please try again.', $old, $back);
}
