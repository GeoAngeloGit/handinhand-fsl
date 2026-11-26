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
            $stmt = $conn->prepare("SELECT teacher_id, first_name, last_name FROM teachers WHERE account_id = ? LIMIT 1");
            $stmt->bind_param('i', $current_account_id);
            $stmt->execute();
            $tch = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $res = $conn->query("SELECT teacher_id, first_name, last_name FROM teachers ORDER BY teacher_id ASC LIMIT 1");
            $tch = $res ? $res->fetch_assoc() : null;
        }

        if (!empty($tch)) {
            $teacher_id         = (int)$tch['teacher_id'];
            $teacher_first_name = trim((string)($tch['first_name'] ?? 'Maria')) ?: 'Maria';
            $teacher_full_name  = 'Teacher ' . $teacher_first_name;
        }
    } catch (Throwable $e) {
        // Keep defaults
    }
}

$name_parts = preg_split('/\s+/', trim($teacher_full_name));
$initials   = strtoupper(
    substr($name_parts[0] ?? 'T', 0, 1) .
    substr($name_parts[1] ?? 'M', 0, 1)
);

// ==================== FETCH LIVE CLASSES & UPCOMING TIMETABLE ====================
$all_classes       = [];
$upcoming_schedule = [];
$thumb_themes      = ['thumb-maroon', 'thumb-blue', 'thumb-green', 'thumb-amber', 'thumb-teal', 'thumb-rose'];

if (isset($conn) && $conn instanceof mysqli) {
    try {
        // Only classes assigned to this teacher (admin links them via class_teacher)
        $sql_cls = "
            SELECT c.*,
                   c.class_title AS class_name,
                   (SELECT cs.venue FROM class_schedule cs
                     WHERE cs.class_id = c.class_id
                     ORDER BY cs.session_date ASC, cs.start_time ASC
                     LIMIT 1) AS location
            FROM class c
            JOIN class_teacher mine ON mine.class_id = c.class_id
            WHERE mine.teacher_id = " . (int)$teacher_id . "
            ORDER BY c.class_id ASC
        ";
        if ($res = $conn->query($sql_cls)) {
            $idx = 0;
            while ($c = $res->fetch_assoc()) {
                $cid = (int)$c['class_id'];

                // Normalize status: Active, Completed, Re-scheduled
                $raw_status = strtolower(trim((string)($c['status'] ?? 'active')));
                if (str_contains($raw_status, 'complet')) {
                    $status_label = 'Completed';
                } elseif (str_contains($raw_status, 'resched') || str_contains($raw_status, 're-sched')) {
                    $status_label = 'Re-scheduled';
                } else {
                    $status_label = 'Active';
                }

                // Fetch assigned teachers from the class_teacher pivot table
                $instructors = [];
                try {
                    $q_ct = "
                        SELECT t.teacher_id, t.first_name, t.last_name
                        FROM class_teacher ct
                        JOIN teachers t ON t.teacher_id = ct.teacher_id
                        WHERE ct.class_id = {$cid}
                        ORDER BY t.teacher_id ASC
                    ";
                    if ($rct = $conn->query($q_ct)) {
                        while ($tr = $rct->fetch_assoc()) {
                            $tf = trim((string)($tr['first_name'] ?? 'Teacher'));
                            $tl = trim((string)($tr['last_name'] ?? ''));
                            $instructors[] = [
                                'initials' => strtoupper(substr($tf, 0, 1) . substr($tl ?: $tf, 1, 1)),
                                'label'    => 'Teacher ' . $tf
                            ];
                        }
                    }
                } catch (Throwable $ignore) {}

                if (empty($instructors) && !empty($c['teacher_id'])) {
                    $tid_single = (int)$c['teacher_id'];
                    if ($rt = $conn->query("SELECT first_name, last_name FROM teachers WHERE teacher_id = {$tid_single} LIMIT 1")) {
                        if ($tr = $rt->fetch_assoc()) {
                            $tf = trim((string)($tr['first_name'] ?? 'Teacher'));
                            $tl = trim((string)($tr['last_name'] ?? ''));
                            $instructors[] = [
                                'initials' => strtoupper(substr($tf, 0, 1) . substr($tl ?: $tf, 1, 1)),
                                'label'    => 'Teacher ' . $tf
                            ];
                        }
                    }
                }

                if (empty($instructors)) {
                    $instructors[] = [
                        'initials' => strtoupper(substr($teacher_first_name, 0, 2)),
                        'label'    => $teacher_full_name
                    ];
                }

                // Fetch enrolled learners for avatar bubbles & roster count
                $learners_enrolled = [];
                try {
                    $q_enr = "
                        SELECT l.learner_id, l.first_name, l.last_name
                        FROM class_enrollments e
                        JOIN learners l ON l.learner_id = e.learner_id
                        WHERE e.class_id = {$cid}
                          AND e.status = 'enrolled'
                        ORDER BY l.learner_id ASC
                    ";
                    if ($ren = $conn->query($q_enr)) {
                        while ($lr = $ren->fetch_assoc()) {
                            $lf = trim((string)($lr['first_name'] ?? 'L'));
                            $ll = trim((string)($lr['last_name'] ?? 'N'));
                            $learners_enrolled[] = [
                                'initials' => strtoupper(substr($lf, 0, 1) . substr($ll, 0, 1)),
                                'name'     => trim($ll . ', ' . $lf)
                            ];
                        }
                    }
                } catch (Throwable $ignore) {}

                // Fetch scheduled timetable slots for this class
                $class_slots = [];
                $sort_date   = $c['created_at'] ?? sprintf('2026-11-%02d', min(28, 20 + $cid));
                try {
                    $q_slots = "
                        SELECT schedule_id, session_date, start_time
                        FROM class_schedule
                        WHERE class_id = {$cid}
                        ORDER BY session_date ASC, start_time ASC
                    ";
                    if ($rs = $conn->query($q_slots)) {
                        while ($sr = $rs->fetch_assoc()) {
                            $sid = (int)($sr['schedule_id'] ?? 0);
                            $dt  = (string)($sr['session_date'] ?? '');
                            $tm  = !empty($sr['start_time']) ? date('h:i A', strtotime((string)$sr['start_time'])) : '09:00 AM';
                            if (empty($class_slots) && $dt !== '') {
                                $sort_date = $dt;
                            }
                            $class_slots[] = sprintf('SCH-%03d: %s • %s', $sid, $dt, $tm);
                        }
                    }
                } catch (Throwable $ignore) {}

                $all_classes[] = [
                    'raw_id'       => $cid,
                    'code'         => sprintf('CLS-%03d', $cid),
                    'title'        => $c['class_name'] ?? ($c['title'] ?? 'FSL Class #' . $cid),
                    'description'  => $c['description'] ?? 'Core Filipino Sign Language expressions, visual communication structures, and community dialogue.',
                    'location'     => $c['location'] ?? ($c['venue'] ?? 'Legazpi City (Brgy. Rawis)'),
                    'capacity'     => (int)($c['max_capacity'] ?? ($c['capacity'] ?? 15)),
                    'status'       => $status_label,
                    'theme'        => $thumb_themes[$idx % count($thumb_themes)],
                    'instructors'  => $instructors,
                    'learners'     => $learners_enrolled,
                    'learner_cnt'  => count($learners_enrolled),
                    'slots'        => $class_slots,
                    'sort_date'    => $sort_date,
                ];
                $idx++;
            }
        }
    } catch (Throwable $e) {
        $all_classes = [];
    }

    // Fetch Upcoming Timetable Queue
    try {
        $sql_sched = "
            SELECT 
                s.*,
                c.class_id,
                c.class_title AS class_name,
                s.venue AS location
            FROM class_schedule s
            JOIN class c ON c.class_id = s.class_id
            JOIN class_teacher mine ON mine.class_id = c.class_id
            WHERE mine.teacher_id = " . (int)$teacher_id . "
            ORDER BY s.session_date ASC, s.start_time ASC
            LIMIT 6
        ";
        if ($res = $conn->query($sql_sched)) {
            while ($s = $res->fetch_assoc()) {
                $sid       = (int)($s['schedule_id'] ?? $s['id'] ?? 0);
                $cid       = (int)($s['class_id'] ?? 0);
                $loc_full  = (string)($s['location'] ?? 'Legazpi City');
                $loc_short = trim(explode('(', $loc_full)[0]);

                $upcoming_schedule[] = [
                    'sched_code' => sprintf('SCH-%03d', $sid),
                    'class_code' => sprintf('CLS-%03d', $cid),
                    'venue'      => $loc_short ?: 'Legazpi City',
                    'title'      => $s['class_name'] ?? 'FSL Session',
                    'date'       => $s['session_date'] ?? date('Y-m-d'),
                    'time'       => !empty($s['start_time']) ? date('h:i A', strtotime((string)$s['start_time'])) : '09:00 AM',
                ];
            }
        }
    } catch (Throwable $e) {
        $upcoming_schedule = [];
    }
}

$cnt_all        = count($all_classes);
$cnt_active     = count(array_filter($all_classes, fn($c) => $c['status'] === 'Active'));
$cnt_completed  = count(array_filter($all_classes, fn($c) => $c['status'] === 'Completed'));
$cnt_resched    = count(array_filter($all_classes, fn($c) => $c['status'] === 'Re-scheduled'));
$active_classes = array_values(array_filter($all_classes, fn($c) => $c['status'] === 'Active'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/teacher.css?v=3">
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
                    <a href="teacher_dashboard.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="teacher_classes.php" class="nav-item active">
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
                <span class="portal-eyebrow">TEACHER PORTAL &bull; CLASSES</span>
                <h1 class="page-title">My <em>Classes</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- Filter & Sort Bar -->
        <section class="classes-toolbar-card">
            <div class="classes-filter-left">
                <span class="toolbar-label">FILTER CLASSES:</span>
                <div class="segmented-filter-pills" role="tablist">
                    <button type="button" class="cls-filter-pill active" data-status="ALL">All (<?php echo $cnt_all; ?>)</button>
                    <button type="button" class="cls-filter-pill" data-status="Active">Active (<?php echo $cnt_active; ?>)</button>
                    <button type="button" class="cls-filter-pill" data-status="Completed">Completed (<?php echo $cnt_completed; ?>)</button>
                    <button type="button" class="cls-filter-pill" data-status="Re-scheduled">Re-scheduled (<?php echo $cnt_resched; ?>)</button>
                </div>
            </div>

            <div class="classes-filter-right">
                <span class="toolbar-label">SORT:</span>
                <div class="sort-toggle-group">
                    <button type="button" class="sort-dir-btn active" data-sort="earliest">&darr; Earliest</button>
                    <button type="button" class="sort-dir-btn" data-sort="latest">&uarr; Latest</button>
                </div>

                <div class="view-mode-toggle" role="group" aria-label="Layout View">
                    <button type="button" class="view-btn active" data-view="grid" title="Grid View">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                    </button>
                    <button type="button" class="view-btn" data-view="list" title="List View">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <circle cx="3.5" cy="6" r="1"></circle>
                            <circle cx="3.5" cy="12" r="1"></circle>
                            <circle cx="3.5" cy="18" r="1"></circle>
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- ==================== UPPER SPLIT: ACTIVE CLASSES + UPCOMING TIMETABLE ==================== -->
        <section class="classes-upper-split" id="activeClassesSection">
            <!-- Left Column: Active Classes -->
            <div class="active-classes-col">
                <div class="section-heading-row">
                    <div class="section-heading-left">
                        <h2 class="section-serif-title">Active Classes</h2>
                        <span class="ongoing-pill"><?php echo $cnt_active; ?> Ongoing</span>
                    </div>
                    <span class="section-hint-text">Click any class card for details</span>
                </div>

                <div class="active-cards-grid" id="activeCardsGrid">
                    <?php if (empty($active_classes)): ?>
                        <div class="empty-panel-note" style="grid-column: 1 / -1;">No active classes assigned right now.</div>
                    <?php else: ?>
                        <?php foreach ($active_classes as $cls): ?>
                            <?php
                                $modal_json = htmlspecialchars(json_encode([
                                    'raw_id'      => $cls['raw_id'],
                                    'code'        => $cls['code'],
                                    'title'       => $cls['title'],
                                    'status'      => $cls['status'],
                                    'theme'       => $cls['theme'],
                                    'location'    => $cls['location'],
                                    'description' => $cls['description'],
                                    'capacity'    => $cls['capacity'],
                                    'enrolled'    => $cls['learner_cnt'],
                                    'instructors' => $cls['instructors'],
                                    'slots'       => $cls['slots'],
                                ]), ENT_QUOTES, 'UTF-8');
                            ?>
                            <article class="class-feature-card"
                                     data-status="<?php echo htmlspecialchars($cls['status']); ?>"
                                     data-date="<?php echo htmlspecialchars($cls['sort_date']); ?>"
                                     data-code="<?php echo htmlspecialchars($cls['code']); ?>"
                                     data-modal="<?php echo $modal_json; ?>">

                                <div class="class-card-top">
                                    <div class="class-art-thumb <?php echo htmlspecialchars($cls['theme']); ?>">
                                        <span class="art-sun"></span>
                                        <span class="art-hill"></span>
                                    </div>
                                    <div class="class-top-meta">
                                        <span class="class-code-loc"><?php echo htmlspecialchars($cls['code']); ?> &bull; <?php echo htmlspecialchars($cls['location']); ?></span>
                                        <h3 class="class-card-title"><?php echo htmlspecialchars($cls['title']); ?></h3>
                                    </div>
                                    <span class="class-status-pill status-active">Active</span>
                                </div>

                                <p class="class-card-desc"><?php echo htmlspecialchars($cls['description']); ?></p>

                                <div class="class-capacity-badge">Max Capacity: <?php echo (int)$cls['capacity']; ?> Learners</div>

                                <div class="teacher-chips-row">
                                    <?php foreach ($cls['instructors'] as $idx_t => $inst): ?>
                                        <span class="teacher-chip">
                                            <span class="chip-avatar chip-color-<?php echo $idx_t % 4; ?>"><?php echo htmlspecialchars($inst['initials']); ?></span>
                                            <span><?php echo htmlspecialchars($inst['label']); ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>

                                <div class="class-card-footer">
                                    <div class="learner-stack-wrap">
                                        <div class="avatar-overlap-group">
                                            <?php
                                                $visible_lrns = array_slice($cls['learners'], 0, 3);
                                                $extra_lrns   = max(0, $cls['learner_cnt'] - 3);
                                            ?>
                                            <?php if (empty($visible_lrns)): ?>
                                                <span class="mini-lrn-avatar av-0">0</span>
                                            <?php else: ?>
                                                <?php foreach ($visible_lrns as $idx_l => $lr): ?>
                                                    <span class="mini-lrn-avatar av-<?php echo $idx_l % 3; ?>"><?php echo htmlspecialchars($lr['initials']); ?></span>
                                                <?php endforeach; ?>
                                                <?php if ($extra_lrns > 0): ?>
                                                    <span class="mini-lrn-avatar av-more">+<?php echo $extra_lrns; ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="learner-count-label"><?php echo (int)$cls['learner_cnt']; ?> Learners</span>
                                    </div>

                                    <a href="teacher_learners.php?class_id=<?php echo (int)$cls['raw_id']; ?>" class="roster-pill-link" onclick="event.stopPropagation();">
                                        Roster &rarr;
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Upcoming Timetable -->
            <aside class="upcoming-timetable-col">
                <div class="section-heading-row">
                    <h2 class="section-serif-title">Upcoming Timetable</h2>
                    <span class="section-hint-text" id="timetableSortHint">Earliest First</span>
                </div>

                <div class="timetable-cards-stack" id="timetableCardsStack">
                    <?php if (empty($upcoming_schedule)): ?>
                        <div class="empty-panel-note">No upcoming scheduled sessions found.</div>
                    <?php else: ?>
                        <?php foreach ($upcoming_schedule as $sch): ?>
                            <div class="timetable-item-card" data-date="<?php echo htmlspecialchars($sch['date']); ?>">
                                <div class="tt-top-row">
                                    <div class="tt-code-pills">
                                        <span class="tt-sch-pill"><?php echo htmlspecialchars($sch['sched_code']); ?></span>
                                        <span class="tt-cls-pill"><?php echo htmlspecialchars($sch['class_code']); ?></span>
                                    </div>
                                    <span class="tt-venue-text"><?php echo htmlspecialchars($sch['venue']); ?></span>
                                </div>

                                <div class="tt-session-title"><?php echo htmlspecialchars($sch['title']); ?></div>

                                <div class="tt-bottom-meta">
                                    <span>&#128197; <?php echo htmlspecialchars($sch['date']); ?></span>
                                    <span>&#128337; <?php echo htmlspecialchars($sch['time']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>
        </section>

        <!-- ==================== LOWER SECTION: ALL CLASSES DIRECTORY ==================== -->
        <section class="all-classes-section">
            <div class="all-classes-header">
                <div>
                    <h2 class="section-serif-title">All Classes</h2>
                    <p class="section-sub-caption">Complete directory of active, completed, and re-scheduled Albay classes under your instruction.</p>
                </div>
                <span class="section-hint-text" id="allClassesSummary">Sorted by Earliest &bull; <?php echo $cnt_all; ?> Total</span>
            </div>

            <div class="all-classes-grid" id="allClassesGrid">
                <?php if (empty($all_classes)): ?>
                    <div class="empty-panel-note" style="grid-column: 1 / -1;">No classes found in the database.</div>
                <?php else: ?>
                    <?php foreach ($all_classes as $cls): ?>
                        <?php
                            $st_class = match ($cls['status']) {
                                'Completed'    => 'status-completed',
                                'Re-scheduled' => 'status-rescheduled',
                                default        => 'status-active',
                            };
                            $modal_json = htmlspecialchars(json_encode([
                                'raw_id'      => $cls['raw_id'],
                                'code'        => $cls['code'],
                                'title'       => $cls['title'],
                                'status'      => $cls['status'],
                                'theme'       => $cls['theme'],
                                'location'    => $cls['location'],
                                'description' => $cls['description'],
                                'capacity'    => $cls['capacity'],
                                'enrolled'    => $cls['learner_cnt'],
                                'instructors' => $cls['instructors'],
                                'slots'       => $cls['slots'],
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <article class="all-class-card"
                                 data-status="<?php echo htmlspecialchars($cls['status']); ?>"
                                 data-date="<?php echo htmlspecialchars($cls['sort_date']); ?>"
                                 data-code="<?php echo htmlspecialchars($cls['code']); ?>"
                                 data-modal="<?php echo $modal_json; ?>">

                            <div class="ac-top-row">
                                <div class="class-art-thumb <?php echo htmlspecialchars($cls['theme']); ?>">
                                    <span class="art-sun"></span>
                                    <span class="art-hill"></span>
                                </div>
                                <div class="ac-code-status">
                                    <span class="ac-code-text"><?php echo htmlspecialchars($cls['code']); ?></span>
                                    <span class="class-status-pill <?php echo $st_class; ?>"><?php echo htmlspecialchars($cls['status']); ?></span>
                                </div>
                            </div>

                            <h3 class="ac-card-title"><?php echo htmlspecialchars($cls['title']); ?></h3>
                            <p class="ac-card-desc"><?php echo htmlspecialchars($cls['description']); ?></p>

                            <div class="class-capacity-badge">Max Capacity: <?php echo (int)$cls['capacity']; ?></div>

                            <div class="teacher-chips-row">
                                <?php foreach ($cls['instructors'] as $idx_t => $inst): ?>
                                    <span class="teacher-chip">
                                        <span class="chip-avatar chip-color-<?php echo $idx_t % 4; ?>"><?php echo htmlspecialchars($inst['initials']); ?></span>
                                        <span><?php echo htmlspecialchars($inst['label']); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <div class="ac-card-footer">
                                <div class="avatar-overlap-group">
                                    <?php
                                        $visible_lrns = array_slice($cls['learners'], 0, 3);
                                        $extra_lrns   = max(0, $cls['learner_cnt'] - 3);
                                    ?>
                                    <?php if (empty($visible_lrns)): ?>
                                        <span class="mini-lrn-avatar av-0">0</span>
                                    <?php else: ?>
                                        <?php foreach ($visible_lrns as $idx_l => $lr): ?>
                                            <span class="mini-lrn-avatar av-<?php echo $idx_l % 3; ?>"><?php echo htmlspecialchars($lr['initials']); ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($extra_lrns > 0): ?>
                                            <span class="mini-lrn-avatar av-more">+<?php echo $extra_lrns; ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <span class="learner-count-label"><?php echo (int)$cls['learner_cnt']; ?> Learners</span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

    </main>
</div>

<!-- ==================== CLASS DETAILS MODAL ==================== -->
<div class="cls-modal-backdrop" id="classDetailsModal" aria-hidden="true">
    <div class="cls-modal-card" role="dialog" aria-modal="true" aria-labelledby="clsModalTitle">
        <div class="cls-modal-header">
            <div class="cls-modal-head-left">
                <div class="class-art-thumb cls-modal-thumb thumb-maroon" id="clsModalThumb">
                    <span class="art-sun"></span>
                    <span class="art-hill"></span>
                </div>
                <div>
                    <div class="cls-modal-eyebrow-row">
                        <span id="clsModalCode"></span>
                        <span class="class-status-pill status-active" id="clsModalStatus"></span>
                    </div>
                    <h2 class="cls-modal-title" id="clsModalTitle"></h2>
                </div>
            </div>
            <button type="button" class="cls-modal-close" id="closeClassModalBtn" aria-label="Close modal">&times;</button>
        </div>

        <div class="cls-overview-box">
            <span class="cls-box-eyebrow">COURSE OVERVIEW</span>
            <p class="cls-modal-desc" id="clsModalDesc"></p>
        </div>

        <div class="cls-detail-rows">
            <div class="cls-detail-row">
                <span class="cls-row-label">Assigned Teachers</span>
                <div class="cls-row-chips" id="clsModalTeachers"></div>
            </div>

            <div class="cls-detail-row">
                <span class="cls-row-label">Class Venue</span>
                <strong class="cls-row-val" id="clsModalLoc"></strong>
            </div>

            <div class="cls-detail-row">
                <span class="cls-row-label">Enrollment Capacity</span>
                <strong class="cls-row-val" id="clsModalCap"></strong>
            </div>

            <div class="cls-detail-row">
                <span class="cls-row-label">Upcoming Sessions</span>
                <strong class="cls-row-val" id="clsModalSessionCount"></strong>
            </div>
        </div>

        <div class="cls-slots-section">
            <span class="cls-box-eyebrow">SCHEDULED TIMETABLE SLOTS</span>
            <div class="cls-slots-row" id="clsModalSlots"></div>
        </div>

        <div class="cls-modal-footer">
            <button type="button" class="btn-cls-secondary" id="cancelClassModalBtn">Close</button>
            <a href="teacher_learners.php" class="btn-cls-primary" id="clsModalRosterLink">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Open Learner Lists &rarr;</span>
            </a>
        </div>
    </div>
</div>

<script src="assets/js/teacher.js?v=1"></script>


</body>
</html>