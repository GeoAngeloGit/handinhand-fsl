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

$preselected_class_id = (int)($_GET['class_id'] ?? 0);

// ==================== FETCH CLASSES & ENROLLED LEARNERS FROM MYSQL ====================
$classes_options = [];
$learners_list   = [];

if (isset($conn) && $conn instanceof mysqli) {
    // 1. Fetch Classes for Dropdown
    try {
        $sql_cls = "SELECT c.class_id, c.class_title AS class_name
                    FROM class c
                    JOIN class_teacher ct ON ct.class_id = c.class_id
                    WHERE ct.teacher_id = " . (int)$teacher_id . "
                    ORDER BY c.class_id ASC";
        if ($res = $conn->query($sql_cls)) {
            while ($c = $res->fetch_assoc()) {
                $cid = (int)$c['class_id'];
                $classes_options[$cid] = [
                    'raw_id' => $cid,
                    'code'   => sprintf('CLS-%03d', $cid),
                    'title'  => $c['class_name'] ?? ('FSL Class #' . $cid),
                    'count'  => 0,
                ];
            }
        }
    } catch (Throwable $e) {
        $classes_options = [];
    }

    // 2. Fetch Learners + Class Enrollment Mapping + Attendance/Rating
    try {
        $sql_lrn = "
            SELECT 
                l.*,
                a.email,
                a.created_at,
                YEAR(a.created_at) AS acc_year,
                b.barangay_name,
                m.municipality_name
            FROM learners l
            JOIN accounts a ON a.account_id = l.account_id
            LEFT JOIN barangays b ON b.barangay_id = l.barangay_id
            LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
            ORDER BY l.learner_id ASC
        ";
        if ($res = $conn->query($sql_lrn)) {
            while ($r = $res->fetch_assoc()) {
                $lid = (int)$r['learner_id'];
                $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
                $full_name = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '') . $mid);
                $lrn_code  = sprintf('LRN-%05d', $lid);
                $acc_code  = sprintf('ACC-%s-%03d', $r['acc_year'] ?: date('Y'), (int)$r['account_id']);

                $status     = !empty($r['is_active']) ? 'Active' : 'Inactive';
                $brgy       = trim((string)($r['barangay_name'] ?? ''));
                $muni       = trim((string)($r['municipality_name'] ?? ''));
                $loc        = trim($brgy . ($brgy !== '' && $muni !== '' ? ', ' : '') . $muni)
                    ?: (!empty($r['barangay_id']) ? 'Barangay #' . $r['barangay_id'] : 'Albay Province');

                // Enrolled classes for this learner
                $enrolled_ids   = [];
                $enrolled_codes = [];
                try {
                    $q_enr = "SELECT e.class_id
                              FROM class_enrollments e
                              JOIN class_teacher ct ON ct.class_id = e.class_id
                              WHERE e.learner_id = {$lid}
                                AND e.status = 'enrolled'
                                AND ct.teacher_id = " . (int)$teacher_id . "
                              ORDER BY e.class_id ASC";
                    if ($ren = $conn->query($q_enr)) {
                        while ($er = $ren->fetch_assoc()) {
                            $ecid = (int)$er['class_id'];
                            $enrolled_ids[]   = $ecid;
                            $enrolled_codes[] = sprintf('CLS-%03d', $ecid);
                            if (isset($classes_options[$ecid])) {
                                $classes_options[$ecid]['count']++;
                            }
                        }
                    }
                } catch (Throwable $ignore) {}

                $primary_class_code  = $enrolled_codes[0] ?? 'UNASSIGNED';
                $primary_class_id    = $enrolled_ids[0] ?? 0;
                $primary_class_label = 'Unassigned';
                if ($primary_class_id > 0 && isset($classes_options[$primary_class_id])) {
                    $primary_class_label = $classes_options[$primary_class_id]['code'] . ' — ' . $classes_options[$primary_class_id]['title'];
                }

                // Latest session attendance & observation rating
                $latest_attendance = 'Present';
                $obs_rating        = 'Satisfactory';
                $teacher_remarks   = 'Consistent visual focus and clear sign formation.';
                try {
                    $q_att = "
                        SELECT status AS attendance_status, session_rating AS rating, remarks
                        FROM session_attendance
                        WHERE learner_id = {$lid}
                        ORDER BY attendance_id DESC
                        LIMIT 1
                    ";
                    if ($ratt = $conn->query($q_att)) {
                        if ($ar = $ratt->fetch_assoc()) {
                            $latest_attendance = !empty($ar['attendance_status']) ? ucfirst((string)$ar['attendance_status']) : $latest_attendance;
                            $obs_rating        = $ar['rating'] ?? $obs_rating;
                            $teacher_remarks   = $ar['remarks'] ?: $teacher_remarks;
                        }
                    }
                } catch (Throwable $ignore) {}

                $learners_list[] = [
                    'raw_id'           => $lid,
                    'code'             => $lrn_code,
                    'account_code'     => $acc_code,
                    'initials'         => strtoupper(substr((string)($r['first_name'] ?? 'L'), 0, 1) . substr((string)($r['last_name'] ?? 'N'), 0, 1)),
                    'full_name'        => $full_name,
                    'primary_class'    => $primary_class_code,
                    'primary_class_id' => $primary_class_id,
                    'assigned_class'   => $primary_class_label,
                    'class_ids'        => $enrolled_ids,
                    'class_codes'      => $enrolled_codes,
                    'hearing'          => $r['hearing_status'] ?: 'Deaf',
                    'speech'           => $r['speech_status'] ?? ($r['communication_mode'] ?? 'Non-verbal'),
                    'location'         => $loc,
                    'contact'          => $r['contact_number'] ?: '—',
                    'email'            => $r['email'] ?? '—',
                    'guardian'         => $r['guardian_name'] ?? ($r['emergency_contact'] ?? '—'),
                    'attendance'       => $latest_attendance,
                    'rating'           => $obs_rating,
                    'remarks'          => $teacher_remarks,
                    'enrolled_count'   => count($enrolled_codes),
                    'status'           => $status,
                ];
            }
        }
    } catch (Throwable $e) {
        $learners_list = [];
    }
}

$total_classes_cnt  = count($classes_options);
$total_learners_cnt = count($learners_list);
$active_lrn_cnt     = count(array_filter($learners_list, fn($l) => $l['status'] === 'Active'));
$inactive_lrn_cnt   = $total_learners_cnt - $active_lrn_cnt;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learner Lists | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/teacher.css?v=8">
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

                    <a href="teacher_classes.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                        <span>Classes</span>
                    </a>

                    <a href="teacher_learners.php" class="nav-item active">
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
                <span class="portal-eyebrow">TEACHER PORTAL &bull; ROSTER &amp; DIRECTORY</span>
                <h1 class="page-title">Learner <em>Lists</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- Unified Top Filter & Class Selector Card -->
        <section class="lrn-control-card">
            <div class="lrn-class-bar">
                <div class="lrn-class-selector-left">
                    <div class="lrn-book-badge" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                    </div>

                    <select id="classFilterSelect" class="lrn-class-dropdown" aria-label="Filter by Class">
                        <option value="ALL" data-title="All Assigned Classes">All Classes (<?php echo $total_learners_cnt; ?> Learners)</option>
                        <?php foreach ($classes_options as $cls_opt): ?>
                            <option value="<?php echo (int)$cls_opt['raw_id']; ?>"
                                    data-title="<?php echo htmlspecialchars($cls_opt['code'] . ' — ' . $cls_opt['title']); ?>"
                                    <?php echo ($preselected_class_id === (int)$cls_opt['raw_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls_opt['code'] . ' — ' . $cls_opt['title'] . ' (' . $cls_opt['count'] . ' Learners)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="lrn-stepper-group">
                        <button type="button" class="lrn-step-btn" id="prevClassBtn" title="Previous Class">&lsaquo;</button>
                        <button type="button" class="lrn-step-btn" id="nextClassBtn" title="Next Class">&rsaquo;</button>
                    </div>
                </div>

                <span class="lrn-classes-total-pill"><?php echo $total_classes_cnt; ?> Classes Total</span>
            </div>

            <div class="lrn-filter-bar">
                <div class="lrn-summary-left">
                    <strong id="lrnDirectoryTitle">Learners Directory (All Assigned Classes)</strong>
                    <span class="lrn-active-pill" id="lrnActiveSummary"><?php echo $active_lrn_cnt; ?> Active &bull; <?php echo $inactive_lrn_cnt; ?> Inactive</span>
                </div>

                <div class="lrn-filter-controls">
                    <div class="lrn-search-box">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="lrnSearchInput" placeholder="Search ID, name, or email...">
                    </div>

                    <div class="lrn-sort-group">
                        <span class="lrn-sort-label">SORT</span>
                        <select id="lrnSortSelect">
                            <option value="id-asc">Learner ID (Ascending)</option>
                            <option value="id-desc">Learner ID (Descending)</option>
                            <option value="name-asc">Full Name (A–Z)</option>
                            <option value="name-desc">Full Name (Z–A)</option>
                        </select>
                    </div>

                    <div class="lrn-status-pills" role="group" aria-label="Status Filter">
                        <button type="button" class="lrn-status-btn active" data-status="ALL">All</button>
                        <button type="button" class="lrn-status-btn" data-status="Active">Active</button>
                        <button type="button" class="lrn-status-btn" data-status="Inactive">Inactive</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- 7-Column Learner Table Header -->
        <div class="lrn-table-header">
            <span class="col-head-underline">LEARNER ID</span>
            <span>FULL NAME</span>
            <span>COMMUNICATION PROFILE</span>
            <span>LOCATION</span>
            <span>CONTACT &amp; EMAIL</span>
            <span>ENROLLED CLASSES</span>
            <span class="align-right">STATUS</span>
        </div>

        <!-- Learner Roster Rows Stack -->
        <div class="lrn-rows-stack" id="lrnRowsStack">
            <?php if (empty($learners_list)): ?>
                <div class="empty-panel-note">No learner records found in the database.</div>
            <?php else: ?>
                <?php foreach ($learners_list as $lrn): ?>
                    <?php
                        $is_active = ($lrn['status'] === 'Active');
                        $hearing_lower = strtolower($lrn['hearing']);
                        $hearing_class = str_contains($hearing_lower, 'hard')
                            ? 'badge-hoh'
                            : (str_contains($hearing_lower, 'hearing') && !str_contains($hearing_lower, 'deaf') ? 'badge-hearing' : 'badge-deaf');

                        $modal_payload = htmlspecialchars(json_encode([
                            'code'             => $lrn['code'],
                            'account_code'     => $lrn['account_code'],
                            'initials'         => $lrn['initials'],
                            'full_name'        => $lrn['full_name'],
                            'status'           => $lrn['status'],
                            'primary_class_id' => $lrn['primary_class_id'],
                            'assigned_class'   => $lrn['assigned_class'],
                            'comm'             => $lrn['hearing'] . ' • ' . $lrn['speech'],
                            'location'         => $lrn['location'],
                            'contact'          => $lrn['contact'],
                            'email'            => $lrn['email'],
                            'guardian'         => $lrn['guardian'],
                            'attendance'       => $lrn['attendance'],
                            'rating'           => $lrn['rating'],
                            'remarks'          => $lrn['remarks'],
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="lrn-row-card <?php echo $is_active ? 'accent-green' : 'accent-muted'; ?>"
                             data-code="<?php echo htmlspecialchars(strtolower($lrn['code'])); ?>"
                             data-name="<?php echo htmlspecialchars(strtolower($lrn['full_name'])); ?>"
                             data-email="<?php echo htmlspecialchars(strtolower($lrn['email'])); ?>"
                             data-status="<?php echo htmlspecialchars($lrn['status']); ?>"
                             data-classes="<?php echo htmlspecialchars(implode(',', $lrn['class_ids'])); ?>"
                             data-modal="<?php echo $modal_payload; ?>">

                        <div>
                            <span class="lrn-id-pill"><?php echo htmlspecialchars($lrn['code']); ?></span>
                        </div>

                        <div class="lrn-name-col">
                            <strong><?php echo htmlspecialchars($lrn['full_name']); ?></strong>
                            <span><?php echo htmlspecialchars($lrn['primary_class']); ?></span>
                        </div>

                        <div class="lrn-comm-col">
                            <span class="comm-tag <?php echo $hearing_class; ?>"><?php echo htmlspecialchars($lrn['hearing']); ?></span>
                            <span class="comm-tag badge-speech"><?php echo htmlspecialchars($lrn['speech']); ?></span>
                        </div>

                        <div class="lrn-loc-col"><?php echo htmlspecialchars($lrn['location']); ?></div>

                        <div class="lrn-contact-col">
                            <strong><?php echo htmlspecialchars($lrn['contact']); ?></strong>
                            <span><?php echo htmlspecialchars($lrn['email']); ?></span>
                        </div>

                        <div>
                            <span class="lrn-enrolled-pill"><?php echo (int)$lrn['enrolled_count']; ?> Active</span>
                        </div>

                        <div class="align-right">
                            <span class="class-status-pill <?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
                                &bull; <?php echo htmlspecialchars($lrn['status']); ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="empty-panel-note" id="lrnEmptyFilterState" style="display: none; margin-top: 12px;">
            No learners match the selected class, search query, or status filter.
        </div>

    </main>
</div>

<!-- ==================== LEARNER PROFILE MODAL ==================== -->
<div class="cls-modal-backdrop" id="learnerProfileModal" aria-hidden="true">
    <div class="cls-modal-card" role="dialog" aria-modal="true" aria-labelledby="lrnModalName">
        <div class="cls-modal-header">
            <div class="cls-modal-head-left" style="align-items: center;">
                <div class="lrn-modal-avatar" id="lrnModalInitials">JD</div>
                <div>
                    <div class="cls-modal-eyebrow-row">
                        <span id="lrnModalCode"></span>
                        <span class="class-status-pill status-active" id="lrnModalStatus"></span>
                    </div>
                    <h2 class="cls-modal-title" id="lrnModalName"></h2>
                </div>
            </div>
            <button type="button" class="cls-modal-close" id="closeLrnModalBtn" aria-label="Close modal">&times;</button>
        </div>

        <div class="cls-detail-rows">
            <div class="cls-detail-row">
                <span class="cls-row-label">Assigned Class</span>
                <strong class="cls-row-val" id="lrnModalAssignedClass"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Hearing &amp; Speaking Profile</span>
                <strong class="cls-row-val" id="lrnModalComm"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Barangay &amp; Municipality</span>
                <strong class="cls-row-val" id="lrnModalLoc"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Contact Number</span>
                <strong class="cls-row-val" id="lrnModalContact"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Email Address</span>
                <strong class="cls-row-val" id="lrnModalEmail"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Guardian / Household Contact</span>
                <strong class="cls-row-val" id="lrnModalGuardian"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Latest Session Attendance</span>
                <strong class="cls-row-val" id="lrnModalAttendance"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Observation Rating</span>
                <strong class="cls-row-val" id="lrnModalRating"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Teacher Remarks</span>
                <strong class="cls-row-val" id="lrnModalRemarks" style="text-align: right; max-width: 62%;"></strong>
            </div>
        </div>

        <div class="cls-modal-footer">
            <button type="button" class="btn-cls-secondary" id="cancelLrnModalBtn">Close</button>
            <a href="teacher_classes.php" class="btn-cls-primary" id="lrnModalViewClassBtn">View Assigned Class &rarr;</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/logout_modal.php'; ?>
<script src="assets/js/teacher.js?v=1"></script>

<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>

</body>
</html>