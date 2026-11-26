<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$accountId = (int)($_SESSION['account_id'] ?? 1);

if (isset($_GET['switch_learner'])) {
    $_SESSION['active_learner_id'] = (int)$_GET['switch_learner'];
}

$learnersList    = [];
$classesList     = [];
$schedulesList   = [];
$enrollmentsList = [];
$gradesList      = [];
$municipalities  = [];
$barangays       = [];

try {
    // 1. Learners under the active account
    $stmt = $conn->prepare("
        SELECT l.*, m.municipality_name, b.barangay_name
        FROM learners l
        LEFT JOIN municipalities m ON l.municipality_id = m.municipality_id
        LEFT JOIN barangays b ON l.barangay_id = b.barangay_id
        WHERE l.account_id = ?
        ORDER BY l.learner_id ASC
    ");
    $stmt->bind_param('i', $accountId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $learnersList[] = $row;
    }
    $stmt->close();

    // Validate active_learner_id belongs to this account
    $activeLearnerId = (int)($_SESSION['active_learner_id'] ?? 0);
    $validIds = array_map(fn($r) => (int)$r['learner_id'], $learnersList);
    if (!in_array($activeLearnerId, $validIds, true)) {
        $activeLearnerId = $validIds[0] ?? 0;
        $_SESSION['active_learner_id'] = $activeLearnerId;
    }

    // 2. All Classes + assigned teachers + active enrolled count
    $classSql = "
        SELECT c.*,
               COALESCE(
                 GROUP_CONCAT(DISTINCT CONCAT('Teacher ', t.first_name, ' ', t.last_name) ORDER BY t.teacher_id SEPARATOR ', '),
                 'TBA'
               ) AS instructor,
               (SELECT COUNT(*) FROM enrollments e2 WHERE e2.class_id = c.class_id AND e2.status IN ('enrolled','completed')) AS current_enrolled
        FROM classes c
        LEFT JOIN class_teachers ct ON c.class_id = ct.class_id
        LEFT JOIN teachers t ON ct.teacher_id = t.teacher_id
        GROUP BY c.class_id
        ORDER BY c.class_id ASC
    ";
    if ($res = $conn->query($classSql)) {
        while ($row = $res->fetch_assoc()) {
            $classesList[] = $row;
        }
    }

    // 3. Class Schedules
    $schSql = "
        SELECT cs.schedule_id, cs.class_id, cs.session_date,
               TIME_FORMAT(cs.start_time, '%l:%i %p') AS start_time,
               c.venue
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.class_id
        ORDER BY cs.session_date ASC, cs.start_time ASC
    ";
    if ($res = $conn->query($schSql)) {
        while ($row = $res->fetch_assoc()) {
            $schedulesList[] = $row;
        }
    }

    // 4. Enrollments for active learner
    if ($activeLearnerId > 0) {
        $enrStmt = $conn->prepare("
            SELECT enrollment_id, learner_id, class_id, status, session_rating, instructor_notes,
                   DATE_FORMAT(enrolled_at, '%Y-%m-%d') AS enrolled_date
            FROM enrollments
            WHERE learner_id = ?
            ORDER BY enrollment_id ASC
        ");
        $enrStmt->bind_param('i', $activeLearnerId);
        $enrStmt->execute();
        $res = $enrStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $enrollmentsList[] = $row;
        }
        $enrStmt->close();

        // 5. Session Grades / Evaluations
        $grdStmt = $conn->prepare("
            SELECT sg.grade_id, sg.enrollment_id, sg.schedule_id, sg.grade_status, sg.remarks,
                   cs.session_date, TIME_FORMAT(cs.start_time, '%l:%i %p') AS start_time
            FROM session_grades sg
            JOIN enrollments e ON sg.enrollment_id = e.enrollment_id
            JOIN class_schedules cs ON sg.schedule_id = cs.schedule_id
            WHERE e.learner_id = ?
            ORDER BY cs.session_date ASC, cs.start_time ASC
        ");
        $grdStmt->bind_param('i', $activeLearnerId);
        $grdStmt->execute();
        $res = $grdStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $gradesList[] = $row;
        }
        $grdStmt->close();
    }

    // 6. Albay Municipalities & Barangays
    if ($res = $conn->query("SELECT municipality_id, municipality_name FROM municipalities ORDER BY municipality_name ASC")) {
        while ($row = $res->fetch_assoc()) {
            $municipalities[] = $row;
        }
    }
    if ($res = $conn->query("SELECT barangay_id, municipality_id, barangay_name FROM barangays ORDER BY barangay_name ASC")) {
        while ($row = $res->fetch_assoc()) {
            $barangays[] = $row;
        }
    }
} catch (mysqli_sql_exception $e) {
    $activeLearnerId = (int)($_SESSION['active_learner_id'] ?? 0);
}

$initialState = [
    'csrfToken'       => $_SESSION['csrf_token'],
    'activeLearnerId' => $activeLearnerId ?? 0,
    'learners'        => $learnersList,
    'classes'         => $classesList,
    'schedules'       => $schedulesList,
    'enrollments'     => $enrollmentsList,
    'sessionGrades'   => $gradesList,
    'municipalities'  => $municipalities,
    'barangays'       => $barangays,
];
?>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($pageTitle ?? 'HandInHand — Learner Portal | Albay Province') ?></title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="assets/css/learner.css" />
<script>
  window.HIH_INITIAL_STATE = <?= json_encode($initialState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
</script>