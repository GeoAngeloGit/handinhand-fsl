<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';
header('Content-Type: application/json');

function out(bool $ok, string $msg, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['logged_in']) || ($_SESSION['role_id'] ?? 0) !== ROLE_ADMIN) {
    out(false, 'Not authorized.', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Invalid request.', 405);
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_verify($in['csrf_token'] ?? null)) {
    out(false, 'Session expired. Reload the page.', 403);
}

$action   = (string)($in['action'] ?? '');
$class_id = (int)($in['class_id'] ?? 0);
if ($class_id <= 0) {
    out(false, 'Invalid class ID.', 400);
}

try {
    switch ($action) {
        case 'update_status':
            $status = (string)($in['status'] ?? 'Open');
            if (!in_array($status, ['Open', 'Ongoing', 'Completed', 'Cancelled'], true)) {
                out(false, 'Invalid status.', 400);
            }
            $stmt = $conn->prepare("UPDATE classes SET status = ? WHERE class_id = ?");
            $stmt->bind_param('si', $status, $class_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Class status updated.');

        case 'delete_class':
            $conn->begin_transaction();
            $conn->query("DELETE FROM class_schedules WHERE class_id = {$class_id}");
            $conn->query("DELETE FROM class_teacher WHERE class_id = {$class_id}");
            $conn->query("DELETE FROM class_enrollments WHERE class_id = {$class_id}");
            $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
            $stmt->bind_param('i', $class_id);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            out(true, 'Class deleted.');

        case 'add_schedule':
            $date  = trim((string)($in['session_date'] ?? ''));
            $start = trim((string)($in['start_time'] ?? ''));
            $end   = trim((string)($in['end_time'] ?? '')) ?: null;
            $room  = trim((string)($in['room_location'] ?? ''));
            if ($date === '' || $start === '') {
                out(false, 'Date and start time are required.', 400);
            }
            $stmt = $conn->prepare("
                INSERT INTO class_schedules (class_id, session_date, start_time, end_time, room_location)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('issss', $class_id, $date, $start, $end, $room);
            $stmt->execute();
            $stmt->close();
            out(true, 'Session added.');

        case 'remove_schedule':
            $sched_id = (int)($in['schedule_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM class_schedules WHERE schedule_id = ? AND class_id = ?");
            $stmt->bind_param('ii', $sched_id, $class_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Session removed.');

            case 'assign_teacher':
            $teacher_id = (int)($in['teacher_id'] ?? 0);
            $role = in_array(($in['role'] ?? ''), ['Lead Instructor', 'Co-teacher', 'Substitute'], true)
                ? $in['role']
                : 'Co-teacher';
            $stmt = $conn->prepare("
                INSERT INTO class_teacher (class_id, teacher_id, role)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE role = VALUES(role)
            ");
            $stmt->bind_param('iis', $class_id, $teacher_id, $role);
            $stmt->execute();
            $stmt->close();
            out(true, 'Educator assigned.');
            $stmt->bind_param('iis', $class_id, $teacher_id, $role);
            $stmt->execute();
            $stmt->close();
            out(true, 'Educator assigned.');

        case 'unassign_teacher':
            $teacher_id = (int)($in['teacher_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM class_teacher WHERE class_id = ? AND teacher_id = ?");
            $stmt->bind_param('ii', $class_id, $teacher_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Educator removed.');

        case 'enroll_learner':
            $learner_id = (int)($in['learner_id'] ?? 0);
            // Enforce capacity check
            $cap_res = $conn->query("
                SELECT c.max_capacity,
                       (SELECT COUNT(*) FROM class_enrollments ce WHERE ce.class_id = c.class_id) AS enrolled
                FROM classes c WHERE c.class_id = {$class_id}
            ")->fetch_assoc();
            if ($cap_res && (int)$cap_res['enrolled'] >= (int)$cap_res['max_capacity']) {
                out(false, 'Class has reached maximum capacity.', 409);
            }
            $status = 'enrolled';
            $stmt = $conn->prepare("
                INSERT INTO class_enrollments (class_id, learner_id, status)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param('iis', $class_id, $learner_id, $status);
            $stmt->execute();
            $stmt->close();
            out(true, 'Learner enrolled.');

        case 'remove_learner':
            $learner_id = (int)($in['learner_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM class_enrollments WHERE class_id = ? AND learner_id = ?");
            $stmt->bind_param('ii', $class_id, $learner_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Learner removed from class.');

        default:
            out(false, 'Unknown action.', 400);
    }
} catch (mysqli_sql_exception $e) {
    if ($conn->in_transaction) $conn->rollback();
    if ($e->getCode() === 1062) {
        out(false, 'That person is already assigned or enrolled in this class.', 409);
    }
    error_log('admin_manage_class failed: ' . $e->getMessage());
    out(false, 'Database operation failed.', 500);
}