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

$account_id          = (int)($_SESSION['account_id'] ?? 0);
$first_name          = trim((string)($_POST['first_name'] ?? ''));
$middle_name         = trim((string)($_POST['middle_name'] ?? ''));
$last_name           = trim((string)($_POST['last_name'] ?? ''));
$birth_date          = trim((string)($_POST['birth_date'] ?? ($_POST['birthdate'] ?? '')));
$contact_number      = trim((string)($_POST['contact_number'] ?? ''));
$gender              = trim((string)($_POST['gender'] ?? ''));
$hearing_status      = trim((string)($_POST['hearing_status'] ?? ''));
$speech_status       = trim((string)($_POST['speech_status'] ?? ''));
$municipality_id     = (int)($_POST['municipality_id'] ?? 0);
$barangay_id         = (int)($_POST['barangay_id'] ?? 0);
$street_address      = trim((string)($_POST['street_address'] ?? ''));
$accessibility_notes = trim((string)($_POST['accessibility_notes'] ?? ''));

$valid_gender  = ['Male', 'Female', 'Prefer not to say'];
$valid_hearing = ['Hearing', 'Mild to Moderate', 'Severe', 'Profoundly Deaf'];
$valid_speech  = ['Verbal', 'Hard of Speech', 'Non-Verbal / Mute'];

if (
    $account_id <= 0 ||
    $first_name === '' ||
    $last_name === '' ||
    $birth_date === '' ||
    $contact_number === '' ||
    $barangay_id <= 0 ||
    !in_array($gender, $valid_gender, true) ||
    !in_array($hearing_status, $valid_hearing, true) ||
    !in_array($speech_status, $valid_speech, true)
) {
    back('profile_error=missing');
}

$d = DateTime::createFromFormat('Y-m-d', $birth_date);
if (!$d || $d->format('Y-m-d') !== $birth_date || $d > new DateTime('today')) {
    back('profile_error=birthdate');
}

try {
    // Verify barangay exists (and belongs to municipality if provided)
    if ($municipality_id > 0) {
        $chk = $conn->prepare('SELECT 1 FROM barangays WHERE barangay_id = ? AND municipality_id = ? LIMIT 1');
        $chk->bind_param('ii', $barangay_id, $municipality_id);
    } else {
        $chk = $conn->prepare('SELECT 1 FROM barangays WHERE barangay_id = ? LIMIT 1');
        $chk->bind_param('i', $barangay_id);
    }
    $chk->execute();
    $valid_brgy = (bool)$chk->get_result()->fetch_row();
    $chk->close();
    if (!$valid_brgy) {
        back('profile_error=location');
    }

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
        'middle_name'         => ($middle_name !== '') ? $middle_name : null,
        'birthdate'           => $birth_date,
        'birth_date'          => $birth_date,
        'contact_number'      => $contact_number,
        'gender'              => $gender,
        'hearing_status'      => $hearing_status,
        'speech_status'       => $speech_status,
        'street_address'      => ($street_address !== '') ? $street_address : null,
        'accessibility_notes' => ($accessibility_notes !== '') ? $accessibility_notes : null,
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
