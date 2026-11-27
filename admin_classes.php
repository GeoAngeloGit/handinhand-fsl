<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';

if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 1);
}

if (!empty($_SESSION['logged_in']) && function_exists('require_login')) {
    require_login([ROLE_ADMIN]);
}

$current_account_id = (int)($_SESSION['account_id'] ?? 0);
$coordinator_name   = 'Albay Coordinator';
$coordinator_hub    = 'Legazpi Hub';

if (isset($conn) && $conn instanceof mysqli) {
    try {
        if ($current_account_id > 0) {
            $stmt = $conn->prepare("SELECT first_name, last_name FROM administrators WHERE account_id = ? LIMIT 1");
            $stmt->bind_param('i', $current_account_id);
            $stmt->execute();
            $adm = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $res = $conn->query("SELECT account_id, first_name, last_name FROM administrators ORDER BY admin_id ASC LIMIT 1");
            $adm = $res ? $res->fetch_assoc() : null;
        }
        if (!empty($adm)) {
            $coordinator_name = trim(($adm['first_name'] ?? '') . ' ' . ($adm['last_name'] ?? '')) ?: $coordinator_name;
        }
    } catch (Throwable $e) {
        // Keep defaults
    }
}

$name_parts = preg_split('/\s+/', trim($coordinator_name));
$initials   = strtoupper(substr($name_parts[0] ?? 'A', 0, 1) . substr($name_parts[1] ?? 'C', 0, 1));

// ==================== FETCH REFERENCE DATA FOR MODALS ====================
$barangays_list = [];
$teachers_list  = [];
$learners_list  = [];
$classes_list   = [];

if (isset($conn) && $conn instanceof mysqli) {
    // 1. Albay Barangays & Municipalities
    try {
        $res = $conn->query("
            SELECT b.barangay_id, b.barangay_name, m.municipality_name
            FROM barangays b
            JOIN municipalities m ON m.municipality_id = b.municipality_id
            ORDER BY m.municipality_name ASC, b.barangay_name ASC
        ");
        while ($r = $res->fetch_assoc()) {
            $barangays_list[] = $r;
        }
    } catch (Throwable $e) {
        error_log("Barangays fetch error: " . $e->getMessage());
    }

    // 2. Active Teachers
    try {
        $res = $conn->query("
            SELECT teacher_id, first_name, middle_name, last_name, specialization
            FROM teachers
            WHERE COALESCE(account_status, 'active') = 'active'
            ORDER BY last_name ASC, first_name ASC
        ");
        while ($r = $res->fetch_assoc()) {
            $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
            $teachers_list[] = [
                'teacher_id'     => (int)$r['teacher_id'],
                'full_name'      => trim($r['first_name'] . $mid . ' ' . $r['last_name']),
                'specialization' => $r['specialization'] ?? 'FSL Instruction'
            ];
        }
    } catch (Throwable $e) {
        error_log("Teachers fetch error: " . $e->getMessage());
    }

    // 3. Active Learners (for enrollment modal)
    try {
        $res = $conn->query("
            SELECT learner_id, first_name, middle_name, last_name, hearing_status
            FROM learners
            WHERE COALESCE(account_status, 'active') = 'active'
            ORDER BY last_name ASC, first_name ASC
        ");
        while ($r = $res->fetch_assoc()) {
            $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
            $learners_list[] = [
                'learner_id'     => (int)$r['learner_id'],
                'code'           => sprintf('LRN-%05d', (int)$r['learner_id']),
                'full_name'      => trim($r['last_name'] . ', ' . $r['first_name'] . $mid),
                'hearing_status' => $r['hearing_status'] ?? 'Unspecified'
            ];
        }
    } catch (Throwable $e) {
        error_log("Learners fetch error: " . $e->getMessage());
    }

    // 4. Classes + Schedules + Assigned Teachers + Enrolled Learners
    try {
        $sql_classes = "
            SELECT 
                c.*,
                b.barangay_name,
                m.municipality_name
            FROM classes c
            LEFT JOIN barangays b ON b.barangay_id = c.barangay_id
            LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
            ORDER BY c.class_id DESC
        ";
        $res = $conn->query($sql_classes);
        while ($c = $res->fetch_assoc()) {
            $cid = (int)$c['class_id'];

            // Fetch Schedules
            $schedules = [];
            $s_stmt = $conn->prepare("
                SELECT schedule_id, session_date, start_time, end_time, room_location
                FROM class_schedules
                WHERE class_id = ?
                ORDER BY session_date ASC, start_time ASC
            ");
            $s_stmt->bind_param('i', $cid);
            $s_stmt->execute();
            $s_res = $s_stmt->get_result();
            while ($sr = $s_res->fetch_assoc()) {
                $schedules[] = [
                    'schedule_id'   => (int)$sr['schedule_id'],
                    'session_date'  => $sr['session_date'],
                    'date_label'    => date('D, M j, Y', strtotime($sr['session_date'])),
                    'start_time'    => $sr['start_time'],
                    'end_time'      => $sr['end_time'],
                    'time_label'    => date('g:i A', strtotime($sr['start_time'])) . (!empty($sr['end_time']) ? ' – ' . date('g:i A', strtotime($sr['end_time'])) : ''),
                    'room_location' => $sr['room_location'] ?? ''
                ];
            }
            $s_stmt->close();

            // Fetch Assigned Teachers
            $assigned_teachers = [];
            $t_stmt = $conn->prepare("
                SELECT ct.teacher_id, ct.role, t.first_name, t.middle_name, t.last_name, t.specialization
                FROM class_teacher ct
                JOIN teachers t ON t.teacher_id = ct.teacher_id
                WHERE ct.class_id = ?
                ORDER BY FIELD(ct.role, 'Lead Instructor', 'Lead', 'Co-teacher', 'Assistant', 'Substitute'), t.last_name ASC
            ");
            if ($t_stmt) {
                $t_stmt->bind_param('i', $cid);
                $t_stmt->execute();
                $t_res = $t_stmt->get_result();
                while ($tr = $t_res->fetch_assoc()) {
                    $mid = !empty($tr['middle_name']) ? ' ' . strtoupper(substr(trim((string)$tr['middle_name']), 0, 1)) . '.' : '';
                    $assigned_teachers[] = [
                        'teacher_id'     => (int)$tr['teacher_id'],
                        'code'           => sprintf('TCH-%05d', (int)$tr['teacher_id']),
                        'full_name'      => trim($tr['first_name'] . $mid . ' ' . $tr['last_name']),
                        'role'           => $tr['role'] ?? 'Lead Instructor',
                        'specialization' => $tr['specialization'] ?? ''
                    ];
                }
                $t_stmt->close();
            }

            // Fetch Enrolled Learners
            $enrolled_learners = [];
            $e_stmt = $conn->prepare("
                SELECT ce.enrollment_id, ce.learner_id, ce.status, ce.enrolled_at,
                       l.first_name, l.middle_name, l.last_name, l.hearing_status
                FROM class_enrollments ce
                JOIN learners l ON l.learner_id = ce.learner_id
                WHERE ce.class_id = ?
                ORDER BY l.last_name ASC, l.first_name ASC
            ");
            if ($e_stmt) {
                $e_stmt->bind_param('i', $cid);
                $e_stmt->execute();
                $e_res = $e_stmt->get_result();
                while ($er = $e_res->fetch_assoc()) {
                    $mid = !empty($er['middle_name']) ? ' ' . strtoupper(substr(trim((string)$er['middle_name']), 0, 1)) . '.' : '';
                    $enrolled_learners[] = [
                        'enrollment_id'  => (int)$er['enrollment_id'],
                        'learner_id'     => (int)$er['learner_id'],
                        'code'           => sprintf('LRN-%05d', (int)$er['learner_id']),
                        'full_name'      => trim($er['last_name'] . ', ' . $er['first_name'] . $mid),
                        'hearing_status' => $er['hearing_status'] ?? 'Unspecified',
                        'status'         => ucfirst(strtolower((string)($er['status'] ?? 'enrolled')))
                    ];
                }
                $e_stmt->close();
            }

            $brgy = $c['barangay_name'] ?? '';
            $muni = $c['municipality_name'] ?? '';
            $hub_label = $brgy ? "Brgy. {$brgy}" . ($muni ? ", {$muni}" : '') : ($muni ?: 'Albay Hub');

            $raw_status = strtolower((string)($c['status'] ?? 'open'));
            $status_label = match ($raw_status) {
                'ongoing'   => 'Ongoing',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                default     => 'Open',
            };

            $lead_name = 'Unassigned';
            foreach ($assigned_teachers as $at) {
                if (stripos($at['role'], 'lead') !== false) {
                    $lead_name = $at['full_name'];
                    break;
                }
            }
            if ($lead_name === 'Unassigned' && !empty($assigned_teachers)) {
                $lead_name = $assigned_teachers[0]['full_name'];
            }

            $classes_list[] = [
                'class_id'          => $cid,
                'code'              => sprintf('CLS-%04d', $cid),
                'title'             => $c['class_title'] ?? 'Untitled Class',
                'description'       => $c['description'] ?? '',
                'skill_level'       => $c['skill_level'] ?? 'Beginner',
                'barangay_id'       => (int)($c['barangay_id'] ?? 0),
                'hub_label'         => $hub_label,
                'max_capacity'      => (int)($c['max_capacity'] ?? 20),
                'enrolled_count'    => count($enrolled_learners),
                'status'            => $status_label,
                'lead_teacher'      => $lead_name,
                'schedules'         => $schedules,
                'assigned_teachers' => $assigned_teachers,
                'enrolled_learners' => $enrolled_learners,
            ];
        }
    } catch (Throwable $e) {
        error_log("Classes query error: " . $e->getMessage());
    }
}

// KPI Counters
$total_classes     = count($classes_list);
$open_classes      = 0;
$ongoing_classes   = 0;
$total_enrollments = 0;

foreach ($classes_list as $cl) {
    if ($cl['status'] === 'Open') $open_classes++;
    if ($cl['status'] === 'Ongoing') $ongoing_classes++;
    $total_enrollments += $cl['enrolled_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | HandInHand FSL</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/admin.css?v=7">
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
                    <a href="admin_dashboard.php" class="nav-item">
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

                    <a href="admin_classes.php" class="nav-item active">
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
                <span class="portal-eyebrow">ADMINISTRATION PORTAL &bull; CLASS &amp; HUB SCHEDULING</span>
                <h1 class="page-title">Manage <em>Classes</em></h1>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" class="btn-new-admin" id="openCreateClassModalBtn">
                    <span>+</span> Create New Class
                </button>
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
            </div>
        </header>

        <!-- URL Feedback Banner -->
        <?php if (isset($_GET['class_created'])): ?>
            <div class="alert-banner alert-success" id="classAlertBanner">
                <span>New FSL class created and assigned successfully.</span>
                <button type="button" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php elseif (isset($_GET['class_error'])): ?>
            <div class="alert-banner alert-error" id="classAlertBanner">
                <span>
                    <?php
                    echo match ($_GET['class_error']) {
                        'csrf'       => 'Session expired. Please reload and submit again.',
                        'missing'    => 'Please fill in all required fields marked with *.',
                        'duplicate'  => 'Duplicate teacher assignment detected. Each educator can only be added once per class.',
                        'lead_count' => 'Please assign exactly one Lead Instructor per class.',
                        default      => 'Could not create class. Please check your inputs and try again.',
                    };
                    ?>
                </span>
                <button type="button" onclick="this.parentElement.remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- 4 KPI Cards -->
        <section class="kpi-grid-4">
            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL CLASSES</span>
                    <span class="mini-role-pill pill-neutral">ALBAY HUBS</span>
                </div>
                <div class="kpi-value"><?php echo number_format($total_classes); ?></div>
                <p class="kpi-sub">All scheduled &amp; archived sessions</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">OPEN FOR ENROLLMENT</span>
                    <span class="mini-role-pill pill-lrn">OPEN</span>
                </div>
                <div class="kpi-value"><?php echo number_format($open_classes); ?></div>
                <p class="kpi-sub">Accepting Albay learners</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">ONGOING CLASSES</span>
                    <span class="mini-role-pill pill-tch">ACTIVE</span>
                </div>
                <div class="kpi-value"><?php echo number_format($ongoing_classes); ?></div>
                <p class="kpi-sub">Currently in session</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">ENROLLED SEATS</span>
                    <span class="mini-role-pill pill-adm">ROSTER</span>
                </div>
                <div class="kpi-value"><?php echo number_format($total_enrollments); ?></div>
                <p class="kpi-sub">Active learner enrollments</p>
            </article>
        </section>

        <!-- Filter & Search Control Bar -->
        <section class="dir-control-card">
            <div class="dir-filter-bar">
                <div class="dir-sub-summary">
                    <strong>Albay FSL Class Registry</strong>
                    <span class="dir-active-counts" id="classCountLabel">Showing <?php echo $total_classes; ?> classes</span>
                </div>

                <div class="dir-filter-controls">
                    <div class="dir-search-box">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="classSearchInput" placeholder="Search class, hub, or teacher...">
                    </div>

                    <div class="dir-sort-group">
                        <span class="dir-sort-label">LEVEL</span>
                        <select id="classLevelFilter">
                            <option value="ALL">All Levels</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>

                    <div class="dir-status-pills" role="group" aria-label="Class Status Filter">
                        <button type="button" class="status-pill-btn active" data-status="ALL">All</button>
                        <button type="button" class="status-pill-btn" data-status="Open">Open</button>
                        <button type="button" class="status-pill-btn" data-status="Ongoing">Ongoing</button>
                        <button type="button" class="status-pill-btn" data-status="Completed">Completed</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Table Header -->
        <div class="dir-table-header cls-grid-layout">
            <span class="col-head-active">CLASS ID</span>
            <span>CLASS TITLE &amp; LEVEL</span>
            <span>BARANGAY HUB</span>
            <span>LEAD EDUCATOR</span>
            <span>NEXT SCHEDULE</span>
            <span>CAPACITY</span>
            <span class="align-right">STATUS</span>
        </div>

        <!-- Class Rows Stack -->
        <div class="dir-rows-stack" id="classesRowsStack">
            <?php if (empty($classes_list)): ?>
                <div class="empty-filter-state">
                    No FSL classes found in the database. Click <strong>+ Create New Class</strong> to schedule your first session.
                </div>
            <?php else: ?>
                <?php foreach ($classes_list as $cls): ?>
                    <?php
                        $next_sched = !empty($cls['schedules'])
                            ? $cls['schedules'][0]['date_label'] . ' • ' . $cls['schedules'][0]['time_label']
                            : 'No schedule set';

                        $status_badge = match ($cls['status']) {
                            'Open'      => 'status-active',
                            'Ongoing'   => 'pill-lrn',
                            'Completed' => 'pill-neutral',
                            default     => 'status-inactive',
                        };

                        $accent_class = in_array($cls['status'], ['Open', 'Ongoing'], true) ? 'accent-green' : 'accent-muted';
                        $payload_json = htmlspecialchars(json_encode($cls), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="dir-row-card cls-grid-layout <?php echo $accent_class; ?>"
                             data-search="<?php echo htmlspecialchars(strtolower($cls['code'] . ' ' . $cls['title'] . ' ' . $cls['hub_label'] . ' ' . $cls['lead_teacher'])); ?>"
                             data-level="<?php echo htmlspecialchars($cls['skill_level']); ?>"
                             data-status="<?php echo htmlspecialchars($cls['status']); ?>"
                             data-class="<?php echo $payload_json; ?>">

                        <div>
                            <span class="dir-id-pill pill-adm"><?php echo htmlspecialchars($cls['code']); ?></span>
                        </div>

                        <div class="dir-contact-stack">
                            <strong><?php echo htmlspecialchars($cls['title']); ?></strong>
                            <span><?php echo htmlspecialchars($cls['skill_level']); ?> Level</span>
                        </div>

                        <div class="dir-muted-text"><?php echo htmlspecialchars($cls['hub_label']); ?></div>

                        <div class="dir-spec-text"><?php echo htmlspecialchars($cls['lead_teacher']); ?></div>

                        <div class="dir-muted-text"><?php echo htmlspecialchars($next_sched); ?></div>

                        <div>
                            <span class="dir-class-pill pill-lrn">
                                <?php echo (int)$cls['enrolled_count']; ?> / <?php echo (int)$cls['max_capacity']; ?> Seats
                            </span>
                        </div>

                        <div class="align-right">
                            <span class="dir-status-badge <?php echo $status_badge; ?>">
                                &bull; <?php echo htmlspecialchars($cls['status']); ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="empty-filter-state" id="classesEmptyFilter" style="display: none; margin-top: 12px;">
            No classes match your current search or filter selection.
        </div>

    </main>
</div>

<!-- ==================== MODAL 1: CREATE NEW CLASS (MODAL 2 DESIGN) ==================== -->
<div class="dir-modal-backdrop" id="createClassModal" aria-hidden="true">
    <div class="dir-modal-card roster-modal-card" role="dialog" aria-modal="true" aria-labelledby="createClassTitle">
        <div class="roster-modal-head">
            <div>
                <span class="portal-eyebrow">CLASS ROSTER</span>
                <h2 class="roster-modal-title" id="createClassTitle">Create <em>New Class</em></h2>
            </div>
            <button type="button" class="modal-close-circle" id="closeCreateClassBtn" aria-label="Close">&times;</button>
        </div>

        <form action="actions/admin_create_class.php" method="POST" id="createClassForm" class="roster-modal-scroll">
            <?php echo csrf_field(); ?>

            <!-- Sub-Card 1: Class Details -->
            <section class="roster-subcard">
                <h3 class="roster-subcard-title">Class Details</h3>

                <div class="roster-field">
                    <label for="ccTitle">Class Title <span class="req-star">*</span></label>
                    <input type="text" id="ccTitle" name="class_title" placeholder="e.g., Basic FSL & Everyday Greetings" required>
                </div>

                <div class="roster-field">
                    <label for="ccDescription">Description</label>
                    <textarea id="ccDescription" name="description" rows="2" placeholder="What will learners cover?"></textarea>
                </div>

                <div class="roster-grid-2">
                    <div class="roster-field">
                        <label for="ccBarangay">Barangay Hub (Albay) <span class="req-star">*</span></label>
                        <select id="ccBarangay" name="barangay_id" required>
                            <option value="">— Select Barangay Hub —</option>
                            <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo (int)$b['barangay_id']; ?>">
                                    <?php echo htmlspecialchars($b['municipality_name'] . ' — Brgy. ' . $b['barangay_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="roster-field">
                        <label for="ccSkillLevel">Skill Level <span class="req-star">*</span></label>
                        <select id="ccSkillLevel" name="skill_level" required>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>

                <div class="roster-grid-2">
                    <div class="roster-field">
                        <label for="ccCapacity">Max Capacity <span class="req-star">*</span></label>
                        <input type="number" id="ccCapacity" name="max_capacity" value="20" min="1" max="100" required>
                    </div>

                    <div class="roster-field">
                        <label for="ccStatus">Status <span class="req-star">*</span></label>
                        <select id="ccStatus" name="status" required>
                            <option value="Open">Open (accepting learners)</option>
                            <option value="Ongoing">Ongoing (in session)</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <!-- Repeatable Teachers Section -->
                <div class="roster-field" style="margin-bottom: 0;">
                    <label>Teachers <span class="req-star">*</span></label>
                    <div id="teacherRowsContainer" class="teacher-rows-stack">
                        <div class="teacher-assign-row">
                            <select name="teacher_ids[]" required>
                                <option value="">— Select teacher —</option>
                                <?php foreach ($teachers_list as $t): ?>
                                    <option value="<?php echo (int)$t['teacher_id']; ?>">
                                        <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['specialization'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="teacher_roles[]" required>
                                <option value="Lead Instructor">Lead Instructor</option>
                                <option value="Co-teacher">Co-teacher</option>
                                <option value="Substitute">Substitute</option>
                            </select>

                            <button type="button" class="btn-remove-teacher-row" title="Remove row" aria-label="Remove teacher row">&times;</button>
                        </div>
                    </div>

                    <button type="button" class="btn-add-teacher-row" id="addTeacherRowBtn">
                        + Add another teacher
                    </button>
                    <p class="roster-helper-note">Pick a teacher and their role. One Lead Instructor per class; add co-teachers or substitutes as needed.</p>
                </div>
            </section>

            <!-- Sub-Card 2: First Session -->
            <section class="roster-subcard" style="margin-bottom: 0;">
                <h3 class="roster-subcard-title">
                    First Session <span class="roster-subcard-hint">(add more sessions later, one at a time)</span>
                </h3>

                <div class="roster-grid-2">
                    <div class="roster-field">
                        <label for="ccRoom">Venue <span class="req-star">*</span></label>
                        <input type="text" id="ccRoom" name="room_location" placeholder="e.g., Barangay Hall Session Room A" required>
                    </div>

                    <div class="roster-field">
                        <label for="ccSessionDate">Session Date <span class="req-star">*</span></label>
                        <input type="date" id="ccSessionDate" name="session_date" required>
                    </div>
                </div>

                <div class="roster-grid-2" style="margin-bottom: 0;">
                    <div class="roster-field" style="margin-bottom: 0;">
                        <label for="ccStartTime">Start Time <span class="req-star">*</span></label>
                        <input type="time" id="ccStartTime" name="start_time" value="09:00" required>
                    </div>

                    <div class="roster-field" style="margin-bottom: 0;">
                        <label for="ccEndTime">End Time <span class="req-star">*</span></label>
                        <input type="time" id="ccEndTime" name="end_time" value="11:00" required>
                    </div>
                </div>
            </section>

            <div class="dir-modal-footer" style="margin-top: 16px;">
                <div></div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-modal-close" id="cancelCreateClassBtn">Cancel</button>
                    <button type="submit" class="btn-modal-edit">Create &amp; Schedule Class</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL 2: MANAGE CLASS DETAILS, ROSTER & SCHEDULE ==================== -->
<div class="dir-modal-backdrop" id="manageClassModal" aria-hidden="true">
    <div class="dir-modal-card modal-wide" role="dialog" aria-modal="true" aria-labelledby="mcTitle">
        <div class="dir-modal-header">
            <div class="modal-identity-left">
                <div class="modal-initials-circle theme-berry" id="mcCodeBadge">CLS</div>
                <div class="modal-meta-block">
                    <div class="modal-top-codes theme-berry">
                        <span id="mcSubMeta">CLS-0001 &bull; BEGINNER</span>
                        <span class="dir-status-badge status-active" id="mcStatusBadge">&bull; Open</span>
                    </div>
                    <h2 class="modal-fullname" id="mcTitle">Class Title</h2>
                </div>
            </div>
            <button type="button" class="modal-close-circle" id="closeManageClassBtn" aria-label="Close">&times;</button>
        </div>

        <!-- Modal Sub-Tabs -->
        <div class="dir-segmented-tabs" style="margin-bottom: 14px;">
            <button type="button" class="dir-tab-btn mc-tab-btn active" data-mctab="overview">Overview &amp; Status</button>
            <button type="button" class="dir-tab-btn mc-tab-btn" data-mctab="schedules">Schedules</button>
            <button type="button" class="dir-tab-btn mc-tab-btn" data-mctab="roster">Roster &amp; Educators</button>
        </div>

        <!-- Sub-Tab 1: Overview & Status -->
        <div class="mc-pane" id="mcPane-overview">
            <div class="modal-fields-stack">
                <div class="modal-field-row">
                    <span class="modal-field-label">Barangay Hub</span>
                    <span class="modal-field-value" id="mcHubLabel">—</span>
                </div>
                <div class="modal-field-row">
                    <span class="modal-field-label">Lead Educator</span>
                    <span class="modal-field-value" id="mcLeadTeacher">—</span>
                </div>
                <div class="modal-field-row">
                    <span class="modal-field-label">Enrollment Capacity</span>
                    <span class="modal-field-value" id="mcCapacityLabel">0 / 20 Seats</span>
                </div>
                <div class="modal-field-row">
                    <span class="modal-field-label">Description</span>
                    <span class="modal-field-value" id="mcDescLabel">—</span>
                </div>
                <div class="modal-field-row">
                    <span class="modal-field-label">Class Status</span>
                    <select id="mcStatusSelect" class="modal-field-input">
                        <option value="Open">Open</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="dir-modal-footer">
                <button type="button" class="btn-delete-user" id="mcDeleteClassBtn">Delete Class</button>
                <div class="modal-footer-right">
                    <button type="button" class="btn-modal-close" id="mcCloseFooterBtn">Close</button>
                    <button type="button" class="btn-modal-edit" id="mcSaveStatusBtn">Update Status</button>
                </div>
            </div>
        </div>

        <!-- Sub-Tab 2: Schedules -->
        <div class="mc-pane" id="mcPane-schedules" style="display: none;">
            <div class="modal-fields-stack" id="mcSchedulesList" style="max-height: 180px; overflow-y: auto;"></div>

            <div class="new-admin-grid" style="margin-bottom: 12px;">
                <div class="na-field">
                    <label for="mcNewSchedDate">ADD SESSION DATE</label>
                    <input type="date" id="mcNewSchedDate">
                </div>
                <div class="na-field">
                    <label for="mcNewSchedRoom">VENUE / ROOM</label>
                    <input type="text" id="mcNewSchedRoom" placeholder="e.g., Hub Room 2">
                </div>
                <div class="na-field">
                    <label for="mcNewSchedStart">START TIME</label>
                    <input type="time" id="mcNewSchedStart" value="09:00">
                </div>
                <div class="na-field">
                    <label for="mcNewSchedEnd">END TIME</label>
                    <input type="time" id="mcNewSchedEnd" value="11:00">
                </div>
            </div>

            <div class="dir-modal-footer">
                <div></div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-modal-edit" id="mcAddScheduleBtn">+ Add Session Date</button>
                </div>
            </div>
        </div>

        <!-- Sub-Tab 3: Roster & Educators -->
        <div class="mc-pane" id="mcPane-roster" style="display: none;">
            <div style="margin-bottom: 12px;">
                <span class="menu-label" style="color: var(--text-muted); padding: 0; margin-bottom: 6px;">ASSIGNED EDUCATORS</span>
                <div class="modal-fields-stack" id="mcTeachersList" style="padding-bottom: 8px; margin-bottom: 8px;"></div>
                <div style="display: flex; gap: 8px;">
                    <select id="mcAssignTeacherSelect" class="modal-field-input" style="flex: 1; text-align: left;">
                        <option value="">Assign additional educator...</option>
                        <?php foreach ($teachers_list as $t): ?>
                            <option value="<?php echo (int)$t['teacher_id']; ?>">
                                <?php echo htmlspecialchars($t['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="mcAssignTeacherRole" class="modal-field-input" style="width: 145px; text-align: left;">
                        <option value="Co-teacher">Co-teacher</option>
                        <option value="Lead Instructor">Lead Instructor</option>
                        <option value="Substitute">Substitute</option>
                    </select>
                    <button type="button" class="btn-modal-edit" id="mcAssignTeacherBtn">Assign</button>
                </div>
            </div>

            <div>
                <span class="menu-label" style="color: var(--text-muted); padding: 0; margin-bottom: 6px;">ENROLLED LEARNERS</span>
                <div class="modal-fields-stack" id="mcLearnersList" style="max-height: 150px; overflow-y: auto; padding-bottom: 8px; margin-bottom: 8px;"></div>
                <div style="display: flex; gap: 8px;">
                    <select id="mcEnrollLearnerSelect" class="modal-field-input" style="flex: 1; text-align: left;">
                        <option value="">Select learner to enroll...</option>
                        <?php foreach ($learners_list as $l): ?>
                            <option value="<?php echo (int)$l['learner_id']; ?>">
                                <?php echo htmlspecialchars($l['code'] . ' — ' . $l['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn-modal-edit" id="mcEnrollLearnerBtn">+ Enroll Learner</button>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="assets/js/admin_classes.js?v=2"></script>
<script>
document.addEventListener('click', function (e) {
    const addBtn = e.target.closest('#addTeacherRowBtn');
    if (addBtn) {
        e.preventDefault();
        const container = document.getElementById('teacherRowsContainer');
        const firstRow = container ? container.querySelector('.teacher-assign-row') : null;
        if (!container || !firstRow) return;

        const newRow = firstRow.cloneNode(true);
        const teacherSelect = newRow.querySelector('select[name="teacher_ids[]"]');
        const roleSelect = newRow.querySelector('select[name="teacher_roles[]"]');
        if (teacherSelect) teacherSelect.value = '';
        if (roleSelect) roleSelect.value = 'Co-teacher';

        container.appendChild(newRow);
        return;
    }

    const removeBtn = e.target.closest('.btn-remove-teacher-row');
    if (removeBtn) {
        e.preventDefault();
        const container = document.getElementById('teacherRowsContainer');
        const row = removeBtn.closest('.teacher-assign-row');
        if (!container || !row) return;

        const allRows = container.querySelectorAll('.teacher-assign-row');
        if (allRows.length <= 1) {
            const sel = row.querySelector('select[name="teacher_ids[]"]');
            if (sel) sel.value = '';
        } else {
            row.remove();
        }
    }
});
</script>
</body>
</html>