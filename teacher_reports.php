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
$teacher_code       = 'TCH-00001';
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
            $teacher_code       = sprintf('TCH-%05d', $teacher_id);
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

// ==================== FETCH SESSION REPORTS, SCHEDULES & ROSTERS ====================
$reports_list      = [];
$schedules_options = [];
$rosters_by_class  = [];

if (isset($conn) && $conn instanceof mysqli) {
    // 1. Preload Enrolled Learners per Class (for dynamic modal student list)
    try {
        $q_rosters = "
            SELECT 
                e.class_id,
                l.learner_id,
                l.first_name,
                l.middle_name,
                l.last_name,
                l.hearing_status
            FROM class_enrollments e
            JOIN learners l ON l.learner_id = e.learner_id
            JOIN class_teacher ct ON ct.class_id = e.class_id
            WHERE ct.teacher_id = " . (int)$teacher_id . "
              AND e.status = 'enrolled'
            ORDER BY e.class_id ASC, l.learner_id ASC
        ";
        if ($res = $conn->query($q_rosters)) {
            while ($r = $res->fetch_assoc()) {
                $cid = (int)$r['class_id'];
                $lid = (int)$r['learner_id'];
                $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
                $rosters_by_class[$cid][] = [
                    'learner_id' => $lid,
                    'code'       => sprintf('LRN-%05d', $lid),
                    'full_name'  => trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '') . $mid),
                    'hearing'    => $r['hearing_status'] ?: 'Deaf',
                ];
            }
        }
    } catch (Throwable $e) {
        $rosters_by_class = [];
    }

    // 2. Fetch Class Schedules for the Modal Dropdown
    try {
        $q_sched = "
            SELECT 
                s.schedule_id,
                s.class_id,
                s.session_date,
                s.start_time,
                c.class_title AS class_name,
                s.venue AS location
            FROM class_schedule s
            JOIN class c ON c.class_id = s.class_id
            JOIN class_teacher ct ON ct.class_id = c.class_id
            WHERE ct.teacher_id = " . (int)$teacher_id . "
            ORDER BY s.session_date ASC, s.start_time ASC
        ";
        if ($res = $conn->query($q_sched)) {
            while ($s = $res->fetch_assoc()) {
                $sid = (int)$s['schedule_id'];
                $cid = (int)$s['class_id'];
                $dt  = (string)($s['session_date'] ?? date('Y-m-d'));
                $tm  = !empty($s['start_time']) ? date('h:i A', strtotime((string)$s['start_time'])) : '09:00 AM';

                $schedules_options[] = [
                    'schedule_id' => $sid,
                    'class_id'    => $cid,
                    'sched_code'  => sprintf('SCH-%03d', $sid),
                    'class_title' => $s['class_name'] ?? ('FSL Class #' . $cid),
                    'location'    => $s['location'] ?? 'Legazpi City (Brgy. Rawis)',
                    'date'        => $dt,
                    'time'        => $tm,
                    'label'       => sprintf('SCH-%03d • %s (%s)', $sid, $s['class_name'] ?? 'FSL Class', $dt),
                ];
            }
        }
    } catch (Throwable $e) {
        $schedules_options = [];
    }

    // 3. Fetch Session Reports
    try {
        $q_rep = "
            SELECT 
                r.*,
                r.submission_timestamp AS submitted_at,
                s.schedule_id,
                s.session_date,
                s.start_time,
                c.class_id,
                c.class_title AS class_name,
                s.venue AS location,
                (SELECT p.photo_file_path FROM report_photos p
                  WHERE p.report_id = r.report_id
                  ORDER BY p.photo_id ASC LIMIT 1) AS photo_proof
            FROM post_class_report r
            JOIN class_schedule s ON s.schedule_id = r.schedule_id
            JOIN class c ON c.class_id = s.class_id
            JOIN class_teacher ct ON ct.class_id = c.class_id
            WHERE ct.teacher_id = " . (int)$teacher_id . "
            ORDER BY r.report_id DESC
        ";
        if ($res = $conn->query($q_rep)) {
            while ($rp = $res->fetch_assoc()) {
                $rid        = (int)$rp['report_id'];
                $sid        = (int)($rp['schedule_id'] ?? 0);
                // Canonical statuses: Pending, Finished, Verified, For Revision
                $status_map    = ['pending' => 'Pending', 'finished' => 'Finished', 'verified' => 'Verified', 'for revision' => 'For Revision'];
                $raw_status    = strtolower(trim((string)($rp['status'] ?? 'pending')));
                $status_label  = $status_map[$raw_status] ?? 'Pending';
                $admin_remarks = trim((string)($rp['admin_remarks'] ?? ''));

                $photo_path = trim((string)($rp['photo_proof'] ?? ($rp['session_photo'] ?? '')));
                $has_photo  = ($photo_path !== '');

                $dt = (string)($rp['session_date'] ?? date('Y-m-d'));
                $tm = !empty($rp['start_time']) ? date('h:i A', strtotime((string)$rp['start_time'])) : '09:00 AM';
                $loc = $rp['location'] ?? 'Legazpi City (Brgy. Rawis)';

                // Attendance summary from session_attendance if available
                $present_cnt = (int)($rp['present_count'] ?? 0);
                $total_cnt   = (int)($rp['total_students'] ?? 0);
                try {
                    if ($ratt = $conn->query("SELECT COUNT(*) AS tot, SUM(CASE WHEN LOWER(status)='present' THEN 1 ELSE 0 END) AS pres FROM session_attendance WHERE report_id = {$rid}")) {
                        $ar = $ratt->fetch_assoc();
                        if ((int)($ar['tot'] ?? 0) > 0) {
                            $total_cnt   = (int)$ar['tot'];
                            $present_cnt = (int)$ar['pres'];
                        }
                    }
                } catch (Throwable $ignore) {}

                $created_fmt = !empty($rp['submitted_at'])
                    ? date('m/d/Y, h:i A', strtotime((string)$rp['submitted_at']))
                    : (!empty($rp['created_at']) ? date('m/d/Y, h:i A', strtotime((string)$rp['created_at'])) : date('m/d/Y, h:i A'));

                $reports_list[] = [
                    'raw_id'      => $rid,
                    'report_code' => sprintf('RPT-%05d', $rid),
                    'sched_code'  => $sid > 0 ? sprintf('SCH-%03d', $sid) : 'SCH-001',
                    'title'       => $rp['class_name'] ?? 'FSL Session Report',
                    'meta_line'   => "{$loc} • {$dt} ({$tm})",
                    'notes'       => $rp['summary_notes'] ?? ($rp['notes'] ?? 'Session summary pending completion.'),
                    'has_photo'   => $has_photo,
                    'photo_path'  => $photo_path,
                    'status'      => $status_label,
                    'status_slug' => strtolower(str_replace(' ', '-', $status_label)),
                    'schedule_id' => $sid,
                    'class_id'    => (int)($rp['class_id'] ?? 0),
                    'admin_remarks' => $admin_remarks,
                    'attendance'  => "{$present_cnt}/{$total_cnt} Present",
                    'timestamp'   => $created_fmt,
                ];
            }
        }
    } catch (Throwable $e) {
        $reports_list = [];
    }
}

$cnt_all = count($reports_list);
$cnt_by_status = ['Pending' => 0, 'Finished' => 0, 'Verified' => 0, 'For Revision' => 0];
foreach ($reports_list as $r) {
    $cnt_by_status[$r['status']]++;
}

$flash_msg  = $_SESSION['report_flash'] ?? '';
$flash_type = $_SESSION['report_flash_type'] ?? 'success';
unset($_SESSION['report_flash'], $_SESSION['report_flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Reports | HandInHand FSL</title>

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

                    <a href="teacher_learners.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Learner Lists</span>
                    </a>

                    <a href="teacher_reports.php" class="nav-item active">
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
                <span class="portal-eyebrow">TEACHER PORTAL &bull; SESSION DOCUMENTATION</span>
                <h1 class="page-title">Session <em>Reports</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <?php if ($flash_msg !== ''): ?>
            <div class="rpt-flash-banner <?php echo $flash_type === 'error' ? 'flash-error' : 'flash-success'; ?>">
                <?php echo htmlspecialchars($flash_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Top Session Documentation Control Card -->
        <section class="rpt-control-card">
            <div class="rpt-header-top">
                <div>
                    <h2 class="rpt-card-heading">Session Documentation</h2>
                    <p class="rpt-card-sub">Record student attendance, assign marks/grades, and submit with a session photo.</p>
                </div>
                <button type="button" class="btn-make-report" id="openMakeReportModalBtn">
                    <span>+</span> Make a Session Report
                </button>
            </div>

            <div class="rpt-filter-row">
                <div class="segmented-filter-pills" role="tablist">
                    <button type="button" class="rpt-filter-pill active" data-filter="ALL">All Reports (<?php echo $cnt_all; ?>)</button>
                    <?php foreach ($cnt_by_status as $st_name => $st_cnt): ?>
                        <button type="button" class="rpt-filter-pill" data-filter="<?php echo htmlspecialchars($st_name); ?>">
                            <?php echo htmlspecialchars($st_name); ?> (<?php echo $st_cnt; ?>)
                        </button>
                    <?php endforeach; ?>
                </div>

                <span class="rpt-counts-text">
                    <?php echo $cnt_by_status['Pending']; ?> Pending &bull;
                    <?php echo $cnt_by_status['Finished']; ?> Finished &bull;
                    <?php echo $cnt_by_status['Verified']; ?> Verified &bull;
                    <?php echo $cnt_by_status['For Revision']; ?> For Revision
                </span>
            </div>
        </section>

        <!-- 2-Column Session Report Cards Grid -->
        <section class="rpt-cards-grid" id="reportsCardsGrid">
            <?php if (empty($reports_list)): ?>
                <div class="empty-panel-note" style="grid-column: 1 / -1;">
                    No session reports documented yet. Click <strong>+ Make a Session Report</strong> above to create your first log.
                </div>
            <?php else: ?>
                <?php foreach ($reports_list as $rpt): ?>
                    <?php
                        $can_edit   = in_array($rpt['status'], ['Pending', 'For Revision'], true);
                        $modal_json   = htmlspecialchars(json_encode($rpt), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="rpt-item-card"
                             data-status="<?php echo htmlspecialchars($rpt['status']); ?>"
                             data-modal="<?php echo $modal_json; ?>">

                        <div class="rpt-item-top">
                            <div class="rpt-code-line">
                                <span class="rpt-id-pill"><?php echo htmlspecialchars($rpt['report_code']); ?></span>
                                <span class="rpt-sch-text">&bull; <?php echo htmlspecialchars($rpt['sched_code']); ?></span>
                            </div>

                            <div class="rpt-badges-right">
                                <?php if ($rpt['has_photo']): ?>
                                    <span class="rpt-photo-pill attached">&#128247; Photo Attached</span>
                                <?php else: ?>
                                    <span class="rpt-photo-pill missing">No Photo Yet</span>
                                <?php endif; ?>

                                <span class="class-status-pill rpt-status-<?php echo htmlspecialchars($rpt['status_slug']); ?>">
                                    <?php echo htmlspecialchars($rpt['status']); ?>
                                </span>
                            </div>
                        </div>

                        <h3 class="rpt-item-title"><?php echo htmlspecialchars($rpt['title']); ?></h3>
                        <div class="rpt-item-meta"><?php echo htmlspecialchars($rpt['meta_line']); ?></div>

                        <p class="rpt-item-notes"><?php echo htmlspecialchars($rpt['notes']); ?></p>

                        <?php if ($rpt['status'] === 'For Revision'): ?>
                            <div class="rpt-revision-note">
                                <strong>Admin remarks</strong>
                                <p><?php echo $rpt['admin_remarks'] !== ''
                                    ? nl2br(htmlspecialchars($rpt['admin_remarks']))
                                    : 'No remarks were provided. Please review and resubmit.'; ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="rpt-item-footer">
                            <strong><?php echo htmlspecialchars($rpt['attendance']); ?></strong>
                            <span>&bull; <?php echo htmlspecialchars($rpt['timestamp']); ?></span>
                            <?php if ($can_edit): ?>
                                <button type="button" class="btn-revise-report"
                                        data-schedule-id="<?php echo (int)$rpt['schedule_id']; ?>">
                                    <?php echo $rpt['status'] === 'For Revision' ? 'Revise &amp; Resubmit' : 'Continue Report'; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </main>
</div>

<!-- ==================== MAKE A SESSION REPORT MODAL ==================== -->
<div class="cls-modal-backdrop" id="makeReportModal" aria-hidden="true">
    <div class="rpt-modal-card" role="dialog" aria-modal="true" aria-labelledby="makeReportTitle">
        <div class="rpt-modal-header">
            <div>
                <span class="portal-eyebrow">TEACHER SESSION DOCUMENTATION &bull; <?php echo htmlspecialchars($teacher_code); ?></span>
                <h2 class="page-title" id="makeReportTitle" style="font-size: 1.55rem;">Make a <em>Session Report</em></h2>
            </div>
            <button type="button" class="cls-modal-close" id="closeMakeReportBtn" aria-label="Close modal">&times;</button>
        </div>

        <form id="sessionReportForm" action="actions/teacher_save_report.php" method="POST" enctype="multipart/form-data">
            <?php
            // CSRF token (verified by actions/teacher_save_report.php). The teacher is derived server-side.
            if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)csrf_token()); ?>">
            <?php elseif (function_exists('csrf_field')): echo csrf_field(); endif; ?>
            <input type="hidden" name="report_action" id="reportActionInput" value="submitted">
            <input type="hidden" name="use_sample_photo" id="useSamplePhotoInput" value="0">

            <!-- Class Schedule Selector -->
            <div class="rpt-form-section">
                <label class="cls-box-eyebrow" for="reportScheduleSelect">CLASS SCHEDULE</label>
                <div class="rpt-sched-selector-row">
                    <select id="reportScheduleSelect" name="schedule_id" class="rpt-sched-select" required>
                        <?php if (empty($schedules_options)): ?>
                            <option value="0" data-class-id="0">No class schedules available</option>
                        <?php else: ?>
                            <?php foreach ($schedules_options as $sch): ?>
                                <option value="<?php echo (int)$sch['schedule_id']; ?>"
                                        data-class-id="<?php echo (int)$sch['class_id']; ?>">
                                    <?php echo htmlspecialchars($sch['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <span class="lrn-classes-total-pill" id="enrolledStudentsBadge">0 Enrolled Students</span>
                </div>
            </div>

            <!-- Student Attendance & Marks / Grade -->
            <div class="rpt-attendance-box">
                <div class="rpt-att-header">
                    <div>
                        <span class="cls-box-eyebrow" style="margin-bottom: 2px;">STUDENT ATTENDANCE &amp; MARKS / GRADE</span>
                        <div class="rpt-att-live-counts" id="attLiveCounters">Present: 0 &bull; Late: 0 &bull; Absent: 0</div>
                    </div>
                    <button type="button" class="btn-mark-all" id="markAllPresentBtn">&check; Mark All Present</button>
                </div>

                <div class="rpt-students-scroll" id="reportStudentsContainer">
                    <!-- Populated dynamically by assets/js/teacher.js -->
                </div>
            </div>

            <!-- Session Summary Notes -->
            <div class="rpt-form-section">
                <label class="cls-box-eyebrow" style="color: var(--berry-accent);" for="sessionSummaryNotes">
                    SESSION SUMMARY NOTES (REQUIRED FOR FINAL SUBMISSION)
                </label>
                <textarea id="sessionSummaryNotes" name="summary_notes" rows="3" class="rpt-notes-textarea"
                          placeholder="Summarize FSL vocabulary practiced, learner participation, and session highlights..."></textarea>
            </div>

            <!-- Session Photo Proof -->
            <div class="rpt-form-section">
                <div class="rpt-photo-label-row">
                    <label class="cls-box-eyebrow" style="color: #B91C1C; margin-bottom: 0;" for="sessionPhotoInput">
                        SESSION PHOTO PROOF * REQUIRED BEFORE SUBMITTING
                    </label>
                    <button type="button" class="btn-sample-photo" id="useSamplePhotoBtn">+ Use Sample Session Photo</button>
                </div>

                <div class="rpt-photo-dropzone">
                    <label for="sessionPhotoInput" class="btn-browse-photo">Browse...</label>
                    <input type="file" id="sessionPhotoInput" name="session_photo" accept="image/*" style="display: none;">
                    <span class="rpt-file-name" id="selectedPhotoName">No file selected.</span>
                </div>
            </div>

            <!-- Modal Action Footer -->
            <div class="rpt-modal-footer">
                <button type="button" class="btn-cls-secondary" id="cancelMakeReportBtn">Cancel</button>

                <div class="rpt-footer-right">
                    <button type="button" class="btn-save-draft" id="saveDraftBtn">
                         Save as Draft
                    </button>
                    <button type="button" class="btn-submit-report" id="submitReportBtn">
                         Submit Report
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ==================== VIEW REPORT DETAILS MODAL ==================== -->
<div class="cls-modal-backdrop" id="viewReportModal" aria-hidden="true">
    <div class="cls-modal-card" role="dialog" aria-modal="true" aria-labelledby="viewRptTitle">
        <div class="cls-modal-header">
            <div>
                <div class="cls-modal-eyebrow-row">
                    <span id="viewRptCodes"></span>
                    <span class="class-status-pill" id="viewRptStatus"></span>
                </div>
                <h2 class="cls-modal-title" id="viewRptTitle"></h2>
                <p class="cls-modal-loc" id="viewRptMeta"></p>
            </div>
            <button type="button" class="cls-modal-close" id="closeViewRptBtn" aria-label="Close modal">&times;</button>
        </div>

        <div class="cls-overview-box">
            <span class="cls-box-eyebrow">SESSION SUMMARY NOTES</span>
            <p class="cls-modal-desc" id="viewRptNotes"></p>
        </div>

        <div class="cls-overview-box rpt-revision-note" id="viewRptRevisionBox" hidden>
            <span class="cls-box-eyebrow">ADMIN REMARKS (FOR REVISION)</span>
            <p class="cls-modal-desc" id="viewRptAdminRemarks"></p>
        </div>

        <div class="cls-detail-rows">
            <div class="cls-detail-row">
                <span class="cls-row-label">Attendance Recorded</span>
                <strong class="cls-row-val" id="viewRptAttendance"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Photo Proof Status</span>
                <strong class="cls-row-val" id="viewRptPhotoStatus"></strong>
            </div>
            <div class="cls-detail-row">
                <span class="cls-row-label">Timestamp</span>
                <strong class="cls-row-val" id="viewRptTimestamp"></strong>
            </div>
        </div>

        <div class="cls-modal-footer">
            <button type="button" class="btn-cls-secondary" id="cancelViewRptBtn">Close</button>
        </div>
    </div>
</div>

<script>
window.ROSTERS_BY_CLASS = <?php echo json_encode($rosters_by_class, JSON_UNESCAPED_UNICODE); ?>;
</script>
<?php include __DIR__ . '/includes/logout_modal.php'; ?>
<script src="assets/js/teacher.js?v=3"></script>

<?php include __DIR__ . '/includes/teacher_profile_modal.php'; ?>

</body>
</html>