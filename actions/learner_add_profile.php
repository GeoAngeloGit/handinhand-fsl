<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';

function back(string $qs): void {
    header('Location: ../learner_dashboard.php?' . $qs);
    exit;
}

if (empty($_SESSION['logged_in']) || (int)($_SESSION['role_id'] ?? 0) !== 3) {
    header('Location: ../login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('');
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    back('profile_error=csrf');
}

$account_id     = (int)($_SESSION['account_id'] ?? 0);
$first_name     = trim((string)($_POST['first_name'] ?? ''));
$middle_name    = trim((string)($_POST['middle_name'] ?? ''));
$last_name      = trim((string)($_POST['last_name'] ?? ''));
$birth_date     = trim((string)($_POST['birth_date'] ?? ''));
$contact_number = trim((string)($_POST['contact_number'] ?? ''));
$gender         = trim((string)($_POST['gender'] ?? ''));
$hearing_status = trim((string)($_POST['hearing_status'] ?? ''));
$speech_status  = trim((string)($_POST['speech_status'] ?? ''));
$barangay_id    = (int)($_POST['barangay_id'] ?? 0);

if ($account_id <= 0 || $first_name === '' || $last_name === '' || $barangay_id <= 0) {
    back('profile_error=missing');
}

try {
    // Inspect learners columns dynamically so exact schema columns map cleanly
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM learners");
    while ($c = $res->fetch_assoc()) {
        $cols[] = $c['Field'];
    }

    $fields = ['account_id', 'first_name', 'last_name'];
    $types  = 'iss';
    $values = [$account_id, $first_name, $last_name];

    $optional_strings = [
        'middle_name'    => ($middle_name !== '') ? $middle_name : null,
        'birth_date'     => ($birth_date !== '') ? $birth_date : null,
        'contact_number' => ($contact_number !== '') ? $contact_number : null,
        'gender'         => ($gender !== '') ? $gender : null,
        'hearing_status' => ($hearing_status !== '') ? $hearing_status : null,
        'speech_status'  => ($speech_status !== '') ? $speech_status : null,
    ];

    foreach ($optional_strings as $col => $val) {
        if (in_array($col, $cols, true)) {
            $fields[] = $col;
            $types   .= 's';
            $values[] = $val;
        }
    }

    if (in_array('barangay_id', $cols, true)) {
        $fields[] = 'barangay_id';
        $types   .= 'i';
        $values[] = $barangay_id;
    }
    if (in_array('account_status', $cols, true)) {
        $fields[] = 'account_status';
        $types   .= 's';
        $values[] = 'active';
    }

    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $col_sql      = implode(', ', $fields);

    $stmt = $conn->prepare("INSERT INTO learners ({$col_sql}) VALUES ({$placeholders})");
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $new_learner_id = (int)$conn->insert_id;
    $stmt->close();

    // Automatically switch the active learner session to the newly added profile
    if ($new_learner_id > 0) {
        $_SESSION['learner_id']        = $new_learner_id;
        $_SESSION['active_learner_id'] = $new_learner_id;
    }

    back('profile_added=1');
} catch (Throwable $e) {
    error_log('learner_add_profile failed: ' . $e->getMessage());
    back('profile_error=server');
}