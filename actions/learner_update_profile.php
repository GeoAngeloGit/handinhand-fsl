<?php
require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../learner_dashboard.php');
    exit;
}

$learnerId   = (int)($_POST['learner_id'] ?? 0);
$barangayId  = (int)($_POST['barangay_id'] ?? 0);
$firstName   = trim($_POST['first_name'] ?? '');
$middleName  = trim($_POST['middle_name'] ?? '') ?: null;
$lastName    = trim($_POST['last_name'] ?? '');
$birthdate   = $_POST['birthdate'] ?? '';
$gender      = $_POST['gender'] ?? 'Prefer not to say';
$contact     = trim($_POST['contact_number'] ?? '');
$hearing     = $_POST['hearing_status'] ?? 'Hearing';
$speech      = $_POST['speech_status'] ?? 'Verbal';
$street      = trim($_POST['street_address'] ?? '');
$notes       = trim($_POST['accessibility_notes'] ?? '');
$redirect    = $_POST['redirect'] ?? '../learner_dashboard.php';

if ($learnerId > 0 && $barangayId > 0 && $firstName !== '' && $lastName !== '' && $conn) {
    $stmt = $conn->prepare("
        UPDATE learners
        SET barangay_id = ?, first_name = ?, middle_name = ?, last_name = ?,
            birthdate = ?, gender = ?, contact_number = ?,
            hearing_status = ?, speech_status = ?, street_address = ?, accessibility_notes = ?
        WHERE learner_id = ?
    ");
    $stmt->bind_param(
        "issssssssssi",
        $barangayId, $firstName, $middleName, $lastName,
        $birthdate, $gender, $contact,
        $hearing, $speech, $street, $notes, $learnerId
    );
    $stmt->execute();
    $stmt->close();
}

header("Location: {$redirect}?toast=profile_updated");
exit;