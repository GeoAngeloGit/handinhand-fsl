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

// ==================== FETCH PROVINCIAL REPORTS DATA ====================
$reports_list  = [];
$teachers_list = [];

$total_reports    = 0;
$for_review_count = 0;
$reviewed_count   = 0;
$revision_count   = 0;

if (isset($conn) && $conn instanceof mysqli) {
    // Active Teachers for filter dropdown
    try {
        $res = $conn->query("
            SELECT teacher_id, first_name, middle_name, last_name
            FROM teachers
            ORDER BY last_name ASC, first_name ASC
        ");
        if ($res) {
            while ($t = $res->fetch_assoc()) {
                $mid = !empty($t['middle_name']) ? ' ' . strtoupper(substr(trim((string)$t['middle_name']), 0, 1)) . '.' : '';
                $teachers_list[] = [
                    'teacher_id' => (int)$t['teacher_id'],
                    'code'       => sprintf('TCH-%05d', (int)$t['teacher_id']),
                    'full_name'  => trim($t['last_name'] . ', ' . $t['first_name'] . $mid)
                ];
            }
        }
    } catch (Throwable $e) {
        error_log("Teachers dropdown fetch error: " . $e->getMessage());
    }

    // Fetch Post-Class Reports joined with Schedules, Classes, Barangays, Municipalities, Teachers
    try {
        $sql = "
            SELECT 
                cr.*,
                cs.schedule_id,
                cs.session_date,
                cs.start_time,
                cs.room_location,
                c.class_id,
                c.class_title,
                c.skill_level,
                b.barangay_name,
                m.municipality_name,
                t.teacher_id AS t_id,
                t.first_name AS t_first,
                t.middle_name AS t_mid,
                t.last_name AS t_last
            FROM class_reports cr
            LEFT JOIN class_schedules cs ON cs.schedule_id = cr.schedule_id
            JOIN classes c ON c.class_id = COALESCE(cr.class_id, cs.class_id)
            LEFT JOIN barangays b ON b.barangay_id = c.barangay_id
            LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
            LEFT JOIN teachers t ON t.teacher_id = cr.teacher_id
            ORDER BY COALESCE(cr.submitted_at, cr.report_date) DESC, cr.report_id DESC
        ";
        $res = $conn->query($sql);

        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rid      = (int)$r['report_id'];
                $sched_id = (int)($r['schedule_id'] ?? 0);
                $cid      = (int)$r['class_id'];
                $tid      = (int)($r['t_id'] ?? $r['teacher_id'] ?? 0);

                $mid = !empty($r['t_mid']) ? ' ' . strtoupper(substr(trim((string)$r['t_mid']), 0, 1)) . '.' : '';
                $teacher_display = trim(($r['t_last'] ?? 'Educator') . ', ' . ($r['t_first'] ?? '') . $mid);
                $teacher_code    = $tid > 0 ? sprintf('TCH-%05d', $tid) : 'TCH-00000';

                $muni = $r['municipality_name'] ?? 'Albay Province';
                $brgy = $r['barangay_name'] ?? '';
                $location_label = $brgy ? "{$muni} (Brgy. {$brgy})" : $muni;

                $sess_date = $r['session_date'] ?? $r['report_date'] ?? '';
                $sess_time = !empty($r['start_time']) ? date('h:i A', strtotime($r['start_time'])) : '09:00 AM';
                $sess_date_fmt = !empty($sess_date) ? date('Y-m-d', strtotime($sess_date)) : '—';

                $raw_sub = $r['submitted_at'] ?? $r['created_at'] ?? $r['report_date'] ?? '';
                $submitted_label = !empty($raw_sub) ? date('m/d/Y h:i A', strtotime($raw_sub)) : '—';

                // Normalize review status
                $raw_status = strtolower(trim((string)($r['review_status'] ?? $r['status'] ?? 'for_review')));
                $status_label = match ($raw_sub_status = str_replace([' ', '-'], '_', $raw_status)) {
                    'reviewed', 'verified', 'approved' => 'Reviewed',
                    'needs_revision', 'flagged', 'returned' => 'Needs Revision',
                    default => 'For Review',
                };

                if ($status_label === 'Reviewed') {
                    $reviewed_count++;
                } elseif ($status_label === 'Needs Revision') {
                    $revision_count++;
                } else {
                    $for_review_count++;
                }

                // Fetch session attendance & grades for this schedule/class
                $att_present = 0;
                $att_total   = 0;
                $learner_logs = [];

                if ($sched_id > 0) {
                    $a_stmt = $conn->prepare("
                        SELECT l.learner_id, l.first_name, l.last_name, a.status, a.remarks
                        FROM attendance a
                        JOIN learners l ON l.learner_id = a.learner_id
                        WHERE a.schedule_id = ?
                        ORDER BY l.last_name ASC, l.first_name ASC
                    ");
                    if ($a_stmt) {
                        $a_stmt->bind_param('i', $sched_id);
                        $a_stmt->execute();
                        $a_res = $a_stmt->get_result();
                        while ($ar = $a_res->fetch_assoc()) {
                            $att_total++;
                            $st = ucfirst(strtolower((string)($ar['status'] ?? 'Present')));
                            if (in_array($st, ['Present', 'Late'], true)) {
                                $att_present++;
                            }
                            $learner_logs[] = [
                                'code'    => sprintf('LRN-%05d', (int)$ar['learner_id']),
                                'name'    => trim($ar['last_name'] . ', ' . $ar['first_name']),
                                'status'  => $st,
                                'remarks' => $ar['remarks'] ?? ''
                            ];
                        }
                        $a_stmt->close();
                    }
                }

                // Fetch class grades snapshot
                $grade_logs = [];
                $g_stmt = $conn->prepare("
                    SELECT l.learner_id, l.first_name, l.last_name, g.assessment_title, g.score, g.max_score
                    FROM grades g
                    JOIN class_enrollments ce ON ce.enrollment_id = g.enrollment_id
                    JOIN learners l ON l.learner_id = ce.learner_id
                    WHERE ce.class_id = ?
                    ORDER BY l.last_name ASC, g.grade_id DESC
                ");
                if ($g_stmt) {
                    $g_stmt->bind_param('i', $cid);
                    $g_stmt->execute();
                    $g_res = $g_stmt->get_result();
                    while ($gr = $g_res->fetch_assoc()) {
                        $sc = rtrim(rtrim(number_format((float)$gr['score'], 2), '0'), '.');
                        $mx = rtrim(rtrim(number_format((float)($gr['max_score'] ?? 100), 2), '0'), '.');
                        $grade_logs[] = [
                            'code'       => sprintf('LRN-%05d', (int)$gr['learner_id']),
                            'name'       => trim($gr['last_name'] . ', ' . $gr['first_name']),
                            'assessment' => $gr['assessment_title'] ?? 'FSL Assessment',
                            'score'      => "{$sc} / {$mx}"
                        ];
                    }
                    $g_stmt->close();
                }

                $photo_path = trim((string)($r['photo_url'] ?? $r['photo_path'] ?? $r['attachment_path'] ?? ''));
                $summary_text = trim((string)($r['summary'] ?? $r['topic_covered'] ?? $r['remarks'] ?? 'Post-class session summary recorded by educator.'));

                $reports_list[] = [
                    'report_id'        => $rid,
                    'code'             => sprintf('RPT-%05d', $rid),
                    'schedule_code'    => $sched_id > 0 ? sprintf('SCH-%03d', $sched_id) : sprintf('CLS-%03d', $cid),
                    'class_title'      => $r['class_title'] ?? 'FSL Session',
                    'location_label'   => $location_label,
                    'session_date_fmt' => $sess_date_fmt,
                    'session_time'     => $sess_time,
                    'teacher_id'       => $tid,
                    'teacher_display'  => $teacher_display,
                    'teacher_code'     => $teacher_code,
                    'summary'          => $summary_text,
                    'coordinator_note' => $r['coordinator_notes'] ?? '',
                    'status'           => $status_label,
                    'has_photo'        => $photo_path !== '',
                    'photo_url'        => $photo_path,
                    'att_summary'      => "{$att_present}/{$att_total} Present",
                    'submitted_label'  => $submitted_label,
                    'learner_logs'     => $learner_logs,
                    'grade_logs'       => $grade_logs
                ];
            }
        }
    } catch (Throwable $e) {
        error_log("Provincial reports query error: " . $e->getMessage());
    }
}

$total_reports = count($reports_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provincial Reports | HandInHand FSL</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/admin.css?v=9">
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

                    <a href="admin_classes.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                        <span>Manage Classes</span>
                    </a>

                    <a href="admin_reports.php" class="nav-item active">
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
                <span class="portal-eyebrow">ADMIN PORTAL &bull; ANALYTICS &amp; EVALUATIONS</span>
                <h1 class="page-title">Provincial <em>Reports</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- 4 KPI Cards -->
        <section class="kpi-grid-4">
            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL POST-CLASS REPORTS</span>
                    <span class="mini-role-pill pill-neutral">ALL</span>
                </div>
                <div class="kpi-value"><?php echo number_format($total_reports); ?></div>
                <p class="kpi-sub">Teacher session reports submitted</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">FOR REVIEW</span>
                    <span class="mini-role-pill pill-lrn">FOR REVIEW</span>
                </div>
                <div class="kpi-value"><?php echo number_format($for_review_count); ?></div>
                <p class="kpi-sub">Awaiting coordinator review</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">REVIEWED REPORTS</span>
                    <span class="mini-role-pill pill-adm">VERIFIED</span>
                </div>
                <div class="kpi-value"><?php echo number_format($reviewed_count); ?></div>
                <p class="kpi-sub">Verified by Albay Coordinator</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">NEEDS REVISION</span>
                    <span class="mini-role-pill pill-tch">FLAGGED</span>
                </div>
                <div class="kpi-value"><?php echo number_format($revision_count); ?></div>
                <p class="kpi-sub">Returned for teacher updates</p>
            </article>
        </section>

        <!-- Submissions & Attendance Logs Control Card -->
        <section class="dir-control-card" style="padding: 18px 20px;">
            <div class="prov-rpt-header-row">
                <div>
                    <span class="portal-eyebrow" style="margin-bottom: 3px;">SUBMISSIONS &amp; ATTENDANCE LOGS</span>
                    <h2 class="prov-rpt-subhead">Teacher Post-Class Reports</h2>
                    <p class="prov-rpt-subdesc">Inspect all teacher-submitted post-class reports, session photos, summary notes, and learner attendance &amp; grades.</p>
                </div>

                <button type="button" class="btn-new-admin" id="exportProvincialCsvBtn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Export CSV
                </button>
            </div>

            <div class="prov-rpt-filter-grid">
                <div class="dir-search-box" style="width: 100%;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" id="provSearchInput" placeholder="Search report ID, schedule, class, or teacher...">
                </div>

                <select id="provStatusFilter" class="prov-select">
                    <option value="ALL">All Statuses</option>
                    <option value="For Review">For Review</option>
                    <option value="Reviewed">Reviewed</option>
                    <option value="Needs Revision">Needs Revision</option>
                </select>

                <select id="provTeacherFilter" class="prov-select">
                    <option value="ALL">All Teachers</option>
                    <?php foreach ($teachers_list as $t): ?>
                        <option value="<?php echo (int)$t['teacher_id']; ?>">
                            <?php echo htmlspecialchars($t['full_name'] . ' (' . $t['code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </section>

        <!-- 2-Column Provincial Report Cards Grid -->
        <section class="prov-cards-grid" id="provReportsGrid">
            <?php if (empty($reports_list)): ?>
                <div class="empty-filter-state" style="grid-column: span 2;">
                    No post-class reports submitted yet. Reports submitted by educators will appear here for coordinator verification.
                </div>
            <?php else: ?>
                <?php foreach ($reports_list as $rpt): ?>
                    <?php
                        $status_pill_class = match ($rpt['status']) {
                            'Reviewed'       => 'rpt-badge-reviewed',
                            'Needs Revision' => 'rpt-badge-revision',
                            default          => 'rpt-badge-review',
                        };
                        $payload_json = htmlspecialchars(json_encode($rpt), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="prov-report-card"
                             data-search="<?php echo htmlspecialchars(strtolower($rpt['code'] . ' ' . $rpt['schedule_code'] . ' ' . $rpt['class_title'] . ' ' . $rpt['location_label'] . ' ' . $rpt['teacher_display'] . ' ' . $rpt['teacher_code'])); ?>"
                             data-status="<?php echo htmlspecialchars($rpt['status']); ?>"
                             data-teacher-id="<?php echo (int)$rpt['teacher_id']; ?>"
                             data-report="<?php echo $payload_json; ?>">

                        <div class="prov-card-top">
                            <div class="prov-card-codes">
                                <span class="dir-id-pill pill-lrn"><?php echo htmlspecialchars($rpt['code']); ?></span>
                                <span class="prov-sched-code">&bull; <?php echo htmlspecialchars($rpt['schedule_code']); ?></span>
                            </div>

                            <div class="prov-card-badges">
                                <?php if ($rpt['has_photo']): ?>
                                    <span class="prov-photo-pill">&#128247; Photo Attached</span>
                                <?php endif; ?>
                                <span class="prov-status-pill <?php echo $status_pill_class; ?>">
                                    <?php echo htmlspecialchars($rpt['status']); ?>
                                </span>
                            </div>
                        </div>

                        <h3 class="prov-card-title"><?php echo htmlspecialchars($rpt['class_title']); ?></h3>

                        <p class="prov-card-meta">
                            <?php echo htmlspecialchars($rpt['location_label']); ?> &bull;
                            <?php echo htmlspecialchars($rpt['session_date_fmt']); ?> (<?php echo htmlspecialchars($rpt['session_time']); ?>) &bull;
                            By <?php echo htmlspecialchars($rpt['teacher_display']); ?> (<?php echo htmlspecialchars($rpt['teacher_code']); ?>)
                        </p>

                        <p class="prov-card-summary"><?php echo htmlspecialchars($rpt['summary']); ?></p>

                        <div class="prov-card-footer">
                            <div class="prov-card-att">
                                <strong><?php echo htmlspecialchars($rpt['att_summary']); ?></strong>
                                <span>&bull; <?php echo htmlspecialchars($rpt['submitted_label']); ?></span>
                            </div>
                            <span class="prov-view-link">View Report &rarr;</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <div class="empty-filter-state" id="provEmptyFilter" style="display: none; margin-top: 14px;">
            No post-class reports match your current search or filter selection.
        </div>

    </main>
</div>

<!-- ==================== MODAL: INSPECT & VERIFY POST-CLASS REPORT ==================== -->
<div class="dir-modal-backdrop" id="provReportModal" aria-hidden="true">
    <div class="dir-modal-card roster-modal-card" role="dialog" aria-modal="true" aria-labelledby="prmTitle">
        <div class="roster-modal-head">
            <div>
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <span class="portal-eyebrow" id="prmEyebrow" style="margin-bottom: 0;">RPT-00001 &bull; SCH-101</span>
                    <span class="prov-status-pill rpt-badge-review" id="prmStatusBadge">For Review</span>
                </div>
                <h2 class="roster-modal-title" id="prmTitle">Class Title</h2>
                <p class="prov-card-meta" id="prmMetaLine" style="margin-top: 4px; margin-bottom: 0;">—</p>
            </div>
            <button type="button" class="modal-close-circle" id="closePrmBtn" aria-label="Close">&times;</button>
        </div>

        <div class="roster-modal-scroll">
            <!-- Session Photo & Summary Sub-Card -->
            <section class="roster-subcard">
                <h3 class="roster-subcard-title">Session Summary &amp; Documentation</h3>
                <p id="prmSummaryText" style="font-size: 0.78rem; color: var(--maroon-deep); line-height: 1.55; margin-bottom: 10px;">—</p>

                <div id="prmPhotoWrap" style="display: none; margin-top: 10px;">
                    <span class="menu-label" style="color: var(--text-muted); padding: 0; margin-bottom: 6px;">ATTACHED SESSION PHOTO</span>
                    <img id="prmPhotoImg" src="" alt="Session Photo" style="width: 100%; max-height: 220px; object-fit: cover; border-radius: 12px; border: 1px solid var(--border-soft);">
                </div>
            </section>

            <!-- Learner Attendance Sub-Card -->
            <section class="roster-subcard">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h3 class="roster-subcard-title" style="margin-bottom: 0;">Learner Attendance</h3>
                    <span class="dir-class-pill pill-lrn" id="prmAttBadge">0/0 Present</span>
                </div>
                <div class="modal-fields-stack" id="prmAttendanceList" style="padding-bottom: 0;"></div>
            </section>

            <!-- Learner Grades Sub-Card -->
            <section class="roster-subcard">
                <h3 class="roster-subcard-title">Learner Assessments &amp; Grades</h3>
                <div class="modal-fields-stack" id="prmGradesList" style="padding-bottom: 0;"></div>
            </section>

            <!-- Coordinator Review Action Sub-Card -->
            <section class="roster-subcard" style="margin-bottom: 0;">
                <h3 class="roster-subcard-title">Coordinator Verification</h3>
                <div class="roster-grid-2" style="margin-bottom: 0;">
                    <div class="roster-field" style="margin-bottom: 0;">
                        <label for="prmReviewStatusSelect">Review Decision</label>
                        <select id="prmReviewStatusSelect">
                            <option value="For Review">For Review</option>
                            <option value="Reviewed">Reviewed (Verified)</option>
                            <option value="Needs Revision">Needs Revision (Flagged)</option>
                        </select>
                    </div>
                    <div class="roster-field" style="margin-bottom: 0;">
                        <label for="prmCoordinatorNotes">Coordinator Remarks</label>
                        <input type="text" id="prmCoordinatorNotes" placeholder="Optional feedback for teacher...">
                    </div>
                </div>
            </section>
        </div>

        <div class="dir-modal-footer" style="margin-top: 14px;">
            <div></div>
            <div class="modal-footer-right">
                <button type="button" class="btn-modal-close" id="cancelPrmBtn">Close</button>
                <button type="button" class="btn-modal-edit" id="savePrmReviewBtn">Save Review Status</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/admin_reports.js?v=2"></script>

</body>
</html>