<?php
/**
 * Shared admin sidebar.  Set $active_nav before including, e.g. $active_nav = 'classes';
 * Requires $conn and an active session.
 */
$active_nav = $active_nav ?? 'dashboard';

$coordinator_name = 'Albay Coordinator';
$coordinator_hub  = 'Legazpi Hub';
if (!empty($_SESSION['account_id'])) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM administrators WHERE account_id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['account_id']);
    $stmt->execute();
    if ($adm = $stmt->get_result()->fetch_assoc()) {
        $coordinator_name = trim($adm['first_name'] . ' ' . $adm['last_name']);
    }
    $stmt->close();
}
$name_parts = preg_split('/\s+/', trim($coordinator_name));
$initials = strtoupper(substr($name_parts[0] ?? 'A', 0, 1) . substr($name_parts[1] ?? 'C', 0, 1));

$nav_items = [
    'dashboard' => ['admin_dashboard.php', 'Dashboard',     '<rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect>'],
    'users'     => ['admin_users.php',     'User Accounts', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>'],
    'directory' => ['admin_directory.php', 'Directory',     '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>'],
    'classes'   => ['admin_classes.php',   'Class',         '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>'],
    'reports'   => ['admin_reports.php',   'Reports',       '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>'],
];
?>
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
                <?php foreach ($nav_items as $key => [$href, $label, $icon]): ?>
                    <a href="<?php echo $href; ?>" class="nav-item<?php echo $key === $active_nav ? ' active' : ''; ?>">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon; ?></svg>
                        <span><?php echo $label; ?></span>
                    </a>
                <?php endforeach; ?>
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
