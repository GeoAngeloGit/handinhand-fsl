<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/class_helpers.php';
require_login([ROLE_ADMIN]);

$active_nav = 'classes';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
$flash_old     = $_SESSION['flash_old'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_old']);

$class_id = ctype_digit($_GET['id'] ?? '') ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT c.class_id, c.class_title, c.description, c.max_capacity, c.current_enrolled, c.status,
            (SELECT COUNT(*) FROM class_enrollments e
              WHERE e.class_id = c.class_id AND e.status = 'enrolled') AS enrolled_count,
            (SELECT ct.teacher_id FROM class_teacher ct
              WHERE ct.class_id = c.class_id AND ct.assigned_role = 'Lead Instructor' LIMIT 1) AS lead_teacher_id
       FROM class c WHERE c.class_id = ?"
);
$stmt->bind_param('i', $class_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$class) {
    $_SESSION['flash_error'] = 'Class not found.';
    header('Location: admin_classes.php');
    exit;
}

// After a failed save, show what the admin typed instead of the stored values
$form = [
    'class_title'  => $class['class_title'],
    'description'  => $class['description'] ?? '',
    'max_capacity' => (string)$class['max_capacity'],
    'status'       => $class['status'],
];
if (is_array($flash_old) && (int)($flash_old['class_id'] ?? 0) === $class_id) {
    foreach ($form as $k => $_) {
        if (isset($flash_old[$k])) $form[$k] = (string)$flash_old[$k];
    }
}

$teachers = get_active_teachers($conn, $class_id);

// Current assignments (or what the admin typed if the last save failed)
$assigned = [];
if (is_array($flash_old) && (int)($flash_old['class_id'] ?? 0) === $class_id && is_array($flash_old['teachers'] ?? null)) {
    $assigned = $flash_old['teachers'];
} else {
    $stmt = $conn->prepare(
        "SELECT teacher_id, assigned_role AS role FROM class_teacher WHERE class_id = ?
          ORDER BY FIELD(assigned_role, 'Lead Instructor', 'Co-Teacher', 'Substitute'), assigned_at"
    );
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $assigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
$min_capacity = max((int)$class['enrolled_count'], (int)$class['current_enrolled'], 1);
$h = static fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Class | HandInHand FSL</title>
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
                <h1 class="page-title">Edit <em>Class</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <?php if ($flash_success): ?><div class="cls-alert ok"><?php echo $h($flash_success); ?></div><?php endif; ?>
        <?php if ($flash_error):   ?><div class="cls-alert err"><?php echo $h($flash_error); ?></div><?php endif; ?>

        <section class="panel-card">
            <form action="actions/update_class.php" method="POST" class="cls-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="class_id" value="<?php echo (int)$class['class_id']; ?>">

                <fieldset>
                    <legend>Class Details</legend>
                    <div class="cls-field" style="margin-bottom:14px">
                        <label for="class_title">Class Title <span class="cls-req">*</span></label>
                        <input type="text" id="class_title" name="class_title" maxlength="200" required value="<?php echo $h($form['class_title']); ?>">
                    </div>
                    <div class="cls-field" style="margin-bottom:14px">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"><?php echo $h($form['description']); ?></textarea>
                    </div>
                    <div class="cls-grid">
                        <div class="cls-field">
                            <label for="max_capacity">Max Capacity <span class="cls-req">*</span></label>
                            <input type="number" id="max_capacity" name="max_capacity" min="<?php echo $min_capacity; ?>" max="500" required value="<?php echo $h($form['max_capacity']); ?>">
                            <div class="hint"><?php echo (int)$class['enrolled_count']; ?> learner(s) enrolled, so capacity cannot go below <?php echo $min_capacity; ?>.</div>
                        </div>
                        <div class="cls-field">
                            <label for="status">Status <span class="cls-req">*</span></label>
                            <select id="status" name="status" required>
                                <?php foreach (CLASS_STATUSES as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $form['status'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php echo teacher_assignment_section($teachers, $assigned); ?>
                </fieldset>

                <p style="font-size:.85rem;opacity:.75;margin:0">
                    Dates, times and venues are edited per session on the
                    <a href="admin_class_sessions.php?id=<?php echo (int)$class['class_id']; ?>">Sessions page</a>.
                </p>

                <div class="cls-actions">
                    <button type="submit" class="cls-btn">Save Changes</button>
                    <a href="admin_classes.php" class="cls-btn ghost">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>
<script>
    const btn = document.getElementById('mobileMenuBtn'), sb = document.getElementById('adminSidebar');
    if (btn && sb) btn.addEventListener('click', () => sb.classList.toggle('open'));
</script>
</body>
</html>
