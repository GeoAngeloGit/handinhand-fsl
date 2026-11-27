<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db_connect.php';

function finish(string $message, string $type = 'error'): void
{
    $_SESSION['report_flash']      = $message;
    $_SESSION['report_flash_type'] = $type;
    header('Location: ../teacher_reports.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../teacher_reports.php');
    exit;
}

// ---------- Security: logged-in teacher only ----------
if (empty($_SESSION['logged_in']) || (int)($_SESSION['role_id'] ?? 0) !== ROLE_TEACHER) {
    finish('Please log in with a teacher account to save a report.');
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    finish('Your form expired. Refresh the page and try again.');
}

$schedule_id    = (int)($_POST['schedule_id'] ?? 0);
$is_draft       = ($_POST['report_action'] ?? '') === 'draft';
$summary_notes  = trim((string)($_POST['summary_notes'] ?? ''));
$attendance_map = (array)($_POST['attendance'] ?? []);
$grade_map      = (array)($_POST['grade'] ?? []);
$remarks_map    = (array)($_POST['remarks'] ?? []);

if (!$is_draft && $summary_notes === '') {
    finish('Session summary notes are required before submitting.');
}

$saved_file     = '';     // uploaded file, removed if saving fails
$in_transaction = false;
$finalizing     = false;

try {
    $account_id = (int)$_SESSION['account_id'];
    $stmt = $conn->prepare('SELECT teacher_id FROM teachers WHERE account_id = ?');
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $teacher_id = (int)($stmt->get_result()->fetch_assoc()['teacher_id'] ?? 0);
    $stmt->close();

    $conn->begin_transaction();
    $in_transaction = true;

    // ---------- The schedule must belong to this teacher ----------
    $stmt = $conn->prepare(
        'SELECT s.class_id, s.status
           FROM class_schedule s
           JOIN class_teacher ct ON ct.class_id = s.class_id AND ct.teacher_id = ?
          WHERE s.schedule_id = ?
          FOR UPDATE'
    );
    $stmt->bind_param('ii', $teacher_id, $schedule_id);
    $stmt->execute();
    $schedule = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$schedule) {
        throw new RuntimeException('This class schedule is not assigned to you.');
    }
    if ($schedule['status'] === 'cancelled') {
        throw new RuntimeException('You cannot report a cancelled session.');
    }

    // ---------- Start the session (the trigger creates the report) ----------
    if (in_array($schedule['status'], ['scheduled', 'rescheduled'], true)) {
        $stmt = $conn->prepare("UPDATE class_schedule SET status = 'ongoing' WHERE schedule_id = ?");
        $stmt->bind_param('i', $schedule_id);
        $stmt->execute();
        $stmt->close();
    }

    $find = $conn->prepare('SELECT report_id, status FROM post_class_report WHERE schedule_id = ? FOR UPDATE');
    $find->bind_param('i', $schedule_id);
    $find->execute();
    $report = $find->get_result()->fetch_assoc();

    if (!$report) { // fallback if the session was already ongoing without a report
        $stmt = $conn->prepare("INSERT INTO post_class_report (schedule_id, status) VALUES (?, 'Pending')");
        $stmt->bind_param('i', $schedule_id);
        $stmt->execute();
        $stmt->close();
        $find->execute();
        $report = $find->get_result()->fetch_assoc();
    }
    $find->close();

    if (!in_array($report['status'], ['Pending', 'For Revision'], true)) {
        throw new RuntimeException('This report was already submitted or verified.');
    }
    $report_id = (int)$report['report_id'];

    // ---------- Attendance and grades (stored procedures) ----------
    $stmt = $conn->prepare("SELECT learner_id FROM class_enrollments WHERE class_id = ? AND status = 'enrolled'");
    $class_id = (int)$schedule['class_id'];
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $roster = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'learner_id');
    $stmt->close();

    $attend = $conn->prepare('CALL sp_record_attendance(?, ?, ?, ?)');
    $grade  = $conn->prepare('CALL sp_grade_learner_session(?, ?, ?, ?)');
    $clear  = $conn->prepare('UPDATE session_attendance SET session_rating = NULL WHERE report_id = ? AND learner_id = ?');

    foreach ($roster as $learner_id) {
        $learner_id = (int)$learner_id;
        $status = strtolower((string)($attendance_map[$learner_id] ?? ''));
        if (!in_array($status, ['present', 'late', 'absent', 'excused'], true)) {
            throw new RuntimeException('Attendance is missing for a learner. Refresh and try again.');
        }
        $remarks = mb_substr(trim((string)($remarks_map[$learner_id] ?? '')), 0, 255);

        $attend->bind_param('iiss', $report_id, $learner_id, $status, $remarks);
        $attend->execute();

        if ($status === 'present' || $status === 'late') {
            $rating = (string)($grade_map[$learner_id] ?? '');
            if (!in_array($rating, ['Satisfactory', 'Needs Practice', 'Unsatisfactory'], true)) {
                throw new RuntimeException('A learner has an invalid or missing rating.');
            }
            $grade->bind_param('iiss', $report_id, $learner_id, $rating, $remarks);
            $grade->execute();
        } else { // absent or excused learners have no rating
            $clear->bind_param('ii', $report_id, $learner_id);
            $clear->execute();
        }
    }

    // ---------- Photo ----------
    $photo_path = '';
    $upload     = $_FILES['session_photo'] ?? null;

    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('Photo upload failed. Use an image up to 5 MB (check the PHP upload limit).');
        }
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if (!isset($types[$mime])) {
            throw new RuntimeException('Only JPG, PNG, or WEBP photos are allowed.');
        }
        $dir = __DIR__ . '/../uploads/session_photos/';
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new RuntimeException('Could not create the photo folder.');
        }
        $name = 'session_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $types[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $dir . $name)) {
            throw new RuntimeException('Could not save the photo.');
        }
        $saved_file = $dir . $name;
        $photo_path = 'uploads/session_photos/' . $name;
    } elseif (($_POST['use_sample_photo'] ?? '0') === '1') {
        if (!is_file(__DIR__ . '/../assets/img/sample_session_proof.jpg')) {
            throw new RuntimeException('The sample photo file does not exist. Use Browse to choose a photo.');
        }
        $photo_path = 'assets/img/sample_session_proof.jpg';
    }

    if ($photo_path !== '') {
        $stmt = $conn->prepare('INSERT INTO report_photos (report_id, photo_file_path) VALUES (?, ?)');
        $stmt->bind_param('is', $report_id, $photo_path);
        $stmt->execute();
        $stmt->close();
    }

    if (!$is_draft) {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM report_photos WHERE report_id = ?');
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $photos = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        if ($photos === 0) {
            throw new RuntimeException('A session photo is required before submitting.');
        }
    }

    // ---------- Save draft, or finalize ----------
    if ($is_draft) {
        $stmt = $conn->prepare(
            "UPDATE post_class_report SET summary_notes = COALESCE(NULLIF(?, ''), summary_notes) WHERE report_id = ?"
        );
        $stmt->bind_param('si', $summary_notes, $report_id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        finish('Session report saved as draft.', 'success');
    }

    // sp_finalize_class_report runs START TRANSACTION, which first commits the work above.
    $conn->commit();
    $in_transaction = false;
    $saved_file     = '';   // the photo record is now saved, so keep the file
    $finalizing     = true;

    $stmt = $conn->prepare('CALL sp_finalize_class_report(?, ?, ?)');
    $stmt->bind_param('iis', $report_id, $teacher_id, $summary_notes);
    $stmt->execute();
    $stmt->close();

    finish('Session report submitted successfully!', 'success');
} catch (Throwable $e) {
    if ($in_transaction) {
        $conn->rollback();
    }
    if ($saved_file !== '' && is_file($saved_file)) {
        unlink($saved_file);
    }
    error_log('teacher_save_report: ' . $e->getMessage());

    if ($finalizing) {
        finish('Attendance and grades were saved, but the report was not submitted. Please submit again.');
    }
    $safe = get_class($e) === 'RuntimeException';
    finish($safe ? $e->getMessage() : 'Could not save the report. Check the PHP error log.');
}