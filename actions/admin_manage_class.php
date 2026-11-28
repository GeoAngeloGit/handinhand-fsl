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
            if (!in_array($status, ['Draft', 'Open', 'Ongoing', 'Completed', 'Cancelled'], true)) {
                out(false, 'Invalid status.', 400);
            }
            $stmt = $conn->prepare("UPDATE class SET status = ? WHERE class_id = ?");
            $stmt->bind_param('si', $status, $class_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Class status updated.');

        case 'edit_class':
            $title       = trim((string)($in['class_title'] ?? ''));
            $description = trim((string)($in['description'] ?? ''));
            $capacity    = (int)($in['max_capacity'] ?? 0);
            $status      = trim((string)($in['status'] ?? 'Open'));
            $teachers_in = is_array($in['teachers'] ?? null) ? $in['teachers'] : [];

            if ($title === '' || $capacity < 1) {
                out(false, 'Class title and a valid max capacity (>= 1) are required.', 400);
            }
            if (!in_array($status, ['Draft', 'Open', 'Ongoing', 'Completed', 'Cancelled'], true)) {
                out(false, 'Invalid class status.', 400);
            }

            $assignments = [];
            $seen_ids    = [];
            $lead_count  = 0;
            foreach ($teachers_in as $t_item) {
                $tid = (int)($t_item['teacher_id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                if (isset($seen_ids[$tid])) {
                    out(false, 'Each assigned teacher must be unique.', 400);
                }
                $seen_ids[$tid] = true;

                $raw_role = trim((string)($t_item['role'] ?? 'Co-Teacher'));
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

            if (empty($assignments) || $lead_count !== 1) {
                out(false, 'Please assign at least one teacher and exactly one Lead Instructor.', 400);
            }

            $conn->begin_transaction();
            $chk = $conn->prepare("SELECT current_enrolled FROM class WHERE class_id = ? FOR UPDATE");
            $chk->bind_param('i', $class_id);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            $chk->close();

            if (!$row) {
                $conn->rollback();
                out(false, 'Class not found.', 404);
            }
            if ($capacity < (int)$row['current_enrolled']) {
                $conn->rollback();
                out(false, 'Max capacity cannot be less than currently enrolled learners (' . (int)$row['current_enrolled'] . ').', 409);
            }

            $stmt = $conn->prepare("UPDATE class SET class_title = ?, description = ?, max_capacity = ?, status = ? WHERE class_id = ?");
            $stmt->bind_param('ssisi', $title, $description, $capacity, $status, $class_id);
            $stmt->execute();
            $stmt->close();

            $del_t = $conn->prepare("DELETE FROM class_teacher WHERE class_id = ?");
            $del_t->bind_param('i', $class_id);
            $del_t->execute();
            $del_t->close();

            $ins_t = $conn->prepare("INSERT INTO class_teacher (class_id, teacher_id, assigned_role) VALUES (?, ?, ?)");
            foreach ($assignments as $a) {
                $ins_t->bind_param('iis', $class_id, $a['teacher_id'], $a['role']);
                $ins_t->execute();
            }
            $ins_t->close();

            $conn->commit();
            out(true, 'Class details updated.');

        case 'delete_class':
            $conn->begin_transaction();
            $stmt1 = $conn->prepare("DELETE FROM class_schedule WHERE class_id = ?");
            $stmt1->bind_param('i', $class_id);
            $stmt1->execute();
            $stmt1->close();

            $stmt2 = $conn->prepare("DELETE FROM class_teacher WHERE class_id = ?");
            $stmt2->bind_param('i', $class_id);
            $stmt2->execute();
            $stmt2->close();

            $stmt3 = $conn->prepare("DELETE FROM class_enrollments WHERE class_id = ?");
            $stmt3->bind_param('i', $class_id);
            $stmt3->execute();
            $stmt3->close();

            $stmt4 = $conn->prepare("DELETE FROM class WHERE class_id = ?");
            $stmt4->bind_param('i', $class_id);
            $stmt4->execute();
            $stmt4->close();

            $conn->commit();
            out(true, 'Class deleted.');

        case 'add_schedule':
            $date  = trim((string)($in['session_date'] ?? ''));
            $start = trim((string)($in['start_time'] ?? ''));
            $end   = trim((string)($in['end_time'] ?? ''));
            $venue = trim((string)($in['venue'] ?? ($in['room_location'] ?? '')));
            if ($date === '' || $start === '' || $end === '') {
                out(false, 'Date, start time, and end time are required.', 400);
            }
            if ($end <= $start) {
                out(false, 'End time must be after start time.', 400);
            }
            $stmt = $conn->prepare("
                INSERT INTO class_schedule (class_id, session_date, start_time, end_time, venue, status)
                VALUES (?, ?, ?, ?, ?, 'scheduled')
            ");
            $stmt->bind_param('issss', $class_id, $date, $start, $end, $venue);
            $stmt->execute();
            $stmt->close();
            out(true, 'Session added.');

        case 'edit_schedule':
            $sched_id = (int)($in['schedule_id'] ?? 0);
            $date     = trim((string)($in['session_date'] ?? ''));
            $start    = trim((string)($in['start_time'] ?? ''));
            $end      = trim((string)($in['end_time'] ?? ''));
            $venue    = trim((string)($in['venue'] ?? ($in['room_location'] ?? '')));
            if ($sched_id <= 0 || $date === '' || $start === '' || $end === '') {
                out(false, 'Schedule ID, date, start time, and end time are required.', 400);
            }
            if ($end <= $start) {
                out(false, 'End time must be after start time.', 400);
            }
            $stmt = $conn->prepare("
                UPDATE class_schedule
                SET session_date = ?, start_time = ?, end_time = ?, venue = ?
                WHERE schedule_id = ? AND class_id = ?
            ");
            $stmt->bind_param('ssssii', $date, $start, $end, $venue, $sched_id, $class_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Session schedule updated.');

        case 'update_schedule_status':
            $sched_id   = (int)($in['schedule_id'] ?? 0);
            $sched_stat = strtolower(trim((string)($in['status'] ?? '')));
            $remarks    = trim((string)($in['remarks'] ?? ''));
            if ($sched_id <= 0 || !in_array($sched_stat, ['scheduled', 'ongoing', 'completed', 'rescheduled', 'cancelled'], true)) {
                out(false, 'Invalid schedule status parameters.', 400);
            }
            $stmt = $conn->prepare("
                UPDATE class_schedule
                SET status = ?, remarks = COALESCE(NULLIF(?, ''), remarks)
                WHERE schedule_id = ? AND class_id = ?
            ");
            $stmt->bind_param('ssii', $sched_stat, $remarks, $sched_id, $class_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Session status updated.');

        case 'remove_schedule':
            $sched_id = (int)($in['schedule_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM class_schedule WHERE schedule_id = ? AND class_id = ?");
            $stmt->bind_param('ii', $sched_id, $class_id);
            $stmt->execute();
            $stmt->close();
            out(true, 'Session removed.');

        case 'assign_teacher':
            $teacher_id = (int)($in['teacher_id'] ?? 0);
            $raw_role = trim((string)($in['role'] ?? 'Co-Teacher'));
            if (strcasecmp($raw_role, 'Co-teacher') === 0) {
                $raw_role = 'Co-Teacher';
            }
            $role = in_array($raw_role, ['Lead Instructor', 'Co-Teacher', 'Substitute'], true)
                ? $raw_role
                : 'Co-Teacher';
            $stmt = $conn->prepare("
                INSERT INTO class_teacher (class_id, teacher_id, assigned_role)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE assigned_role = VALUES(assigned_role)
            ");
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
            if ($learner_id <= 0) {
                out(false, 'Invalid learner ID.', 400);
            }
            $stmt = $conn->prepare("CALL sp_enroll_learner(?, ?)");
            $stmt->bind_param('ii', $class_id, $learner_id);
            $stmt->execute();
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) {;}
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
    if ($e->getSqlState() === '45000') {
        out(false, $e->getMessage(), 409);
    }
    error_log('admin_manage_class failed: ' . $e->getMessage());
    out(false, 'Database operation failed.', 500);
}
