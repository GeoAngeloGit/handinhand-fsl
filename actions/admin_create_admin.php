<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';

function back(string $qs): void {
    header('Location: ../admin_directory.php?' . $qs);
    exit;
}

// Admins only
if (empty($_SESSION['logged_in']) || ($_SESSION['role_id'] ?? 0) !== ROLE_ADMIN) {
    header('Location: ../login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back('');
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    back('admin_error=csrf');
}

$first    = trim($_POST['first_name'] ?? '');
$middle   = trim($_POST['middle_name'] ?? '');
$last     = trim($_POST['last_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$contact  = trim($_POST['contact_number'] ?? '');
$password = $_POST['password'] ?? '';

if ($first === '' || $last === '' || $username === '' || $email === '') {
    back('admin_error=missing');
}
if (strlen($username) > 30) {
    back('admin_error=username_long');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    back('admin_error=email');
}
if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact)) {
    back('admin_error=contact');
}
if (strlen($password) < 8) {
    back('admin_error=password');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role = ROLE_ADMIN;

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        'INSERT INTO accounts (role_id, username, email, password_hash) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('isss', $role, $username, $email, $hash);
    $stmt->execute();
    $account_id = (int)$conn->insert_id;
    $stmt->close();

    // administrators.contact_number is NOT NULL and the form has no phone field
    $middle_db = ($middle === '') ? null : $middle;
    $stmt = $conn->prepare(
        'INSERT INTO administrators (account_id, first_name, middle_name, last_name, contact_number) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $account_id, $first, $middle_db, $last, $contact);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    back('admin_created=1');
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ($e->getCode() === 1062) {
        back('admin_error=duplicate');
    }
    error_log('admin_create_admin failed: ' . $e->getMessage());
    back('admin_error=server');
}