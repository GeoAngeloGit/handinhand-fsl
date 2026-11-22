<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';
require_login([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_classes.php');
    exit;
}

$class_id = ctype_digit($_POST['class_id'] ?? '') ? (int)$_POST['class_id'] : 0;
$back     = '../admin_class_sessions.php?id=' . $class_id;

function back_with_error(string $message, string $back): void
{
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $back);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    back_with_error('Your session expired. Please try again.', $back);
}

$date_in  = trim($_POST['session_date'] ?? '');
$start_in = trim($_POST['start_time'] ?? '');
$end_in   = trim($_POST['end_time'] ?? '');
$venue    = trim($_POST['venue'] ?? '');

$date  = DateTime::createFromFormat('Y-m-d', $date_in);
$start = DateTime::createFromFormat('H:i', $start_in);
$end   = DateTime::createFromFormat('H:i', $end_in);

if (!$date || $date->format('Y-m-d') !== $date_in) {
    back_with_error('Please enter a valid session date.', $back);
}
if ($date < new DateTime('today')) {
    back_with_error('A session cannot be scheduled in the past.', $back);
}
if (!$start || !$end) {
    back_with_error('Please enter valid start and end times.', $back);
}
if ($end <= $start) {
    back_with_error('End time must be later than start time.', $back);
}
if (mb_strlen($venue) > 255) {
    back_with_error('Venue is too long (max 255 characters).', $back);
}
$venue_sql = $venue !== '' ? $venue : null;
$start_sql = $start->format('H:i:s');
$end_sql   = $end->format('H:i:s');

try {
    // Class must exist and still be running
    $stmt = $conn->prepare('SELECT status FROM class WHERE class_id = ?');
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$class) {
        $_SESSION['flash_error'] = 'Class not found.';
        header('Location: ../admin_classes.php');
        exit;
    }
    if (in_array($class['status'], ['Completed', 'Cancelled'], true)) {
        back_with_error('You cannot add sessions to a ' . strtolower($class['status']) . ' class.', $back);
    }

    // Reject overlapping sessions for the same class on the same day
    $stmt = $conn->prepare(
        "SELECT 1 FROM class_schedule
          WHERE class_id = ? AND session_date = ? AND status <> 'cancelled'
            AND start_time < ? AND end_time > ? LIMIT 1"
    );
    $date_sql = $date->format('Y-m-d');
    $stmt->bind_param('isss', $class_id, $date_sql, $end_sql, $start_sql);
    $stmt->execute();
    $overlap = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if ($overlap) {
        back_with_error('This class already has a session that overlaps that date and time.', $back);
    }

    $stmt = $conn->prepare(
        'INSERT INTO class_schedule (class_id, session_date, start_time, end_time, venue)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $class_id, $date_sql, $start_sql, $end_sql, $venue_sql);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_success'] = 'Session added for ' . $date->format('D, M j, Y') . '.';
    header('Location: ' . $back);
    exit;

} catch (mysqli_sql_exception $e) {
    error_log('add_session failed: ' . $e->getMessage());
    back_with_error('Could not add the session. Please try again.', $back);
}
