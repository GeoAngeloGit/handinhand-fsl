<?php
/**
 * db_connect.php — HandInHand FSL
 * Database connection + secure session + authentication helpers.
 *
 * Include at the top of every page:   require_once __DIR__ . '/db_connect.php';
 */
declare(strict_types=1);

/* ============================================================
   1. CONFIGURATION  (prefer environment variables in production)
   ============================================================ */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'handinhand_fsl');

// ASSUMPTION: match these to the role_id values in your Roles table.
define('ROLE_ADMIN', 1);
define('ROLE_TEACHER', 2);
define('ROLE_LEARNER', 3);

// Where each role lands once its profile exists / when its profile still needs setup.
const ROLE_DASHBOARD = [
    ROLE_ADMIN      => 'admin_dashboard.php',
    ROLE_TEACHER => 'teacher_dashboard.php',
    ROLE_LEARNER    => 'learner_dashboard.php',
];
const ROLE_SETUP_PAGE = [
    ROLE_TEACHER => 'teacher_setup.php',
    ROLE_LEARNER    => 'learner_setup.php',
];
const ROLE_LABEL = [
    ROLE_ADMIN      => 'admin',
    ROLE_TEACHER => 'teacher',
    ROLE_LEARNER    => 'learner',
];
// Table that proves a role's profile has been completed (keyed by account_id).
const ROLE_PROFILE_TABLE = [
    ROLE_TEACHER => 'teachers',
    ROLE_LEARNER    => 'learners',
];

/* ============================================================
   2. SECURE SESSION
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ============================================================
   3. DATABASE CONNECTION  ($conn)
   ============================================================ */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Service temporarily unavailable. Please try again later.');
}

/* ============================================================
   4. CSRF HELPERS
   ============================================================ */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/* ============================================================
   5. REGISTRATION  (Accounts + Learners in one transaction)
   ============================================================ */

/**
 * Creates an Accounts row (password hashed) and a Learners row atomically.
 *
 * @param array $d  Sanitized form values (see register.php)
 * @return int      New account_id
 * @throws RuntimeException with a user-safe message on validation/duplicate errors
 */
function register_learner(mysqli $conn, array $d, string $password): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT); // bcrypt/argon2 + automatic salt

    $conn->begin_transaction();
    try {
        // Barangay must exist
        $chk = $conn->prepare('SELECT 1 FROM barangays WHERE barangay_id = ? AND municipality_id = ?');
        $barangay_id     = (int)$d['barangay_id'];
        $municipality_id = (int)$d['municipality_id'];
        $chk->bind_param('ii', $barangay_id, $municipality_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_row()) {
            throw new RuntimeException('Please select a valid municipality and barangay.');
        }
        $chk->close();

        // 1) Accounts
        $role = ROLE_LEARNER;
        $stmt = $conn->prepare(
            'INSERT INTO accounts (role_id, username, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isss', $role, $d['username'], $d['email'], $hash);
        $stmt->execute();
        $account_id = (int)$conn->insert_id;
        $stmt->close();

        // 2) learners  (only middle name, notes and street are optional/NULLable)
        $nz = static fn($v) => ($v === '' || $v === null) ? null : $v;
        $middle    = $nz($d['middle_name']);
        $birthdate = $d['birthdate'];
        $contact   = $d['contact_number'];
        $gender    = $d['gender'];
        $notes     = $nz($d['accessibility_notes']);
        $street    = $nz($d['street_address']);

        $stmt = $conn->prepare(
            'INSERT INTO learners
                (account_id, first_name, middle_name, last_name, birthdate, contact_number,
                 gender, hearing_status, speech_status, accessibility_notes, barangay_id, street_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'isssssssssis',
            $account_id, $d['first_name'], $middle, $d['last_name'], $birthdate, $contact,
            $gender, $d['hearing_status'], $d['speech_status'], $notes, $barangay_id, $street
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return $account_id;
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ($e->getCode() === 1062) { // duplicate key on username/email
            throw new RuntimeException('That username or email is already registered.');
        }
        error_log('register_learner failed: ' . $e->getMessage());
        throw new RuntimeException('Could not create your account. Please try again.');
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/* ============================================================
   6. LOGIN / SESSION
   ============================================================ */

/**
 * Verifies credentials. Returns the account row on success, null on failure.
 * Accepts username OR email.
 */
function authenticate(mysqli $conn, string $identifier, string $password): ?array
{
    $stmt = $conn->prepare(
        'SELECT account_id, role_id, username, email, password_hash
           FROM accounts
          WHERE username = ? OR email = ?
          LIMIT 1'
    );
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Constant-ish timing: always run a verify, even for unknown users
    $hash = $account['password_hash'] ?? '$2y$10$usesomesillystringforsaltuMqXj3o5Pq0KxkQ1m8g6aY5v0yWq';
    $ok   = password_verify($password, $hash);

    if (!$account || !$ok) {
        return null;
    }

    // Upgrade hash if algorithm/cost defaults have changed
    if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
        $new = password_hash($password, PASSWORD_DEFAULT);
        $up  = $conn->prepare('UPDATE accounts SET password_hash = ? WHERE account_id = ?');
        $up->bind_param('si', $new, $account['account_id']);
        $up->execute();
        $up->close();
    }

    unset($account['password_hash']);
    return $account;
}

/** Establishes the logged-in session. */
function login_user(array $account): void
{
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['account_id']   = (int)$account['account_id'];
    $_SESSION['username']     = $account['username'];   // used by learner_dashboard.php
    $_SESSION['email']        = $account['email'];
    $_SESSION['role_id']      = (int)$account['role_id'];
    $_SESSION['role']         = ROLE_LABEL[(int)$account['role_id']] ?? 'unknown';
    $_SESSION['logged_in']    = true;
    $_SESSION['login_time']   = time();
    unset($_SESSION['login_attempts'], $_SESSION['lockout_until']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ============================================================
   7. ROLE ROUTING
   ============================================================ */

/** Has this account completed its role-specific profile? */
function has_role_profile(mysqli $conn, int $account_id, int $role_id): bool
{
    if (!isset(ROLE_PROFILE_TABLE[$role_id])) {
        return true; // e.g., admin: nothing to set up
    }
    $table = ROLE_PROFILE_TABLE[$role_id]; // from constant whitelist, never user input
    $stmt  = $conn->prepare("SELECT 1 FROM `$table` WHERE account_id = ? LIMIT 1");
    $stmt->bind_param('i', $account_id);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

/** Returns the page this user should land on after login. */
function post_login_destination(mysqli $conn, int $account_id, int $role_id): string
{
    if (!has_role_profile($conn, $account_id, $role_id)) {
        return ROLE_SETUP_PAGE[$role_id] ?? 'index.php';
    }
    return ROLE_DASHBOARD[$role_id] ?? 'index.php';
}

/**
 * Guard for protected pages.
 *   require_login();                         // any logged-in user
 *   require_login([ROLE_LEARNER]);           // learners only
 */
function require_login(array $allowed_roles = []): void
{
    if (empty($_SESSION['logged_in'])) {
        header('Location: login.php');
        exit;
    }
    if ($allowed_roles && !in_array($_SESSION['role_id'], $allowed_roles, true)) {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
}
