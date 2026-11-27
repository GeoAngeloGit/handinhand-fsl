<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db_connect.php';

if (!defined('ROLE_TEACHER')) {
    define('ROLE_TEACHER', 2);
}

$allowed_pages = [
    'teacher_dashboard.php',
    'teacher_classes.php',
    'teacher_learners.php',
    'teacher_reports.php',
];

$return_to = basename((string)($_POST['return_to'] ?? 'teacher_dashboard.php'));
if (!in_array($return_to, $allowed_pages, true)) {
    $return_to = 'teacher_dashboard.php';
}

function redirect_teacher_profile(string $page, string $msg, string $type = 'error'): void
{
    $_SESSION['teacher_profile_flash']      = $msg;
    $_SESSION['teacher_profile_flash_type'] = $type;
    header('Location: ../' . $page);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../' . $return_to);
    exit;
}

if (!empty($_SESSION['logged_in']) && (int)($_SESSION['role_id'] ?? 0) !== ROLE_TEACHER) {
    redirect_teacher_profile($return_to, 'Unauthorized account role.');
}

if (function_exists('csrf_verify') && !empty($_POST['csrf_token'])) {
    if (!csrf_verify($_POST['csrf_token'])) {
        redirect_teacher_profile($return_to, 'Session expired. Refresh the page and try again.');
    }
}

$teacher_id  = (int)($_POST['teacher_id'] ?? 0);
$account_id  = (int)($_SESSION['account_id'] ?? ($_POST['account_id'] ?? 0));
$first_name  = trim((string)($_POST['first_name'] ?? ''));
$middle_name = trim((string)($_POST['middle_name'] ?? ''));
$last_name   = trim((string)($_POST['last_name'] ?? ''));
$birth_date  = trim((string)($_POST['birth_date'] ?? ''));
$gender      = trim((string)($_POST['gender'] ?? ''));
$contact     = trim((string)($_POST['contact_number'] ?? ''));
$email       = trim((string)($_POST['email'] ?? ''));
$barangay_id = (int)($_POST['barangay_id'] ?? 0);
$street      = trim((string)($_POST['street_address'] ?? ''));
$spec        = trim((string)($_POST['fsl_specialization'] ?? ''));
$experience  = max(0, (int)($_POST['years_of_experience'] ?? 0));

if ($teacher_id <= 0 || $first_name === '' || $last_name === '') {
    redirect_teacher_profile($return_to, 'First Name and Last Name are required.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_teacher_profile($return_to, 'Please enter a valid email address.');
}

try {
    // Verify teacher ownership when logged in
    if ($account_id > 0) {
        $chk = $conn->prepare('SELECT teacher_id, account_id FROM teachers WHERE teacher_id = ? AND account_id = ? LIMIT 1');
        $chk->bind_param('ii', $teacher_id, $account_id);
        $chk->execute();
        $owned = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$owned) {
            redirect_teacher_profile($return_to, 'You cannot edit another teacher profile.');
        }
    } else {
        $chk = $conn->prepare('SELECT account_id FROM teachers WHERE teacher_id = ? LIMIT 1');
        $chk->bind_param('i', $teacher_id);
        $chk->execute();
        $owned = $chk->get_result()->fetch_assoc();
        $chk->close();
        $account_id = (int)($owned['account_id'] ?? 0);
    }

    // Inspect existing columns in teachers table so optional columns match schema safely
    $cols = [];
    if ($res = $conn->query('SHOW COLUMNS FROM teachers')) {
        while ($c = $res->fetch_assoc()) {
            $cols[$c['Field']] = true;
        }
    }

    $conn->begin_transaction();

    $sets   = ['first_name = ?', 'last_name = ?'];
    $types  = 'ss';
    $values = [$first_name, $last_name];

    if (isset($cols['middle_name'])) {
        $sets[]   = 'middle_name = NULLIF(?, "")';
        $types   .= 's';
        $values[] = $middle_name;
    }

    $bdate_col = isset($cols['birth_date']) ? 'birth_date' : (isset($cols['birthdate']) ? 'birthdate' : '');
    if ($bdate_col !== '') {
        $sets[]   = "{$bdate_col} = NULLIF(?, '')";
        $types   .= 's';
        $values[] = $birth_date;
    }

    if (isset($cols['gender'])) {
        $sets[]   = 'gender = NULLIF(?, "")';
        $types   .= 's';
        $values[] = $gender;
    }

    if (isset($cols['contact_number'])) {
        $sets[]   = 'contact_number = NULLIF(?, "")';
        $types   .= 's';
        $values[] = $contact;
    }

    if (isset($cols['barangay_id']) && $barangay_id > 0) {
        $sets[]   = 'barangay_id = ?';
        $types   .= 'i';
        $values[] = $barangay_id;
    }

    $street_col = isset($cols['street_address']) ? 'street_address'
                : (isset($cols['purok_address']) ? 'purok_address'
                : (isset($cols['address']) ? 'address' : ''));
    if ($street_col !== '') {
        $sets[]   = "{$street_col} = NULLIF(?, '')";
        $types   .= 's';
        $values[] = $street;
    }

    $spec_col = isset($cols['fsl_specialization']) ? 'fsl_specialization'
              : (isset($cols['specialization']) ? 'specialization' : '');
    if ($spec_col !== '') {
        $sets[]   = "{$spec_col} = NULLIF(?, '')";
        $types   .= 's';
        $values[] = $spec;
    }

    $exp_col = isset($cols['years_of_experience']) ? 'years_of_experience'
             : (isset($cols['experience_years']) ? 'experience_years' : '');
    if ($exp_col !== '') {
        $sets[]   = "{$exp_col} = ?";
        $types   .= 'i';
        $values[] = $experience;
    }

    $types   .= 'i';
    $values[] = $teacher_id;

    $sql = 'UPDATE teachers SET ' . implode(', ', $sets) . ' WHERE teacher_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();

    if ($account_id > 0 && $email !== '') {
        $up_acc = $conn->prepare('UPDATE accounts SET email = ? WHERE account_id = ?');
        $up_acc->bind_param('si', $email, $account_id);
        $up_acc->execute();
        $up_acc->close();
    }

    $conn->commit();
    redirect_teacher_profile($return_to, 'Teacher profile updated successfully.', 'success');
} catch (Throwable $e) {
    if ($conn->errno || $conn->in_transaction) {
        @$conn->rollback();
    }
    error_log('teacher_update_profile: ' . $e->getMessage());
    redirect_teacher_profile($return_to, 'Could not update teacher profile. Please check your inputs.');
}