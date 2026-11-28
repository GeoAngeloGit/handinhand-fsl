<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/db_connect.php';

require_login([ROLE_ADMIN]);

$admin_username = $_SESSION['username'] ?? 'ADMIN';
$coordinator_name = 'Albay Coordinator';
$coordinator_hub  = 'Legazpi Hub';

// Fetch logged-in admin details if available
if (!empty($_SESSION['account_id'])) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM administrators WHERE account_id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['account_id']);
    $stmt->execute();
    if ($adm = $stmt->get_result()->fetch_assoc()) {
        $coordinator_name = trim($adm['first_name'] . ' ' . $adm['last_name']);
    }
    $stmt->close();
}

// Compute initials for sidebar avatar badge
$name_parts = preg_split('/\s+/', trim($coordinator_name));
$initials = strtoupper(substr($name_parts[0] ?? 'A', 0, 1) . substr($name_parts[1] ?? 'C', 0, 1));

// ==================== KPI METRICS ====================
$total_learners   = 0;
$new_learners_wk  = 0;
$active_teachers  = 0;
$active_hubs      = 0;
$total_reports    = 0;

try {
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM learners WHERE is_active = 1");
    $total_learners = (int)($res->fetch_assoc()['cnt'] ?? 0);

    $wk_res = $conn->query("SELECT COUNT(*) AS cnt FROM learners WHERE is_active = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $new_learners_wk = (int)($wk_res->fetch_assoc()['cnt'] ?? 0);

    $res = $conn->query("SELECT COUNT(*) AS cnt FROM teachers WHERE is_active = 1");
    $active_teachers = (int)($res->fetch_assoc()['cnt'] ?? 0);

    $hub_res = $conn->query("SELECT COUNT(DISTINCT b.municipality_id) AS cnt FROM teachers t JOIN barangays b ON t.barangay_id = b.barangay_id WHERE t.is_active = 1");
    $active_hubs = (int)($hub_res->fetch_assoc()['cnt'] ?? 0);

    $res = $conn->query("SELECT COUNT(*) AS cnt FROM post_class_report");
    $total_reports = (int)($res->fetch_assoc()['cnt'] ?? 0);
} catch (Throwable $e) {
    error_log('admin_dashboard KPI error: ' . $e->getMessage());
}

// ==================== RECENT REGISTRATIONS ====================
$recent_registrations = [];
try {
    $sql_reg = "
        SELECT 
            a.account_id,
            a.role_id,
            YEAR(a.created_at) AS reg_year,
            COUNT(l.learner_id) AS learner_count,
            GROUP_CONCAT(l.first_name ORDER BY l.learner_id ASC SEPARATOR ' & ') AS learner_first_names,
            MAX(l.last_name) AS learner_last_name,
            MAX(CONCAT(t.first_name, ' ', IFNULL(CONCAT(t.middle_name, ' '), ''), t.last_name)) AS teacher_full_name
        FROM accounts a
        LEFT JOIN learners l ON a.account_id = l.account_id
        LEFT JOIN teachers t ON a.account_id = t.account_id
        WHERE a.role_id IN (2, 3)
        GROUP BY a.account_id, a.role_id, YEAR(a.created_at)
        ORDER BY a.created_at DESC
        LIMIT 4
    ";
    $reg_res = $conn->query($sql_reg);
    while ($row = $reg_res->fetch_assoc()) {
        $acc_code = sprintf('ACC-%s-%03d', $row['reg_year'] ?: date('Y'), (int)$row['account_id']);
        if ((int)$row['role_id'] === 2 && !empty($row['teacher_full_name'])) {
            $recent_registrations[] = [
                'display_name' => $row['teacher_full_name'],
                'acc_code'     => $acc_code,
                'badge_label'  => 'TEACHER',
                'badge_type'   => 'teacher'
            ];
        } elseif ((int)$row['role_id'] === 3 && !empty($row['learner_first_names'])) {
            $cnt = (int)$row['learner_count'];
            $display = trim($row['learner_first_names'] . ' ' . $row['learner_last_name']);
            $recent_registrations[] = [
                'display_name' => $display,
                'acc_code'     => $acc_code,
                'badge_label'  => $cnt > 1 ? "LEARNER ({$cnt})" : 'LEARNER',
                'badge_type'   => 'learner'
            ];
        }
    }
} catch (Throwable $e) {
    // Fallback below
}

if (empty($recent_registrations)) {
    $recent_registrations = [
        [
            'display_name' => 'Juan & Maria De La Cruz',
            'acc_code'     => 'ACC-2026-014',
            'badge_label'  => 'LEARNER (2)',
            'badge_type'   => 'learner'
        ],
        [
            'display_name' => 'Elena Reyes Santos',
            'acc_code'     => 'ACC-2026-015',
            'badge_label'  => 'TEACHER',
            'badge_type'   => 'teacher'
        ],
        [
            'display_name' => 'June & Martha De Vera',
            'acc_code'     => 'ACC-2026-016',
            'badge_label'  => 'LEARNER (2)',
            'badge_type'   => 'learner'
        ],
        [
            'display_name' => 'Carlo Bautista Mendoza',
            'acc_code'     => 'ACC-2026-017',
            'badge_label'  => 'TEACHER',
            'badge_type'   => 'teacher'
        ]
    ];
}

// ==================== UPCOMING CLASSES & CALENDAR EVENTS ====================
$upcoming_classes = [];
$calendar_events  = [];
try {
    $sql_cls = "
        SELECT 
            c.class_id,
            c.class_title,
            c.max_capacity,
            c.current_enrolled,
            cs.session_date,
            cs.start_time,
            cs.venue,
            cs.status AS session_status,
            CONCAT(t.first_name, ' ', IFNULL(CONCAT(LEFT(t.middle_name, 1), '. '), ''), t.last_name) AS lead_teacher
        FROM class_schedule cs
        JOIN class c ON cs.class_id = c.class_id
        LEFT JOIN class_teacher ct ON c.class_id = ct.class_id AND ct.assigned_role = 'Lead Instructor'
        LEFT JOIN teachers t ON ct.teacher_id = t.teacher_id
        WHERE cs.status IN ('scheduled', 'ongoing', 'rescheduled')
        ORDER BY cs.session_date ASC, cs.start_time ASC
    ";
    $cls_res = $conn->query($sql_cls);
    $today_iso = date('Y-m-d');
    while ($row = $cls_res->fetch_assoc()) {
        $venue    = trim((string)($row['venue'] ?? '')) ?: 'Albay Hub';
        $teacher  = trim((string)($row['lead_teacher'] ?? '')) ?: 'TBA Educator';
        $date_iso = (string)$row['session_date'];
        $date_str = date('D, M j, Y', strtotime($date_iso));
        $time_str = date('g:i A', strtotime((string)$row['start_time']));
        $enrolled = (int)$row['current_enrolled'];
        $cap      = (int)($row['max_capacity'] ?: 20);
        $sched_line = "{$date_str} • {$time_str} • {$enrolled}/{$cap} Enrolled";

        if (!isset($calendar_events[$date_iso])) {
            $calendar_events[$date_iso] = [
                'title' => $row['class_title'],
                'sub'   => "{$time_str} • {$venue} ({$teacher})"
            ];
        }

        if ($date_iso >= $today_iso && count($upcoming_classes) < 3) {
            $upcoming_classes[] = [
                'title'    => $row['class_title'],
                'meta'     => "{$venue} • {$teacher}",
                'schedule' => $sched_line,
                'date_iso' => $date_iso,
                'hub_only' => $venue
            ];
        }
    }
} catch (Throwable $e) {
    error_log('admin_dashboard upcoming classes error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/admin.css?v=3">
</head>
<body>

<div class="admin-layout">

    <!-- ==================== LEFT SIDEBAR ==================== -->
    <aside class="sidebar" id="adminSidebar">
        <div class="sidebar-top">
            <div class="brand-header">
                <div class="brand-row">
                    <a href="admin_dashboard.php" class="brand-title">HandInHand</a>
                    <span class="admin-pill">ADMIN</span>
                </div>
                <span class="brand-region">Albay Province &bull; Bicol Region</span>
            </div>

            <div class="menu-section">
                <span class="menu-label">MAIN MENU</span>
                <nav class="sidebar-nav" aria-label="Admin Navigation">
                    <a href="admin_dashboard.php" class="nav-item active">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="admin_users.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>User Accounts</span>
                    </a>

                    <a href="admin_directory.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>Directory</span>
                    </a>

                    <a href="admin_classes.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                        <span>Class</span>
                    </a>

                    <a href="admin_reports.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        <span>Reports</span>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Bottom Coordinator Profile Card -->
        <div class="sidebar-footer">
            <div class="coordinator-card">
                <div class="coord-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div class="coord-info">
                    <strong><?php echo htmlspecialchars($coordinator_name); ?></strong>
                    <span><?php echo htmlspecialchars($coordinator_hub); ?> &bull; <a href="actions/logout.php" class="logout-link">Log out</a></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT AREA ==================== -->
    <main class="main-content">

        <!-- Page Header -->
        <header class="page-header">
            <div class="header-titles">
                <span class="portal-eyebrow">ADMINISTRATION PORTAL &bull; OVERVIEW</span>
                <h1 class="page-title">Admin <em>Dashboard</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- URL Feedback Banner -->
        <?php if (isset($_GET['admin_created'])): ?>
            <div class="alert-banner alert-success" id="dirAlertBanner">
                <span>New administrator account created and added to the directory.</span>
                <button type="button" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php elseif (isset($_GET['admin_error'])): ?>
            <div class="alert-banner alert-error" id="dirAlertBanner">
                <span>
                    <?php
                    echo match ($_GET['admin_error']) {
                        'csrf'      => 'Session expired. Please reload the page and try again.',
                        'missing'   => 'Please fill in all required administrator fields.',
                        'password'  => 'Password must be at least 8 characters long.',
                        'duplicate' => 'That email address or username is already registered.',
                        default     => 'Could not create administrator account. Please try again.',
                    };
                    ?>
                </span>
                <button type="button" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>
        <!-- Welcome Banner -->
        <section class="welcome-banner">
            <h2>Welcome, <em><?php echo htmlspecialchars(strtoupper($admin_username)); ?></em></h2>
            <p>Manage Albay Province Filipino Sign Language classes, barangay hubs, and learner households.</p>
        </section>

        <!-- 3 KPI Stat Cards -->
        <section class="kpi-grid">
            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL LEARNERS</span>
                    <a href="admin_directory.php?tab=learners" class="card-action-link">View Directory &rarr;</a>
                </div>
                <div class="kpi-value"><?php echo number_format($total_learners); ?></div>
                <p class="kpi-sub">+<?php echo $new_learners_wk; ?> registered this week</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">ACTIVE TEACHERS</span>
                    <a href="admin_directory.php?tab=teachers" class="card-action-link">View Directory &rarr;</a>
                </div>
                <div class="kpi-value"><?php echo number_format($active_teachers); ?></div>
                <p class="kpi-sub">Across <?php echo $active_hubs; ?> Albay hubs</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL REPORTS</span>
                    <a href="admin_reports.php" class="card-action-link">View Reports &rarr;</a>
                </div>
                <div class="kpi-value"><?php echo number_format($total_reports); ?></div>
                <p class="kpi-sub">Attendance &amp; Learner grades</p>
            </article>
        </section>

        <!-- Bottom 3-Column Bento Grid -->
        <section class="bento-grid">

            <!-- Column 1: Recent Registrations -->
            <article class="panel-card">
                <div class="panel-head">
                    <h2 class="panel-title">Recent Registrations</h2>
                    <a href="admin_users.php" class="card-action-link">User Accounts &rarr;</a>
                </div>

                <div class="item-stack">
                    <?php foreach ($recent_registrations as $reg): ?>
                        <div class="reg-row">
                            <div class="reg-meta">
                                <strong><?php echo htmlspecialchars($reg['display_name']); ?></strong>
                                <span><?php echo htmlspecialchars($reg['acc_code']); ?></span>
                            </div>
                            <span class="role-tag <?php echo $reg['badge_type'] === 'learner' ? 'tag-blue' : 'tag-berry'; ?>">
                                <?php echo htmlspecialchars($reg['badge_label']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <!-- Column 2: Upcoming Classes -->
            <article class="panel-card">
                <div class="panel-head">
                    <h2 class="panel-title">Upcoming Classes</h2>
                    <a href="admin_classes.php" class="card-action-link">Class &rarr;</a>
                </div>

                <div class="item-stack">
                    <?php if (empty($upcoming_classes)): ?>
                        <div class="class-row">
                            <strong>No upcoming classes scheduled</strong>
                            <span class="class-hub">Create or schedule a class in Manage Classes.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_classes as $cls): ?>
                            <div class="class-row">
                                <strong><?php echo htmlspecialchars($cls['title']); ?></strong>
                                <span class="class-hub"><?php echo htmlspecialchars($cls['meta']); ?></span>
                                <span class="class-time"><?php echo htmlspecialchars($cls['schedule']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <!-- Column 3: Interactive November 2026 Calendar -->
            <article class="panel-card calendar-panel">
                <div class="panel-head">
                    <h2 class="cal-month-title" id="calMonthLabel">November 2026</h2>
                    <button type="button" class="today-pill-btn" id="calTodayBtn">Today</button>
                </div>

                <div class="cal-weekdays">
                    <span>S</span>
                    <span>M</span>
                    <span>T</span>
                    <span>W</span>
                    <span>TH</span>
                    <span>F</span>
                    <span>S</span>
                </div>

                <div class="cal-days-grid" id="calDaysGrid"></div>

                <div class="cal-status-box" id="calStatusBox">
                    <span class="cal-status-eyebrow" id="calStatusDate">NOV 21 &bull; TODAY</span>
                    <strong class="cal-status-title" id="calStatusTitle">No classes scheduled today</strong>
                    <span class="cal-status-sub" id="calStatusSub">Next: Wednesday, Nov 25 at Rawis Hub</span>
                </div>
            </article>

        </section>

    </main>
</div>

<script>
    const calendarEvents = <?php echo json_encode($calendar_events, JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php include __DIR__ . '/includes/logout_modal.php'; ?>
<script src="assets/js/admin.js?v=2"></script>

</body>
</html>