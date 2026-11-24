<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';

if (!defined('ROLE_TEACHER')) {
    define('ROLE_TEACHER', 2);
}

if (!empty($_SESSION['logged_in']) && function_exists('require_login')) {
    require_login([ROLE_TEACHER]);
}

// ==================== RESOLVE ACTIVE TEACHER PROFILE ====================
$current_account_id = (int)($_SESSION['account_id'] ?? 0);
$teacher_id         = 0;
$teacher_first_name = 'Maria';
$teacher_full_name  = 'Teacher Maria';
$teacher_location   = 'Legazpi City';

if (isset($conn) && $conn instanceof mysqli) {
    try {
        if ($current_account_id > 0) {
            $stmt = $conn->prepare("SELECT teacher_id, first_name, last_name, barangay_id FROM teachers WHERE account_id = ? LIMIT 1");
            $stmt->bind_param('i', $current_account_id);
            $stmt->execute();
            $tch = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $res = $conn->query("SELECT teacher_id, first_name, last_name, barangay_id FROM teachers ORDER BY teacher_id ASC LIMIT 1");
            $tch = $res ? $res->fetch_assoc() : null;
        }

        if (!empty($tch)) {
            $teacher_id         = (int)$tch['teacher_id'];
            $teacher_first_name = trim((string)($tch['first_name'] ?? 'Maria')) ?: 'Maria';
            $teacher_full_name  = 'Teacher ' . $teacher_first_name;
        }
    } catch (Throwable $e) {
        // Keep safe defaults
    }
}

$name_parts = preg_split('/\s+/', trim($teacher_full_name));
$initials   = strtoupper(
    substr($name_parts[0] ?? 'T', 0, 1) .
    substr($name_parts[1] ?? 'M', 0, 1)
);

// ==================== FETCH LIVE METRICS & LISTS ====================
$active_classes_count   = 0;
$total_assigned_classes = 0;
$enrolled_students_cnt  = 0;
$documented_reports_cnt = 0;

$upcoming_classes = [];
$assigned_classes = [];

// Evaluation breakdown counters
$eval_satisfactory = 0;
$eval_developing   = 0;
$eval_needs_support = 0;

if (isset($conn) && $conn instanceof mysqli) {
    $teacher_filter_sql = $teacher_id > 0 ? " WHERE c.teacher_id = {$teacher_id} " : "";

    // 1. Assigned Classes & KPI counts
    try {
        $sql_classes = "
            SELECT 
                c.*
            FROM classes c
            {$teacher_filter_sql}
            ORDER BY c.class_id ASC
        ";
        if ($res = $conn->query($sql_classes)) {
            while ($c = $res->fetch_assoc()) {
                $cid    = (int)$c['class_id'];
                $status = strtolower((string)($c['status'] ?? 'active'));
                $total_assigned_classes++;
                if ($status === 'active' || $status === 'ongoing') {
                    $active_classes_count++;
                }

                $enrolled_in_class = 0;
                try {
                    if ($ec = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE class_id = {$cid}")) {
                        $enrolled_in_class = (int)($ec->fetch_assoc()['cnt'] ?? 0);
                    }
                } catch (Throwable $ignore) {}

                $assigned_classes[] = [
                    'code'     => sprintf('CLS-%03d', $cid),
                    'title'    => $c['class_name'] ?? ($c['title'] ?? 'FSL Class #' . $cid),
                    'location' => $c['location'] ?? ($c['venue'] ?? 'Legazpi City (Brgy. Rawis)'),
                    'enrolled' => $enrolled_in_class,
                    'capacity' => (int)($c['max_capacity'] ?? ($c['capacity'] ?? 15)),
                ];
            }
        }
    } catch (Throwable $e) {
        // Table may not be populated yet
    }

    // 2. Distinct Enrolled Students across Teacher's Classes
    try {
        $sql_students = $teacher_id > 0
            ? "SELECT COUNT(DISTINCT e.learner_id) AS cnt FROM enrollments e JOIN classes c ON c.class_id = e.class_id WHERE c.teacher_id = {$teacher_id}"
            : "SELECT COUNT(DISTINCT learner_id) AS cnt FROM enrollments";
        if ($res = $conn->query($sql_students)) {
            $enrolled_students_cnt = (int)($res->fetch_assoc()['cnt'] ?? 0);
        }
    } catch (Throwable $e) {
        $enrolled_students_cnt = 0;
    }

    // 3. Documented Session Reports Count
    try {
        $sql_reports = $teacher_id > 0
            ? "SELECT COUNT(*) AS cnt FROM session_reports WHERE teacher_id = {$teacher_id} AND LOWER(COALESCE(status, 'submitted')) = 'submitted'"
            : "SELECT COUNT(*) AS cnt FROM session_reports WHERE LOWER(COALESCE(status, 'submitted')) = 'submitted'";
        if ($res = $conn->query($sql_reports)) {
            $documented_reports_cnt = (int)($res->fetch_assoc()['cnt'] ?? 0);
        }
    } catch (Throwable $e) {
        $documented_reports_cnt = 0;
    }

    // 4. Upcoming Timetable Queue (class_schedules)
    try {
        $where_sched = $teacher_id > 0 ? " WHERE c.teacher_id = {$teacher_id} " : "";
        $sql_sched = "
            SELECT 
                s.*,
                c.class_id,
                c.class_name,
                c.location
            FROM class_schedules s
            JOIN classes c ON c.class_id = s.class_id
            {$where_sched}
            ORDER BY s.session_date ASC, s.start_time ASC
            LIMIT 4
        ";
        if ($res = $conn->query($sql_sched)) {
            while ($s = $res->fetch_assoc()) {
                $sid  = (int)($s['schedule_id'] ?? $s['id'] ?? 0);
                $cid  = (int)($s['class_id'] ?? 0);
                $time_fmt = !empty($s['start_time']) ? date('h:i A', strtotime((string)$s['start_time'])) : '09:00 AM';
                $loc_txt  = $s['location'] ?? 'Legazpi City (Brgy. Rawis)';

                $upcoming_classes[] = [
                    'sched_code' => sprintf('SCH-%03d', $sid),
                    'class_code' => sprintf('CLS-%03d', $cid),
                    'date'       => $s['session_date'] ?? date('Y-m-d'),
                    'title'      => $s['class_name'] ?? 'FSL Session',
                    'meta'       => $time_fmt . ' • ' . $loc_txt,
                ];
            }
        }
    } catch (Throwable $e) {
        $upcoming_classes = [];
    }

    // 5. Learner Ratings Breakdown (if evaluations/attendance ratings exist)
    try {
        $sql_eval = "
            SELECT LOWER(COALESCE(rating, performance_rating, '')) AS r_label, COUNT(*) AS cnt
            FROM session_attendance
            GROUP BY r_label
        ";
        if ($res = $conn->query($sql_eval)) {
            while ($row = $res->fetch_assoc()) {
                $lbl = $row['r_label'];
                $cnt = (int)$row['cnt'];
                if (str_contains($lbl, 'satisfactory') || str_contains($lbl, 'proficient')) {
                    $eval_satisfactory += $cnt;
                } elseif (str_contains($lbl, 'develop')) {
                    $eval_developing += $cnt;
                } elseif (str_contains($lbl, 'support') || str_contains($lbl, 'need')) {
                    $eval_needs_support += $cnt;
                }
            }
        }
    } catch (Throwable $e) {
        // Fallback handled below
    }
}

$total_evaluated = $eval_satisfactory + $eval_developing + $eval_needs_support;
$sat_pct = $total_evaluated > 0 ? (int)round(($eval_satisfactory / $total_evaluated) * 100) : 0;
$dev_pct = $total_evaluated > 0 ? (int)round(($eval_developing / $total_evaluated) * 100) : 0;
$sup_pct = $total_evaluated > 0 ? max(0, 100 - $sat_pct - $dev_pct) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/teacher.css?v=1">
</head>
<body>

<div class="teacher-layout">

    <!-- ==================== LEFT SIDEBAR ==================== -->
    <aside class="sidebar" id="teacherSidebar">
        <div class="sidebar-top">
            <div class="brand-header">
                <div class="brand-row">
                    <a href="teacher_dashboard.php" class="brand-title">HandInHand</a>
                    <span class="role-pill">TEACHER</span>
                </div>
                <span class="brand-region">Albay Province &bull; Bicol Region</span>
            </div>

            <div class="menu-section">
                <span class="menu-label">MAIN MENU</span>
                <nav class="sidebar-nav" aria-label="Teacher Navigation">
                    <a href="teacher_dashboard.php" class="nav-item active">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="teacher_classes.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                        <span>Classes</span>
                    </a>

                    <a href="teacher_learners.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Learner Lists</span>
                    </a>

                    <a href="teacher_reports.php" class="nav-item">
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

        <!-- Bottom Teacher Profile Card -->
        <div class="sidebar-footer">
            <div class="profile-card">
                <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div class="profile-info">
                    <strong><?php echo htmlspecialchars($teacher_full_name); ?></strong>
                    <span><?php echo htmlspecialchars($teacher_location); ?> &bull; <a href="actions/logout.php" class="logout-link">Log out</a></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT AREA ==================== -->
    <main class="main-content">

        <!-- Page Header -->
        <header class="page-header">
            <div class="header-titles">
                <span class="portal-eyebrow">TEACHER PORTAL &bull; OVERVIEW</span>
                <h1 class="page-title">Teacher <em>Dashboard</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- Welcome Banner -->
        <section class="welcome-banner">
            <h2>Welcome, <em><?php echo htmlspecialchars($teacher_full_name); ?></em></h2>
            <p>Manage your Filipino Sign Language classes across Albay learning centers, review your upcoming schedule, and document sessions with photo-verified reports.</p>
        </section>

        <!-- 3 KPI Summary Cards -->
        <section class="kpi-grid-3">
            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">CLASSES</span>
                    <span class="kpi-badge badge-active">ACTIVE</span>
                </div>
                <div class="kpi-value"><?php echo number_format($active_classes_count); ?></div>
                <p class="kpi-sub"><?php echo number_format($total_assigned_classes); ?> total assigned classes</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">STUDENTS</span>
                    <span class="kpi-badge badge-enrolled">ENROLLED</span>
                </div>
                <div class="kpi-value"><?php echo number_format($enrolled_students_cnt); ?></div>
                <p class="kpi-sub">Across Albay Province classes</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">DOCUMENTED</span>
                    <span class="kpi-badge badge-reports">REPORTS</span>
                </div>
                <div class="kpi-value"><?php echo number_format($documented_reports_cnt); ?></div>
                <p class="kpi-sub">Photo-verified session logs</p>
            </article>
        </section>

        <!-- Bottom 3-Column Panels -->
        <section class="dashboard-panels-3">

            <!-- Panel 1: Learner Ratings -->
            <article class="panel-card">
                <div class="panel-header">
                    <div>
                        <span class="panel-eyebrow">OBSERVATION METRICS</span>
                        <h2 class="panel-title">Learner Ratings</h2>
                    </div>
                </div>

                <div class="ratings-overview-box">
                    <div class="ratings-overview-top">
                        <span>EVALUATED LEARNERS</span>
                        <strong><?php echo number_format($total_evaluated); ?> Total</strong>
                    </div>
                    <div class="multi-progress-bar">
                        <div class="bar-seg-sat" style="width: <?php echo $sat_pct; ?>%;"></div>
                        <div class="bar-seg-dev" style="width: <?php echo $dev_pct; ?>%;"></div>
                        <div class="bar-seg-sup" style="width: <?php echo $sup_pct; ?>%;"></div>
                    </div>
                </div>

                <div class="rating-tiers-stack">
                    <div class="rating-tier-card">
                        <div class="tier-top-row">
                            <div class="tier-title-wrap">
                                <span class="tier-dot dot-sat"></span>
                                <div>
                                    <strong>Satisfactory</strong>
                                    <span>Meets FSL learning targets</span>
                                </div>
                            </div>
                            <span class="tier-count-pill pill-sat"><?php echo $eval_satisfactory; ?> (<?php echo $sat_pct; ?>%)</span>
                        </div>
                        <div class="single-progress-track">
                            <div class="single-progress-fill bar-seg-sat" style="width: <?php echo $sat_pct; ?>%;"></div>
                        </div>
                    </div>

                    <div class="rating-tier-card">
                        <div class="tier-top-row">
                            <div class="tier-title-wrap">
                                <span class="tier-dot dot-dev"></span>
                                <div>
                                    <strong>Developing</strong>
                                    <span>Progressing with guided drills</span>
                                </div>
                            </div>
                            <span class="tier-count-pill pill-dev"><?php echo $eval_developing; ?> (<?php echo $dev_pct; ?>%)</span>
                        </div>
                        <div class="single-progress-track">
                            <div class="single-progress-fill bar-seg-dev" style="width: <?php echo $dev_pct; ?>%;"></div>
                        </div>
                    </div>

                    <div class="rating-tier-card">
                        <div class="tier-top-row">
                            <div class="tier-title-wrap">
                                <span class="tier-dot dot-sup"></span>
                                <div>
                                    <strong>Needs Support</strong>
                                    <span>Requires 1-on-1 visual coaching</span>
                                </div>
                            </div>
                            <span class="tier-count-pill pill-sup"><?php echo $eval_needs_support; ?> (<?php echo $sup_pct; ?>%)</span>
                        </div>
                        <div class="single-progress-track">
                            <div class="single-progress-fill bar-seg-sup" style="width: <?php echo $sup_pct; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Panel 2: Upcoming Classes -->
            <article class="panel-card">
                <div class="panel-header">
                    <div>
                        <span class="panel-eyebrow">TIMETABLE QUEUE</span>
                        <h2 class="panel-title">Upcoming Classes</h2>
                    </div>
                    <a href="teacher_classes.php" class="panel-link">Full schedule &rarr;</a>
                </div>

                <div class="mini-items-stack">
                    <?php if (empty($upcoming_classes)): ?>
                        <div class="empty-panel-note">No upcoming class schedules found in the database.</div>
                    <?php else: ?>
                        <?php foreach ($upcoming_classes as $item): ?>
                            <a href="teacher_classes.php" class="mini-item-card">
                                <div class="mini-item-top">
                                    <span class="mini-code-line"><?php echo htmlspecialchars($item['sched_code'] . ' • ' . $item['class_code']); ?></span>
                                    <span class="mini-date-tag"><?php echo htmlspecialchars($item['date']); ?></span>
                                </div>
                                <div class="mini-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div class="mini-item-sub"><?php echo htmlspecialchars($item['meta']); ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <!-- Panel 3: Assigned Classes -->
            <article class="panel-card">
                <div class="panel-header">
                    <div>
                        <span class="panel-eyebrow">ACTIVE CLASSES</span>
                        <h2 class="panel-title">Assigned Classes</h2>
                    </div>
                    <a href="teacher_classes.php" class="panel-link">View all &rarr;</a>
                </div>

                <div class="mini-items-stack">
                    <?php if (empty($assigned_classes)): ?>
                        <div class="empty-panel-note">No assigned classes found in the database.</div>
                    <?php else: ?>
                        <?php foreach (array_slice($assigned_classes, 0, 4) as $cls): ?>
                            <a href="teacher_classes.php" class="mini-item-card">
                                <div class="mini-item-row">
                                    <div>
                                        <div class="mini-code-line maroon"><?php echo htmlspecialchars($cls['code']); ?></div>
                                        <div class="mini-item-title"><?php echo htmlspecialchars($cls['title']); ?></div>
                                        <div class="mini-item-sub"><?php echo htmlspecialchars($cls['location']); ?></div>
                                    </div>
                                    <span class="capacity-pill"><?php echo (int)$cls['enrolled'] . '/' . (int)$cls['capacity']; ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

        </section>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('teacherSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }
});
</script>

</body>
</html>