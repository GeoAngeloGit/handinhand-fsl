<?php
require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../learner_classes.php');
    exit;
}

$classId   = (int)($_POST['class_id'] ?? 0);
$learnerId = (int)($_POST['learner_id'] ?? ($_SESSION['active_learner_id'] ?? 0));
$redirect  = $_POST['redirect'] ?? '../learner_dashboard.php';

if ($classId > 0 && $learnerId > 0 && $conn) {
    // Triggers trg_enrollment_after_update to decrement current_enrolled automatically
    $stmt = $conn->prepare("UPDATE class_enrollments SET status = 'dropped' WHERE class_id = ? AND learner_id = ? AND status = 'enrolled'");
    $stmt->bind_param("ii", $classId, $learnerId);
    $stmt->execute();
    $stmt->close();
}

header("Location: {$redirect}?toast=dropped");
exit;