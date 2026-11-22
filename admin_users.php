<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db_connect.php';

if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 1);
}

if (!empty($_SESSION['logged_in']) && function_exists('require_login')) {
    require_login([ROLE_ADMIN]);
}

$coordinator_name = 'Albay Coordinator';
$coordinator_hub  = 'Legazpi Hub';

if (!empty($_SESSION['account_id']) && $conn) {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name FROM administrators WHERE account_id = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['account_id']);
        $stmt->execute();
        if ($adm = $stmt->get_result()->fetch_assoc()) {
            $coordinator_name = trim($adm['first_name'] . ' ' . $adm['last_name']);
        }
        $stmt->close();
    } catch (Throwable $e) {
        // Keep defaults
    }
}

$name_parts = preg_split('/\s+/', trim($coordinator_name));
$initials   = strtoupper(substr($name_parts[0] ?? 'A', 0, 1) . substr($name_parts[1] ?? 'C', 0, 1));

// ==================== FETCH ACCOUNTS FROM MYSQL ====================
$accounts_list = [];
$count_total    = 0;
$count_learners = 0;
$count_teachers = 0;
$count_admins   = 0;

if ($conn) {
    try {
        $sql = "
            SELECT 
                account_id,
                role_id,
                username,
                email,
                DATE(created_at) AS created_date,
                YEAR(created_at) AS created_year
            FROM accounts
            ORDER BY account_id ASC
        ";
        $res = $conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rid  = (int)$row['role_id'];
            $year = $row['created_year'] ?: '2026';
            $code = sprintf('ACC-%s-%03d', $year, (int)$row['account_id']);

            if ($rid === 1) {
                $role_code = 'ROLE-ADM';
                $count_admins++;
            } elseif ($rid === 2) {
                $role_code = 'ROLE-TCH';
                $count_teachers++;
            } else {
                $role_code = 'ROLE-LRN';
                $count_learners++;
            }
            $count_total++;

            $uname = str_starts_with($row['username'], '@') ? $row['username'] : '@' . $row['username'];

            $accounts_list[] = [
                'raw_id'       => (int)$row['account_id'],
                'account_code' => $code,
                'role_code'    => $role_code,
                'username'     => $uname,
                'email'        => $row['email'],
                'created_at'   => $row['created_date'] ?: '2026-11-21'
            ];
        }
    } catch (Throwable $e) {
        // Fall back to sample data below
    }
}

if (empty($accounts_list)) {
    $count_total    = 18;
    $count_learners = 11;
    $count_teachers = 5;
    $count_admins   = 2;

    $accounts_list = [
        [
            'raw_id'       => 18,
            'account_code' => 'ACC-2026-018',
            'role_code'    => 'ROLE-ADM',
            'username'     => '@albay.coordinator',
            'email'        => 'coordinator@handinhand.ph',
            'created_at'   => '2026-11-21'
        ],
        [
            'raw_id'       => 20,
            'account_code' => 'ACC-2026-020',
            'role_code'    => 'ROLE-LRN',
            'username'     => '@june.devera',
            'email'        => 'june.devera@handinhand.ph',
            'created_at'   => '2026-11-21'
        ],
        [
            'raw_id'       => 21,
            'account_code' => 'ACC-2026-021',
            'role_code'    => 'ROLE-ADM',
            'username'     => '@daraga.coordinator',
            'email'        => 'daraga.admin@handinhand.ph',
            'created_at'   => '2026-11-19'
        ],
        [
            'raw_id'       => 22,
            'account_code' => 'ACC-2026-022',
            'role_code'    => 'ROLE-TCH',
            'username'     => '@elena.reyes',
            'email'        => 'elena.reyes@handinhand.ph',
            'created_at'   => '2026-11-18'
        ],
        [
            'raw_id'       => 23,
            'account_code' => 'ACC-2026-023',
            'role_code'    => 'ROLE-LRN',
            'username'     => '@carlo.mendoza',
            'email'        => 'carlo.mendoza@handinhand.ph',
            'created_at'   => '2026-11-17'
        ]
    ];
}

$displayed_total = count($accounts_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Accounts | HandInHand FSL</title>

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
                    <a href="admin_dashboard.php" class="nav-item">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="admin_users.php" class="nav-item active">
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
                <span class="portal-eyebrow">ADMINISTRATION PORTAL &bull; ACCOUNT REGISTRY</span>
                <h1 class="page-title">Manage <em>User Accounts</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <!-- 4 Role KPI Cards -->
        <section class="kpi-grid-4">
            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL ACCOUNTS</span>
                    <span class="mini-role-pill pill-neutral">ALL ROLES</span>
                </div>
                <div class="kpi-value"><?php echo number_format($count_total); ?></div>
                <p class="kpi-sub">Combined Learner, Teacher &amp; Admin</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL LEARNERS</span>
                    <span class="mini-role-pill pill-lrn">LRN</span>
                </div>
                <div class="kpi-value"><?php echo number_format($count_learners); ?></div>
                <p class="kpi-sub">Registered Albay learners</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL TEACHERS</span>
                    <span class="mini-role-pill pill-tch">TCH</span>
                </div>
                <div class="kpi-value"><?php echo number_format($count_teachers); ?></div>
                <p class="kpi-sub">Albay FSL teachers</p>
            </article>

            <article class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">TOTAL ADMINS</span>
                    <span class="mini-role-pill pill-adm">ADM</span>
                </div>
                <div class="kpi-value"><?php echo number_format($count_admins); ?></div>
                <p class="kpi-sub">Provincial &amp; hub administrators</p>
            </article>
        </section>

        <!-- Toolbar Row: Count + Toggle Filters Button -->
        <div class="registry-toolbar">
            <span class="accounts-counter" id="accountsCounter">
                <strong><?php echo $count_total; ?></strong> of <?php echo $count_total; ?> accounts
            </span>

            <button type="button" class="filter-toggle-btn" id="filterToggleBtn" aria-expanded="true">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                <span>Filters</span>
                <span class="chevron-icon" id="filterChevron">&#9662;</span>
            </button>
        </div>

        <!-- Collapsible Filter Card -->
        <section class="filter-panel-card" id="filterPanelCard">
            <div class="filter-controls-row">
                <div class="filter-field">
                    <label for="roleFilterSelect">ROLE ID</label>
                    <select id="roleFilterSelect">
                        <option value="ALL">All Roles (Combined)</option>
                        <option value="ROLE-ADM">ROLE-ADM (Administrators)</option>
                        <option value="ROLE-LRN">ROLE-LRN (Learners)</option>
                        <option value="ROLE-TCH">ROLE-TCH (Teachers)</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="accountSearchInput">ACCOUNT ID</label>
                    <div class="search-input-wrap">
                        <input type="text" id="accountSearchInput" placeholder="Search Account ID...">
                        <svg class="search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                </div>

                <div class="filter-field">
                    <label for="createdDateInput">CREATED AT</label>
                    <input type="date" id="createdDateInput">
                </div>

                <button type="button" class="btn-reset-filters" id="resetFiltersBtn">Reset Filters</button>
            </div>

            <div class="quick-date-row">
                <div class="quick-date-pills">
                    <span class="quick-date-label">QUICK DATE:</span>
                    <button type="button" class="qdate-pill active" data-qdate="ALL">All Dates</button>
                    <button type="button" class="qdate-pill" data-qdate="2026-11-21">Today (2026-11-21)</button>
                    <button type="button" class="qdate-pill" data-qdate="2026-11">Nov 2026</button>
                    <button type="button" class="qdate-pill" data-qdate="2026-10">Oct 2026</button>
                </div>

                <button type="button" class="hide-filters-link" id="hideFiltersBtn">Hide Filters &#9652;</button>
            </div>
        </section>

        <!-- Account Registry Table Header + Card Rows -->
        <section class="registry-table-section" data-total-count="<?php echo $count_total; ?>">
            <div class="registry-grid-header">
                <button type="button" class="sort-col-btn" data-sort="account">ACCOUNT ID <span class="sort-arrows">&#8597;</span></button>
                <button type="button" class="sort-col-btn" data-sort="role">ROLE ID <span class="sort-arrows">&#8597;</span></button>
                <button type="button" class="sort-col-btn" data-sort="username">USERNAME <span class="sort-arrows">&#8597;</span></button>
                <button type="button" class="sort-col-btn" data-sort="email">EMAIL <span class="sort-arrows">&#8597;</span></button>
                <button type="button" class="sort-col-btn align-right" data-sort="date">CREATED AT <span class="sort-arrows">&#8593;</span></button>
            </div>

            <div class="registry-rows-stack" id="registryRowsStack">
                <?php foreach ($accounts_list as $acc): ?>
                    <?php
                        $badge_class = match ($acc['role_code']) {
                            'ROLE-ADM' => 'badge-adm',
                            'ROLE-TCH' => 'badge-tch',
                            default    => 'badge-lrn',
                        };
                    ?>
                    <article class="account-row-card"
                             data-account="<?php echo htmlspecialchars(strtolower($acc['account_code'])); ?>"
                             data-role="<?php echo htmlspecialchars($acc['role_code']); ?>"
                             data-username="<?php echo htmlspecialchars(strtolower($acc['username'])); ?>"
                             data-email="<?php echo htmlspecialchars(strtolower($acc['email'])); ?>"
                             data-date="<?php echo htmlspecialchars($acc['created_at']); ?>">

                        <div class="col-account">
                            <span class="acc-code-text"><?php echo htmlspecialchars($acc['account_code']); ?></span>
                            <button type="button" class="copy-id-btn" data-copy="<?php echo htmlspecialchars($acc['account_code']); ?>" title="Copy Account ID">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="col-role">
                            <span class="registry-role-badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($acc['role_code']); ?>
                            </span>
                        </div>

                        <div class="col-username">
                            <?php echo htmlspecialchars($acc['username']); ?>
                        </div>

                        <div class="col-email">
                            <?php echo htmlspecialchars($acc['email']); ?>
                        </div>

                        <div class="col-date">
                            <?php echo htmlspecialchars($acc['created_at']); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="empty-filter-state" id="emptyFilterState" style="display: none;">
                No matching user accounts found for the selected filter criteria.
            </div>
        </section>

    </main>
</div>

<script src="assets/js/admin_users.js?v=1"></script>

</body>
</html>
