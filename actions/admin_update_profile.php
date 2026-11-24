<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db_connect.php';
header('Content-Type: application/json');

function out(bool $ok, string $msg, int $status = 200): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $msg]);
    exit;
}

if (empty($_SESSION['logged_in']) || ($_SESSION['role_id'] ?? 0) !== ROLE_ADMIN) {
    out(false, 'Not authorized.', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Invalid request.', 405);
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_verify($in['csrf_token'] ?? null)) {
    out(false, 'Session expired. Please reload the page.', 403);
}

$type = (string)($in['type'] ?? '');
$code = (string)($in['code'] ?? '');
$f    = is_array($in['fields'] ?? null) ? $in['fields'] : [];

if (!preg_match('/^[A-Z]{3}-(\d+)$/', $code, $m)) out(false, 'Invalid ID.', 400);
$id = (int)$m[1];

$tables = [
    'admin'   => ['administrators', 'admin_id'],
    'teacher' => ['teachers', 'teacher_id'],
    'learner' => ['learners', 'learner_id'],
];
if (!isset($tables[$type])) out(false, 'Invalid profile type.', 400);
[$table, $idcol] = $tables[$type];

// "Last, First M."  ->  last = "Last", first = "First" (middle initial dropped, middle name untouched)
$fullName = trim((string)($f['Full Name'] ?? ''));
if (!str_contains($fullName, ',')) out(false, 'Use the format "Last, First".', 400);
[$last, $firstPart] = array_map('trim', explode(',', $fullName, 2));
$first = trim(preg_replace('/\s+[A-Za-z]\.$/', '', $firstPart));
if ($first === '' || $last === '') out(false, 'First and last name are required.', 400);

$email = trim((string)($f['Email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 'Invalid email address.', 400);

$conn->begin_transaction();
try {
    // Find the linked account
    $stmt = $conn->prepare("SELECT account_id FROM `$table` WHERE `$idcol` = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) { $conn->rollback(); out(false, 'Record not found.', 404); }
    $acc = (int)$row['account_id'];

    // accounts: email (+ username for admins)
    if ($type === 'admin') {
        $username = ltrim(trim((string)($f['Username'] ?? '')), '@');
        if ($username === '' || strlen($username) > 30) { $conn->rollback(); out(false, 'Username must be 1-30 characters.', 400); }
        $stmt = $conn->prepare('UPDATE accounts SET username = ?, email = ? WHERE account_id = ?');
        $stmt->bind_param('ssi', $username, $email, $acc);
    } else {
        $stmt = $conn->prepare('UPDATE accounts SET email = ? WHERE account_id = ?');
        $stmt->bind_param('si', $email, $acc);
    }
    $stmt->execute();
    $stmt->close();

    // role table
    $skip = static fn($v) => ($v === '—' ? '' : $v);
    if ($type === 'admin') {
        $contact = $skip(trim((string)($f['Contact Number'] ?? '')));
$stmt = $conn->prepare(
    'UPDATE administrators SET first_name = ?, last_name = ?,
            contact_number = COALESCE(NULLIF(?, ""), contact_number)
      WHERE admin_id = ?'
);
$stmt->bind_param('sssi', $first, $last, $contact, $id);
    } elseif ($type === 'teacher') {
        $contact = $skip(trim((string)($f['Contact Number'] ?? '')));
        $spec    = trim((string)($f['FSL Specialization'] ?? ''));
        $years   = max(0, (int)preg_replace('/\D/', '', (string)($f['Years of Experience'] ?? '0')));
        $stmt = $conn->prepare(
            'UPDATE teachers SET first_name = ?, last_name = ?,
                    contact_number = COALESCE(NULLIF(?, ""), contact_number),
                    specialization = COALESCE(NULLIF(?, ""), specialization),
                    years_of_experience = ?
              WHERE teacher_id = ?'
        );
        $stmt->bind_param('ssssii', $first, $last, $contact, $spec, $years, $id);
    } else { // learner
        $contact = $skip(trim((string)($f['Contact'] ?? '')));
        $stmt = $conn->prepare(
            'UPDATE learners SET first_name = ?, last_name = ?,
                    contact_number = COALESCE(NULLIF(?, ""), contact_number)
              WHERE learner_id = ?'
        );
        $stmt->bind_param('sssi', $first, $last, $contact, $id);
    }
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    out(true, 'Profile updated.');
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    if ($e->getCode() === 1062) out(false, 'That username or email is already in use.', 409);
    error_log('admin_update_profile failed: ' . $e->getMessage());
    out(false, 'Could not save changes.', 500);
}