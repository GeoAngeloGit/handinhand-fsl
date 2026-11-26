<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$accountId = (int)($_SESSION['account_id'] ?? 0);

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
$dbErrors        = [];
$activeLearnerId = (int)($_SESSION['active_learner_id'] ?? 0);

if (isset($conn) && $conn instanceof mysqli) {
    // 1. Learners (join municipalities via barangays.municipality_id)
    try {
        if ($accountId > 0) {
            $stmt = $conn->prepare("
                SELECT l.*, b.municipality_id, m.municipality_name, b.barangay_name
                FROM `learners` l
                LEFT JOIN `barangays` b ON l.barangay_id = b.barangay_id
                LEFT JOIN `municipalities` m ON b.municipality_id = m.municipality_id
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
        }

        // Fallback if session account_id had no matching rows or wasn't set yet
        if (empty($learnersList)) {
            $res = $conn->query("
                SELECT l.*, b.municipality_id, m.municipality_name, b.barangay_name
                FROM `learners` l
                LEFT JOIN `barangays` b ON l.barangay_id = b.barangay_id
                LEFT JOIN `municipalities` m ON b.municipality_id = m.municipality_id
                ORDER BY l.learner_id ASC
            ");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $learnersList[] = $row;
                }
            }
        }

        $validIds = array_map(fn($r) => (int)$r['learner_id'], $learnersList);
        if (!in_array($activeLearnerId, $validIds, true)) {
            $activeLearnerId = $validIds[0] ?? 0;
            $_SESSION['active_learner_id'] = $activeLearnerId;
        }
    } catch (mysqli_sql_exception $e) {
        $dbErrors['learners'] = $e->getMessage();
    }

    // 2. Classes + assigned teachers + venue
    try {
        $classSql = "
            SELECT c.class_id, c.created_by, c.class_title, c.description,
                   c.max_capacity, c.current_enrolled, c.status,
                   COALESCE(
                     GROUP_CONCAT(DISTINCT CONCAT('Teacher ', t.first_name, ' ', t.last_name) ORDER BY t.teacher_id SEPARATOR ', '),
                     'TBA'
                   ) AS instructor,
                   COALESCE(MIN(cs.venue), 'TBA') AS venue
            FROM `class` c
            LEFT JOIN `class_teacher` ct ON c.class_id = ct.class_id
            LEFT JOIN `teachers` t ON ct.teacher_id = t.teacher_id
            LEFT JOIN `class_schedule` cs ON c.class_id = cs.class_id
            GROUP BY c.class_id
            ORDER BY c.class_id ASC
        ";
        if ($res = $conn->query($classSql)) {
            while ($row = $res->fetch_assoc()) {
                $classesList[] = $row;
            }
        }
    } catch (mysqli_sql_exception $e) {
        $dbErrors['classes'] = $e->getMessage();
    }

    // 3. Class Schedules
    try {
        $schSql = "
            SELECT cs.schedule_id, cs.class_id, cs.session_date,
                   TIME_FORMAT(cs.start_time, '%l:%i %p') AS start_time,
                   TIME_FORMAT(cs.end_time, '%l:%i %p') AS end_time,
                   cs.venue, cs.status
            FROM `class_schedule` cs
            ORDER BY cs.session_date ASC, cs.start_time ASC
        ";
        if ($res = $conn->query($schSql)) {
            while ($row = $res->fetch_assoc()) {
                $schedulesList[] = $row;
            }
        }
    } catch (mysqli_sql_exception $e) {
        $dbErrors['schedules'] = $e->getMessage();
    }

    // 4. Enrollments for active learner
    if ($activeLearnerId > 0) {
        try {
            $enrStmt = $conn->prepare("
            SELECT enrollment_id, learner_id, class_id, status,
                DATE_FORMAT(enrollment_date, '%Y-%m-%d') AS enrolled_date
            FROM `class_enrollments`
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
        } catch (mysqli_sql_exception $e) {
            $dbErrors['enrollments'] = $e->getMessage();
        }

        // 5. Session Grades / Evaluations
        try {
            $grdStmt = $conn->prepare("
            SELECT v.attendance_id AS grade_id,
                e.enrollment_id,
                v.class_id,
                v.schedule_id,
                v.attendance_status,
                v.session_rating,
                v.remarks,
                v.session_date,
                TIME_FORMAT(v.start_time, '%l:%i %p') AS start_time
            FROM `vw_session_attendance_grades` v
            JOIN `class_enrollments` e
            ON e.class_id = v.class_id AND e.learner_id = v.learner_id
            WHERE v.learner_id = ?
            ORDER BY v.session_date ASC, v.start_time ASC
        ");
            $grdStmt->bind_param('i', $activeLearnerId);
            $grdStmt->execute();
            $res = $grdStmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $gradesList[] = $row;
            }
            $grdStmt->close();
        } catch (mysqli_sql_exception $e) {
            $dbErrors['session_grades'] = $e->getMessage();
        }
    }

    // 6. Albay Municipalities & Barangays
    try {
        if ($res = $conn->query("SELECT municipality_id, municipality_name FROM `municipalities` ORDER BY municipality_name ASC")) {
            while ($row = $res->fetch_assoc()) {
                $municipalities[] = $row;
            }
        }
        if ($res = $conn->query("SELECT barangay_id, municipality_id, barangay_name FROM `barangays` ORDER BY barangay_name ASC")) {
            while ($row = $res->fetch_assoc()) {
                $barangays[] = $row;
            }
        }
    } catch (mysqli_sql_exception $e) {
        $dbErrors['locations'] = $e->getMessage();
    }
}

$initialState = [
    'csrfToken'       => $_SESSION['csrf_token'],
    'activeLearnerId' => $activeLearnerId,
    'learners'        => $learnersList,
    'classes'         => $classesList,
    'schedules'       => $schedulesList,
    'enrollments'     => $enrollmentsList,
    'sessionGrades'   => $gradesList,
    'municipalities'  => $municipalities,
    'barangays'       => $barangays,
    'dbErrors'        => $dbErrors,
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