<?php
require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// CSRF
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: login.php?error=csrf');
    exit;
}

// Simple brute-force throttle: 5 failures => 15-minute lockout (per session)
if (($_SESSION['lockout_until'] ?? 0) > time()) {
    header('Location: login.php?error=locked');
    exit;
}

$identifier = trim($_POST['login_identifier'] ?? '');
$password   = $_POST['password'] ?? '';

if ($identifier === '' || $password === '') {
    header('Location: login.php?error=invalid');
    exit;
}

$account = authenticate($conn, $identifier, $password);

if ($account === null) {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['lockout_until'] = time() + 900;
    }
    header('Location: login.php?error=invalid');
    exit;
}

login_user($account);

// Route: profile setup page if the role profile is missing, otherwise the dashboard
$dest = post_login_destination($conn, (int)$account['account_id'], (int)$account['role_id']);
header('Location: ' . $dest);
exit;
