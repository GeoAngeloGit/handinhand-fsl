<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db_connect.php';
require_login([ROLE_ADMIN]);

$active_nav = 'classes';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$class_id = ctype_digit($_GET['id'] ?? '') ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare('SELECT class_id, class_title, status FROM class WHERE class_id = ?');
$stmt->bind_param('i', $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    $_SESSION['flash_error'] = 'Class not found.';
    header('Location: admin_classes.php');
    exit;
}

$stmt = $conn->prepare(
    'SELECT session_date, start_time, end_time, venue, status
       FROM class_schedule WHERE class_id = ?
   ORDER BY session_date, start_time'
);
$stmt->bind_param('i', $class_id);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$last_venue = '';
foreach ($sessions as $s) { if (!empty($s['venue'])) $last_venue = $s['venue']; }
$can_add = !in_array($class['status'], ['Completed', 'Cancelled'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions | HandInHand FSL</title>
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
                <span class="portal-eyebrow">CLASSES &bull; SESSIONS</span>
                <h1 class="page-title"><?php echo htmlspecialchars($class['class_title']); ?></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <?php if ($flash_success): ?><div class="cls-alert ok"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
        <?php if ($flash_error):   ?><div class="cls-alert err"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>

        <section class="panel-card" style="margin-bottom:20px">
            <div class="cls-toolbar">
                <span class="cls-status st-<?php echo htmlspecialchars($class['status']); ?>"><?php echo htmlspecialchars($class['status']); ?></span>
                <span>
                    <a href="admin_class_edit.php?id=<?php echo (int)$class['class_id']; ?>" class="cls-btn">Edit Class</a>
                    <a href="admin_classes.php" class="cls-btn ghost">&larr; Back to Classes</a>
                </span>
            </div>
            <div class="cls-table-wrap">
                <table class="cls-table">
                    <thead><tr><th>#</th><th>Date</th><th>Time</th><th>Venue</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (!$sessions): ?>
                        <tr><td colspan="5" class="cls-empty">No sessions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $i => $s): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars(date('D, M j, Y', strtotime($s['session_date']))); ?></td>
                            <td><?php echo htmlspecialchars(date('g:i A', strtotime($s['start_time'])) . ' – ' . date('g:i A', strtotime($s['end_time']))); ?></td>
                            <td><?php echo htmlspecialchars($s['venue'] ?: 'Venue TBA'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($s['status'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($can_add): ?>
        <section class="panel-card">
            <form action="actions/add_session.php" method="POST" class="cls-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="class_id" value="<?php echo (int)$class['class_id']; ?>">
                <fieldset>
                    <legend>Add Another Session <small style="font-weight:400;opacity:.65">(one at a time)</small></legend>
                    <div class="cls-field" style="margin-bottom:14px">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" maxlength="255" value="<?php echo htmlspecialchars($last_venue, ENT_QUOTES); ?>">
                    </div>
                    <div class="cls-grid">
                        <div class="cls-field">
                            <label for="session_date">Session Date <span class="cls-req">*</span></label>
                            <input type="date" id="session_date" name="session_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="cls-field">
                            <label for="start_time">Start Time <span class="cls-req">*</span></label>
                            <input type="time" id="start_time" name="start_time" required>
                        </div>
                        <div class="cls-field">
                            <label for="end_time">End Time <span class="cls-req">*</span></label>
                            <input type="time" id="end_time" name="end_time" required>
                        </div>
                    </div>
                </fieldset>
                <div class="cls-actions"><button type="submit" class="cls-btn">Add Session</button></div>
            </form>
        </section>
        <?php endif; ?>
    </main>
</div>
<script>
    const btn = document.getElementById('mobileMenuBtn'), sb = document.getElementById('adminSidebar');
    if (btn && sb) btn.addEventListener('click', () => sb.classList.toggle('open'));
</script>
</body>
</html>
