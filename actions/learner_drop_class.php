<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../learner_classes.php');
    exit;
}

$redirect = '../learner_dashboard.php';
if (!empty($_POST['redirect']) && preg_match('/^\.\.\/[a-zA-Z0-9_\-\.]+\.php$/', $_POST['redirect'])) {
    $redirect = $_POST['redirect'];
}

$csrf = $_POST['csrf_token'] ?? '';
if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrf)) {
    header("Location: {$redirect}?error=" . urlencode('Invalid security token. Please refresh and try again.'));
    exit;
}

$classId   = (int)($_POST['class_id'] ?? 0);
$learnerId = (int)($_POST['learner_id'] ?? ($_SESSION['active_learner_id'] ?? 0));

if ($classId > 0 && $learnerId > 0 && isset($conn) && $conn) {
    try {
        $stmt = $conn->prepare(
            "UPDATE `class_enrollments` SET status = 'dropped'
             WHERE class_id = ? AND learner_id = ? AND status = 'enrolled'"
        );
        $stmt->bind_param("ii", $classId, $learnerId);
        $stmt->execute();
        $stmt->close();

        // Recount so the seat is freed even if no trigger exists
        $cnt = $conn->prepare(
            "UPDATE `class` SET current_enrolled = (
                 SELECT COUNT(*) FROM `class_enrollments`
                 WHERE class_id = ? AND status = 'enrolled'
             ) WHERE class_id = ?"
        );
        $cnt->bind_param("ii", $classId, $classId);
        $cnt->execute();
        $cnt->close();

        header("Location: {$redirect}?toast=dropped");
        exit;
    } catch (mysqli_sql_exception $e) {
        header("Location: {$redirect}?error=" . urlencode($e->getMessage()));
        exit;
    }
}

header("Location: {$redirect}");
exit;