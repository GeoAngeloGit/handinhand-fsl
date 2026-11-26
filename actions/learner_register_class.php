<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../learner_browse.php');
    exit;
}

$redirect = '../learner_classes.php';
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
        $stmt = $conn->prepare("CALL sp_enroll_learner(?, ?)");
        $stmt->bind_param("ii", $classId, $learnerId);
        $stmt->execute();
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) {;}
        header("Location: {$redirect}?toast=enrolled");
        exit;
    } catch (mysqli_sql_exception $e) {
        header("Location: {$redirect}?error=" . urlencode($e->getMessage()));
        exit;
    }
}

header("Location: {$redirect}");
exit;