<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../teacher_reports.php');
    exit;
}

$teacher_id       = (int)($_POST['teacher_id'] ?? 1);
$schedule_id      = (int)($_POST['schedule_id'] ?? 0);
$action           = strtolower(trim((string)($_POST['report_action'] ?? 'submitted')));
$status           = ($action === 'draft') ? 'Draft' : 'Submitted';
$summary_notes    = trim((string)($_POST['summary_notes'] ?? ''));
$use_sample_photo = (string)($_POST['use_sample_photo'] ?? '0') === '1';

$attendance_map = $_POST['attendance'] ?? [];
$grade_map      = $_POST['grade'] ?? [];
$remarks_map    = $_POST['remarks'] ?? [];

$photo_path = '';
if (!empty($_FILES['session_photo']['name']) && $_FILES['session_photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/session_photos/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0775, true);
    }
    $ext       = pathinfo((string)$_FILES['session_photo']['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $file_name = 'session_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (move_uploaded_file($_FILES['session_photo']['tmp_name'], $upload_dir . $file_name)) {
        $photo_path = 'uploads/session_photos/' . $file_name;
    }
} elseif ($use_sample_photo) {
    $photo_path = 'assets/img/sample_session_proof.jpg';
}

if (isset($conn) && $conn instanceof mysqli) {
    try {
        $total_students = count($attendance_map);
        $present_count  = 0;
        foreach ($attendance_map as $st) {
            if (strtolower((string)$st) === 'present') {
                $present_count++;
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO session_reports 
                (teacher_id, schedule_id, summary_notes, photo_proof, status, present_count, total_students, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            'iisssii',
            $teacher_id,
            $schedule_id,
            $summary_notes,
            $photo_path,
            $status,
            $present_count,
            $total_students
        );
        $stmt->execute();
        $report_id = (int)$stmt->insert_id;
        $stmt->close();

        // Save per-student attendance & observation rating rows
        if ($report_id > 0 && !empty($attendance_map)) {
            $att_stmt = $conn->prepare("
                INSERT INTO session_attendance 
                    (report_id, learner_id, attendance_status, rating, remarks)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($attendance_map as $lid_raw => $att_val) {
                $lid   = (int)$lid_raw;
                $att   = (string)$att_val;
                $grade = (string)($grade_map[$lid_raw] ?? 'Satisfactory');
                $rem   = trim((string)($remarks_map[$lid_raw] ?? ''));
                $att_stmt->bind_param('iisss', $report_id, $lid, $att, $grade, $rem);
                $att_stmt->execute();
            }
            $att_stmt->close();
        }

        $_SESSION['report_flash']      = ($status === 'Draft')
            ? 'Session report saved as Draft.'
            : 'Session report submitted successfully!';
        $_SESSION['report_flash_type'] = 'success';
    } catch (Throwable $e) {
        $_SESSION['report_flash']      = 'Could not save report to database: ' . $e->getMessage();
        $_SESSION['report_flash_type'] = 'error';
    }
}

header('Location: ../teacher_reports.php');
exit;