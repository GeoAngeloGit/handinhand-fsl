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
            if (!empty($adm['account_id'])) {
                $current_account_id = (int)$adm['account_id'];
            }
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

// ==================== FETCH LIVE DIRECTORY DATA FROM MYSQL ====================
$learners_list = [];
$teachers_list = [];
$admins_list   = [];

if (isset($conn) && $conn instanceof mysqli) {
    // 1. Learners
    try {
        $sql_lrn = "
            SELECT 
                l.*,
                a.email,
                a.username,
                a.created_at,
                YEAR(a.created_at) AS acc_year
            FROM learners l
            JOIN accounts a ON a.account_id = l.account_id
            ORDER BY l.learner_id ASC
        ";
        if ($res = $conn->query($sql_lrn)) {
            while ($r = $res->fetch_assoc()) {
                $lid = (int)$r['learner_id'];
                $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
                $full_name = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '') . $mid);
                $lrn_code  = sprintf('LRN-%05d', $lid);
                $acc_code  = sprintf('ACC-%s-%03d', $r['acc_year'] ?: date('Y'), (int)$r['account_id']);

                $raw_status = strtolower((string)($r['account_status'] ?? $r['status'] ?? 'active'));
                $status     = ($raw_status === 'inactive') ? 'Inactive' : 'Active';
                $loc        = !empty($r['barangay_id']) ? 'Barangay #' . $r['barangay_id'] : 'Albay';

                $class_count = 0;
                try {
                    if ($ec = $conn->query("SELECT COUNT(*) AS cnt FROM enrollments WHERE learner_id = {$lid}")) {
                        $class_count = (int)($ec->fetch_assoc()['cnt'] ?? 0);
                    }
                } catch (Throwable $ignore) {}

                $learners_list[] = [
                    'raw_id'        => $lid,
                    'account_id'    => (int)$r['account_id'],
                    'code'          => $lrn_code,
                    'account_code'  => $acc_code,
                    'full_name'     => $full_name,
                    'initials'      => strtoupper(substr((string)($r['first_name'] ?? 'L'), 0, 1) . substr((string)($r['last_name'] ?? 'N'), 0, 1)),
                    'hearing'       => $r['hearing_status'] ?: 'Unspecified',
                    'speech'        => $r['speech_status'] ?? ($r['communication_mode'] ?? 'Unspecified'),
                    'location'      => $loc,
                    'contact'       => $r['contact_number'] ?: '—',
                    'email'         => $r['email'] ?? '—',
                    'classes_count' => $class_count,
                    'status'        => $status,
                ];
            }
        }
    } catch (Throwable $e) {
        $learners_list = [];
    }

    // 2. Teachers
    try {
        $sql_tch = "
            SELECT 
                t.*,
                a.email,
                a.username,
                a.created_at,
                YEAR(a.created_at) AS acc_year
            FROM teachers t
            JOIN accounts a ON a.account_id = t.account_id
            ORDER BY t.teacher_id ASC
        ";
        if ($res = $conn->query($sql_tch)) {
            while ($r = $res->fetch_assoc()) {
                $tid = (int)$r['teacher_id'];
                $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
                $full_name = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '') . $mid);
                $tch_code  = sprintf('TCH-%05d', $tid);
                $acc_code  = sprintf('ACC-%s-%03d', $r['acc_year'] ?: date('Y'), (int)$r['account_id']);

                $raw_status = strtolower((string)($r['account_status'] ?? $r['status'] ?? 'active'));
                $status     = ($raw_status === 'inactive') ? 'Inactive' : 'Active';

                $class_count = 0;
                try {
                    if ($cc = $conn->query("SELECT COUNT(*) AS cnt FROM classes WHERE teacher_id = {$tid}")) {
                        $class_count = (int)($cc->fetch_assoc()['cnt'] ?? 0);
                    }
                } catch (Throwable $ignore) {}

                $teachers_list[] = [
                    'raw_id'         => $tid,
                    'account_id'     => (int)$r['account_id'],
                    'code'           => $tch_code,
                    'account_code'   => $acc_code,
                    'full_name'      => $full_name,
                    'initials'       => strtoupper(substr((string)($r['first_name'] ?? 'T'), 0, 1) . substr((string)($r['last_name'] ?? 'H'), 0, 1)),
                    'specialization' => $r['specialization'] ?: 'General FSL Instruction',
                    'experience'     => (int)($r['years_experience'] ?? 0),
                    'contact'        => $r['contact_number'] ?: '—',
                    'email'          => $r['email'] ?? '—',
                    'classes_count'  => $class_count,
                    'status'         => $status,
                ];
            }
        }
    } catch (Throwable $e) {
        $teachers_list = [];
    }

    // 3. Admins
    try {
        $sql_adm = "
            SELECT 
                adm.*,
                a.username,
                a.email,
                a.created_at,
                YEAR(a.created_at) AS acc_year
            FROM administrators adm
            JOIN accounts a ON a.account_id = adm.account_id
            ORDER BY adm.admin_id ASC
        ";
        if ($res = $conn->query($sql_adm)) {
            while ($r = $res->fetch_assoc()) {
                $aid = (int)$r['admin_id'];
                $mid = !empty($r['middle_name']) ? ' ' . strtoupper(substr(trim((string)$r['middle_name']), 0, 1)) . '.' : '';
                $full_name = trim(($r['last_name'] ?? '') . ', ' . ($r['first_name'] ?? '') . $mid);
                $adm_code  = sprintf('ADM-%05d', $aid);
                $acc_code  = sprintf('ACC-%s-%03d', $r['acc_year'] ?: date('Y'), (int)$r['account_id']);

                $raw_status = strtolower((string)($r['account_status'] ?? $r['status'] ?? 'active'));
                $status     = ($raw_status === 'inactive') ? 'Inactive' : 'Active';

                $raw_user  = (string)($r['username'] ?? '');
                $uname     = $raw_user !== '' ? (str_starts_with($raw_user, '@') ? $raw_user : '@' . $raw_user) : '—';
                $date_add  = !empty($r['created_at']) ? date('M j, Y', strtotime((string)$r['created_at'])) : '—';
                $loc       = $r['location'] ?? ($r['hub_assignment'] ?? (!empty($r['barangay_id']) ? 'Barangay #' . $r['barangay_id'] : 'Rawis, Legazpi City'));

                $admins_list[] = [
                    'raw_id'       => $aid,
                    'account_id'   => (int)$r['account_id'],
                    'code'         => $adm_code,
                    'account_code' => $acc_code,
                    'full_name'    => $full_name,
                    'initials'     => strtoupper(substr((string)($r['first_name'] ?? 'A'), 0, 1) . substr((string)($r['last_name'] ?? 'D'), 0, 1)),
                    'username'     => $uname,
                    'location'     => $loc,
                    'email'        => $r['email'] ?? '—',
                    'date_added'   => $date_add,
                    'status'       => $status,
                    'is_you'       => ($current_account_id > 0 && (int)$r['account_id'] === $current_account_id),
                ];
            }
        }
    } catch (Throwable $e) {
        $admins_list = [];
    }
}

function count_active_inactive(array $rows): array {
    $active = 0;
    $inactive = 0;
    foreach ($rows as $r) {
        if (($r['status'] ?? 'Active') === 'Active') {
            $active++;
        } else {
            $inactive++;
        }
    }
    return [$active, $inactive];
}

[$lrn_active, $lrn_inactive] = count_active_inactive($learners_list);
[$tch_active, $tch_inactive] = count_active_inactive($teachers_list);
[$adm_active, $adm_inactive] = count_active_inactive($admins_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Directory | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/admin.css?v=5">
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

                    <a href="admin_directory.php" class="nav-item active">
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
            <div class="coordinator-card" id="sidebarCoordCard" style="cursor: pointer;" title="View Profile Details">
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
                <span class="portal-eyebrow">ADMINISTRATION PORTAL &bull; ACCOUNT REGISTRY</span>
                <h1 class="page-title">Manage <em>Directory</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- Unified Top Control Card -->
        <section class="dir-control-card">
            <div class="dir-tabs-bar">
                <div class="dir-segmented-tabs" role="tablist">
                    <button type="button" class="dir-tab-btn active" data-tab="learners" role="tab" aria-selected="true">
                        <span>Learners</span>
                        <span class="tab-count-badge"><?php echo count($learners_list); ?></span>
                    </button>

                    <button type="button" class="dir-tab-btn" data-tab="teachers" role="tab" aria-selected="false">
                        <span>Teachers</span>
                        <span class="tab-count-badge"><?php echo count($teachers_list); ?></span>
                    </button>

                    <button type="button" class="dir-tab-btn" data-tab="admins" role="tab" aria-selected="false">
                        <span>Admins</span>
                        <span class="tab-count-badge"><?php echo count($admins_list); ?></span>
                    </button>
                </div>

                <button type="button" class="btn-new-admin" id="openNewAdminModalBtn" style="display: none;">
                    <span>+</span> New Admin Account
                </button>
            </div>

            <div class="dir-filter-bar">
                <div class="dir-sub-summary">
                    <strong id="dirSectionTitle">Learners Directory</strong>
                    <span class="dir-active-counts" id="dirActiveSummary">
                        <?php echo $lrn_active; ?> Active &bull; <?php echo $lrn_inactive; ?> Inactive
                    </span>
                </div>

                <div class="dir-filter-controls">
                    <div class="dir-search-box">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="dirSearchInput" placeholder="Search ID, name, or email...">
                    </div>

                    <div class="dir-sort-group">
                        <span class="dir-sort-label">SORT</span>
                        <select id="dirSortSelect">
                            <option value="id-asc">Learner ID (Ascending)</option>
                            <option value="id-desc">Learner ID (Descending)</option>
                            <option value="name-asc">Full Name (A–Z)</option>
                            <option value="name-desc">Full Name (Z–A)</option>
                        </select>
                    </div>

                    <div class="dir-status-pills" role="group" aria-label="Status Filter">
                        <button type="button" class="status-pill-btn active" data-status="ALL">All</button>
                        <button type="button" class="status-pill-btn" data-status="Active">Active</button>
                        <button type="button" class="status-pill-btn" data-status="Inactive">Inactive</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==================== TAB 1: LEARNERS DIRECTORY ==================== -->
        <section class="dir-tab-panel active" id="panel-learners"
                 data-title="Learners Directory"
                 data-summary="<?php echo $lrn_active; ?> Active • <?php echo $lrn_inactive; ?> Inactive"
                 data-id-label="Learner ID">

            <div class="dir-table-header dir-grid-learners">
                <span class="col-head-active">LEARNER ID</span>
                <span>FULL NAME</span>
                <span>COMMUNICATION PROFILE</span>
                <span>LOCATION</span>
                <span>CONTACT &amp; EMAIL</span>
                <span>ENROLLED CLASSES</span>
                <span class="align-right">STATUS</span>
            </div>

            <div class="dir-rows-stack">
                <?php if (empty($learners_list)): ?>
                    <div class="empty-filter-state">No learner records found in the database.</div>
                <?php else: ?>
                    <?php foreach ($learners_list as $lrn): ?>
                        <?php
                            $is_active = ($lrn['status'] === 'Active');
                            $modal_payload = htmlspecialchars(json_encode([
                                'type'         => 'learner',
                                'id_label'     => 'Learner ID',
                                'code'         => $lrn['code'],
                                'account_code' => $lrn['account_code'],
                                'initials'     => $lrn['initials'],
                                'full_name'    => $lrn['full_name'],
                                'status'       => $lrn['status'],
                                'fields'       => [
                                    ['label' => 'Learner ID',            'value' => $lrn['code']],
                                    ['label' => 'Full Name',             'value' => $lrn['full_name']],
                                    ['label' => 'Communication Profile', 'value' => $lrn['hearing'] . ' • ' . $lrn['speech']],
                                    ['label' => 'Location',              'value' => $lrn['location']],
                                    ['label' => 'Contact',               'value' => $lrn['contact']],
                                    ['label' => 'Email',                 'value' => $lrn['email']],
                                    ['label' => 'Enrolled Classes',      'value' => $lrn['classes_count'] . ' Active'],
                                ]
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <article class="dir-row-card dir-grid-learners <?php echo $is_active ? 'accent-green' : 'accent-muted'; ?>"
                                 data-code="<?php echo htmlspecialchars(strtolower($lrn['code'])); ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($lrn['full_name'])); ?>"
                                 data-email="<?php echo htmlspecialchars(strtolower($lrn['email'])); ?>"
                                 data-status="<?php echo htmlspecialchars($lrn['status']); ?>"
                                 data-modal="<?php echo $modal_payload; ?>">

                            <div>
                                <span class="dir-id-pill pill-lrn"><?php echo htmlspecialchars($lrn['code']); ?></span>
                            </div>

                            <div class="dir-fullname"><?php echo htmlspecialchars($lrn['full_name']); ?></div>

                            <div class="dir-comm-stack">
                                <span class="comm-badge comm-hearing"><?php echo htmlspecialchars($lrn['hearing']); ?></span>
                                <span class="comm-badge comm-speech"><?php echo htmlspecialchars($lrn['speech']); ?></span>
                            </div>

                            <div class="dir-muted-text"><?php echo htmlspecialchars($lrn['location']); ?></div>

                            <div class="dir-contact-stack">
                                <strong><?php echo htmlspecialchars($lrn['contact']); ?></strong>
                                <span><?php echo htmlspecialchars($lrn['email']); ?></span>
                            </div>

                            <div>
                                <span class="dir-class-pill pill-lrn"><?php echo (int)$lrn['classes_count']; ?> Active</span>
                            </div>

                            <div class="align-right">
                                <span class="dir-status-badge <?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
                                    &bull; <?php echo htmlspecialchars($lrn['status']); ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ==================== TAB 2: TEACHERS DIRECTORY ==================== -->
        <section class="dir-tab-panel" id="panel-teachers" style="display: none;"
                 data-title="Teachers Directory"
                 data-summary="<?php echo $tch_active; ?> Active • <?php echo $tch_inactive; ?> Inactive"
                 data-id-label="Teacher ID">

            <div class="dir-table-header dir-grid-teachers">
                <span class="col-head-active">TEACHER ID</span>
                <span>FULL NAME</span>
                <span>SPECIALIZATION</span>
                <span>EXPERIENCE</span>
                <span>EMAIL &amp; CONTACT</span>
                <span>ASSIGNED CLASSES</span>
                <span class="align-right">STATUS</span>
            </div>

            <div class="dir-rows-stack">
                <?php if (empty($teachers_list)): ?>
                    <div class="empty-filter-state">No teacher records found in the database.</div>
                <?php else: ?>
                    <?php foreach ($teachers_list as $tch): ?>
                        <?php
                            $is_active = ($tch['status'] === 'Active');
                            $modal_payload = htmlspecialchars(json_encode([
                                'type'         => 'teacher',
                                'id_label'     => 'Teacher ID',
                                'code'         => $tch['code'],
                                'account_code' => $tch['account_code'],
                                'initials'     => $tch['initials'],
                                'full_name'    => $tch['full_name'],
                                'status'       => $tch['status'],
                                'fields'       => [
                                    ['label' => 'Teacher ID',          'value' => $tch['code']],
                                    ['label' => 'Full Name',           'value' => $tch['full_name']],
                                    ['label' => 'FSL Specialization',  'value' => $tch['specialization']],
                                    ['label' => 'Years of Experience', 'value' => $tch['experience'] . ' years'],
                                    ['label' => 'Email',               'value' => $tch['email']],
                                    ['label' => 'Contact Number',      'value' => $tch['contact']],
                                    ['label' => 'Enrolled Classes',    'value' => $tch['classes_count'] . ' Active'],
                                ]
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <article class="dir-row-card dir-grid-teachers <?php echo $is_active ? 'accent-green' : 'accent-muted'; ?>"
                                 data-code="<?php echo htmlspecialchars(strtolower($tch['code'])); ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($tch['full_name'])); ?>"
                                 data-email="<?php echo htmlspecialchars(strtolower($tch['email'])); ?>"
                                 data-status="<?php echo htmlspecialchars($tch['status']); ?>"
                                 data-modal="<?php echo $modal_payload; ?>">

                            <div>
                                <span class="dir-id-pill pill-tch"><?php echo htmlspecialchars($tch['code']); ?></span>
                            </div>

                            <div class="dir-fullname"><?php echo htmlspecialchars($tch['full_name']); ?></div>

                            <div class="dir-spec-text"><?php echo htmlspecialchars($tch['specialization']); ?></div>

                            <div>
                                <span class="exp-pill"><?php echo (int)$tch['experience']; ?> yrs</span>
                            </div>

                            <div class="dir-contact-stack">
                                <strong><?php echo htmlspecialchars($tch['email']); ?></strong>
                                <span><?php echo htmlspecialchars($tch['contact']); ?></span>
                            </div>

                            <div>
                                <span class="dir-class-pill pill-tch"><?php echo (int)$tch['classes_count']; ?> Classes</span>
                            </div>

                            <div class="align-right">
                                <span class="dir-status-badge <?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
                                    &bull; <?php echo htmlspecialchars($tch['status']); ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ==================== TAB 3: ADMINS DIRECTORY ==================== -->
        <section class="dir-tab-panel" id="panel-admins" style="display: none;"
                 data-title="Administrators Directory"
                 data-summary="<?php echo $adm_active; ?> Active • <?php echo $adm_inactive; ?> Inactive"
                 data-id-label="Admin ID">

            <div class="dir-table-header dir-grid-admins">
                <span class="col-head-active">ADMIN ID</span>
                <span>FULL NAME</span>
                <span>USERNAME</span>
                <span>EMAIL</span>
                <span>DATE ADDED</span>
                <span class="align-right">STATUS</span>
            </div>

            <div class="dir-rows-stack">
                <?php if (empty($admins_list)): ?>
                    <div class="empty-filter-state">No administrator records found in the database.</div>
                <?php else: ?>
                    <?php foreach ($admins_list as $adm): ?>
                        <?php
                            $is_active = ($adm['status'] === 'Active');
                            $modal_payload = htmlspecialchars(json_encode([
                                'type'         => 'admin',
                                'id_label'     => 'Admin ID',
                                'code'         => $adm['code'],
                                'account_code' => $adm['account_code'],
                                'initials'     => $adm['initials'],
                                'full_name'    => $adm['full_name'],
                                'status'       => $adm['status'],
                                'fields'       => [
                                    ['label' => 'Admin ID',   'value' => $adm['code']],
                                    ['label' => 'Full Name',  'value' => $adm['full_name']],
                                    ['label' => 'Username',   'value' => $adm['username']],
                                    ['label' => 'Location',   'value' => $adm['location']],
                                    ['label' => 'Email',      'value' => $adm['email']],
                                    ['label' => 'Date Added', 'value' => $adm['date_added']],
                                ]
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                        <article class="dir-row-card dir-grid-admins <?php echo $is_active ? 'accent-green' : 'accent-muted'; ?>"
                                 data-code="<?php echo htmlspecialchars(strtolower($adm['code'])); ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($adm['full_name'])); ?>"
                                 data-email="<?php echo htmlspecialchars(strtolower($adm['email'])); ?>"
                                 data-status="<?php echo htmlspecialchars($adm['status']); ?>"
                                 data-modal="<?php echo $modal_payload; ?>">

                            <div>
                                <span class="dir-id-pill pill-adm"><?php echo htmlspecialchars($adm['code']); ?></span>
                            </div>

                            <div class="dir-fullname">
                                <span><?php echo htmlspecialchars($adm['full_name']); ?></span>
                                <?php if (!empty($adm['is_you'])): ?>
                                    <span class="you-pill">You</span>
                                <?php endif; ?>
                            </div>

                            <div class="dir-username-text"><?php echo htmlspecialchars($adm['username']); ?></div>

                            <div class="dir-muted-text"><?php echo htmlspecialchars($adm['email']); ?></div>

                            <div class="dir-muted-text"><?php echo htmlspecialchars($adm['date_added']); ?></div>

                            <div class="align-right">
                                <span class="dir-status-badge <?php echo $is_active ? 'status-active' : 'status-inactive'; ?>">
                                    &bull; <?php echo htmlspecialchars($adm['status']); ?>
                                </span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <div class="empty-filter-state" id="dirEmptyState" style="display: none; margin-top: 12px;">
            No directory profiles match your search or status filter.
        </div>

    </main>
</div>

<!-- ==================== PROFILE DETAILS MODAL (ACTIVE & INACTIVE) ==================== -->
<div class="dir-modal-backdrop" id="profileDetailsModal" aria-hidden="true">
    <div class="dir-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalProfileName">
        <div class="dir-modal-header">
            <div class="modal-identity-left">
                <div class="modal-initials-circle" id="modalInitials"></div>
                <div class="modal-meta-block">
                    <div class="modal-top-codes">
                        <span id="modalCodeLine"></span>
                        <span class="dir-status-badge status-active" id="modalStatusBadge"></span>
                    </div>
                    <h2 class="modal-fullname" id="modalProfileName"></h2>
                </div>
            </div>

            <button type="button" class="modal-close-circle" id="closeProfileModalBtn" aria-label="Close modal">&times;</button>
        </div>

        <div class="modal-fields-stack" id="modalFieldsStack"></div>

        <div class="dir-modal-footer" id="modalFooterBar">
            <div class="modal-footer-left">
                <button type="button" class="btn-delete-user" id="modalDeleteUserBtn" style="display: none;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Delete User</span>
                </button>
            </div>

            <div class="modal-footer-right">
                <button type="button" class="btn-modal-close" id="modalCancelBtn">Close</button>
                <button type="button" class="btn-modal-edit" id="modalEditSaveBtn">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9"></path>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                    </svg>
                    <span id="modalEditBtnLabel">Edit Profile</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== + NEW ADMIN ACCOUNT MODAL ==================== -->
<div class="dir-modal-backdrop" id="newAdminModal" aria-hidden="true">
    <div class="dir-modal-card" role="dialog" aria-modal="true" aria-labelledby="newAdminModalTitle">
        <div class="dir-modal-header">
            <div class="modal-identity-left">
                <div class="modal-initials-circle theme-berry">+</div>
                <div class="modal-meta-block">
                    <span class="portal-eyebrow" style="margin-bottom: 2px;">ADMINISTRATION PORTAL &bull; NEW RECORD</span>
                    <h2 class="modal-fullname" id="newAdminModalTitle">New Admin Account</h2>
                </div>
            </div>
            <button type="button" class="modal-close-circle" id="closeNewAdminModalBtn" aria-label="Close modal">&times;</button>
        </div>

        <form id="newAdminForm" action="actions/admin_create_admin.php" method="POST">
            <div class="new-admin-grid">
                <div class="na-field">
                    <label for="naFirstName">FIRST NAME</label>
                    <input type="text" id="naFirstName" name="first_name" placeholder="e.g., Camille" required>
                </div>
                <div class="na-field">
                    <label for="naLastName">LAST NAME</label>
                    <input type="text" id="naLastName" name="last_name" placeholder="e.g., Ortega" required>
                </div>
                <div class="na-field">
                    <label for="naUsername">USERNAME</label>
                    <input type="text" id="naUsername" name="username" placeholder="e.g., tabaco.coordinator" required>
                </div>
                <div class="na-field">
                    <label for="naEmail">EMAIL ADDRESS</label>
                    <input type="email" id="naEmail" name="email" placeholder="e.g., tabaco.admin@handinhand.ph" required>
                </div>
                <div class="na-field">
                    <label for="naHub">LOCATION / HUB</label>
                    <select id="naHub" name="hub_assignment">
                        <option value="Rawis, Legazpi City">Rawis, Legazpi City</option>
                        <option value="Sagpon, Daraga">Sagpon, Daraga</option>
                        <option value="San Lorenzo, Tabaco City">San Lorenzo, Tabaco City</option>
                        <option value="Centro, Ligao City">Centro, Ligao City</option>
                        <option value="Centro Oriental, Polangui">Centro Oriental, Polangui</option>
                    </select>
                </div>
                <div class="na-field">
                    <label for="naPassword">TEMPORARY PASSWORD</label>
                    <input type="password" id="naPassword" name="password" placeholder="Min. 8 characters" required>
                </div>
            </div>

            <div class="dir-modal-footer" style="margin-top: 16px;">
                <div></div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-modal-close" id="cancelNewAdminBtn">Cancel</button>
                    <button type="submit" class="btn-modal-edit">Create Admin Account</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/admin_directory.js?v=2"></script>

</body>
</html>