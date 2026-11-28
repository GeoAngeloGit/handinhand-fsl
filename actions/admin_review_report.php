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
    out(false, 'Session expired. Reload the page.', 403);
}

$report_id = (int)($in['report_id'] ?? 0);
$status    = trim((string)($in['review_status'] ?? 'Verified'));
$notes     = trim((string)($in['coordinator_notes'] ?? ''));

$allowed = ['Pending', 'Finished', 'Verified', 'For Revision'];
if ($report_id <= 0 || !in_array($status, $allowed, true)) {
    out(false, 'Invalid review parameters.', 400);
}

try {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM post_class_report");
    while ($c = $res->fetch_assoc()) {
        $cols[] = $c['Field'];
    }

    if (in_array('admin_remarks', $cols, true)) {
        $stmt = $conn->prepare("UPDATE post_class_report SET status = ?, admin_remarks = ? WHERE report_id = ?");
        $stmt->bind_param('ssi', $status, $notes, $report_id);
    } else {
        $stmt = $conn->prepare("UPDATE post_class_report SET status = ? WHERE report_id = ?");
        $stmt->bind_param('si', $status, $report_id);
    }
    $stmt->execute();
    $stmt->close();

    out(true, 'Report review status updated.');
} catch (Throwable $e) {
    error_log('admin_review_report failed: ' . $e->getMessage());
    out(false, 'Could not update report status.', 500);
}
