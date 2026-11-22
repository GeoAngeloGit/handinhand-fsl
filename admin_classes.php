<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db_connect.php';
require_login([ROLE_ADMIN]);

$active_nav = 'classes';

// One-time flash messages from actions/create_class.php
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---------- Status filter (whitelisted) ----------
$filters = [
    'active'    => 'Active',
    'all'       => 'All',
    'Draft'     => 'Draft',
    'Open'      => 'Open',
    'Ongoing'   => 'Ongoing',
    'Completed' => 'Completed',
    'Cancelled' => 'Cancelled',
];
$filter = $_GET['status'] ?? 'active';
if (!isset($filters[$filter])) {
    $filter = 'active';
}

$where  = '';
$params = [];
if ($filter === 'active') {
    $where = "WHERE c.status IN ('Open','Ongoing')";
} elseif ($filter !== 'all') {
    $where = 'WHERE c.status = ?';
    $params[] = $filter;
}

$sql = "
    SELECT
        c.class_id, c.class_title, c.description, c.max_capacity, c.status, c.created_at,
        (SELECT COUNT(*) FROM class_enrollments e
          WHERE e.class_id = c.class_id AND e.status = 'enrolled') AS enrolled_count,
        (SELECT COUNT(*) FROM class_schedule s WHERE s.class_id = c.class_id) AS session_count,
        (SELECT CONCAT(s.session_date, ' ', s.start_time) FROM class_schedule s
          WHERE s.class_id = c.class_id AND s.status = 'scheduled' AND s.session_date >= CURDATE()
          ORDER BY s.session_date, s.start_time LIMIT 1) AS next_session,
        (SELECT s.venue FROM class_schedule s
          WHERE s.class_id = c.class_id
          ORDER BY (s.session_date >= CURDATE()) DESC, s.session_date LIMIT 1) AS venue,
        (SELECT GROUP_CONCAT(CONCAT(t.first_name, ' ', t.last_name, '::', ct.assigned_role)
                 ORDER BY FIELD(ct.assigned_role, 'Lead Instructor', 'Co-Teacher', 'Substitute') SEPARATOR '||')
           FROM class_teacher ct JOIN teachers t ON t.teacher_id = ct.teacher_id
          WHERE ct.class_id = c.class_id) AS teacher_list
    FROM class c
    $where
    ORDER BY c.created_at DESC
";

$classes = [];
$load_error = '';
try {
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param('s', $params[0]);
    }
    $stmt->execute();
    $classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('admin_classes query failed: ' . $e->getMessage());
    $load_error = 'Could not load classes right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes | HandInHand FSL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=4">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="header-titles">
                <span class="portal-eyebrow">ADMINISTRATION PORTAL &bull; CLASSES</span>
                <h1 class="page-title">FSL <em>Classes</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <?php if ($flash_success): ?><div class="cls-alert ok"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
        <?php if ($flash_error):   ?><div class="cls-alert err"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>
        <?php if ($load_error):    ?><div class="cls-alert err"><?php echo htmlspecialchars($load_error); ?></div><?php endif; ?>

        <section class="panel-card">
            <div class="cls-toolbar">
                <nav class="cls-filters" aria-label="Filter by status">
                    <?php foreach ($filters as $key => $label): ?>
                        <a href="admin_classes.php?status=<?php echo urlencode($key); ?>" class="<?php echo $key === $filter ? 'on' : ''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </nav>
                <a href="admin_class_new.php" class="cls-btn">+ New Class</a>
            </div>

            <div class="cls-table-wrap">
                <table class="cls-table">
                    <thead>
                        <tr>
                            <th>Class</th><th>Next session</th><th>Venue</th>
                            <th>Teacher</th><th>Enrolled</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$classes): ?>
                        <tr><td colspan="7" class="cls-empty">No classes found. Click “+ New Class” to create one.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($classes as $c): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($c['class_title']); ?></strong>
                                <small><?php echo (int)$c['session_count']; ?> session<?php echo (int)$c['session_count'] === 1 ? '' : 's'; ?></small>
                            </td>
                            <td><?php echo $c['next_session']
                                    ? htmlspecialchars(date('D, M j, Y • g:i A', strtotime($c['next_session'])))
                                    : '<span style="opacity:.6">None scheduled</span>'; ?></td>
                            <td><?php echo htmlspecialchars($c['venue'] ?: 'Venue TBA'); ?></td>
                            <td class="tchr-names">
                                <?php if (!empty($c['teacher_list'])): ?>
                                    <?php foreach (explode('||', $c['teacher_list']) as $entry):
                                        [$tname, $trole] = array_pad(explode('::', $entry, 2), 2, ''); ?>
                                        <div><?php echo htmlspecialchars($tname); ?> <small>(<?php echo htmlspecialchars($trole === 'Lead Instructor' ? 'Lead' : $trole); ?>)</small></div>
                                    <?php endforeach; ?>
                                <?php else: ?>TBA<?php endif; ?>
                            </td>
                            <td><?php echo (int)$c['enrolled_count']; ?>/<?php echo (int)$c['max_capacity']; ?></td>
                            <td><span class="cls-status st-<?php echo htmlspecialchars($c['status']); ?>"><?php echo htmlspecialchars($c['status']); ?></span></td>
                            <td style="white-space:nowrap">
                                <a href="admin_class_edit.php?id=<?php echo (int)$c['class_id']; ?>">Edit</a> &nbsp;|&nbsp;
                                <a href="admin_class_sessions.php?id=<?php echo (int)$c['class_id']; ?>">Sessions</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script>
    const btn = document.getElementById('mobileMenuBtn'), sb = document.getElementById('adminSidebar');
    if (btn && sb) btn.addEventListener('click', () => sb.classList.toggle('open'));
</script>
</body>
</html>
