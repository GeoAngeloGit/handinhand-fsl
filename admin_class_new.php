<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db_connect.php';
require_login([ROLE_ADMIN]);
require_once __DIR__ . '/includes/class_helpers.php';
$teachers = get_active_teachers($conn);

$active_nav = 'classes';

// Error + previously typed values from actions/create_class.php
$flash_error = $_SESSION['flash_error'] ?? '';
$old = $_SESSION['flash_old'] ?? [];
unset($_SESSION['flash_error'], $_SESSION['flash_old']);

$v = static fn(string $key, string $default = ''): string =>
    htmlspecialchars((string)($old[$key] ?? $default), ENT_QUOTES);
$status_old = $old['status'] ?? 'Open';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Class | HandInHand FSL</title>
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
                <h1 class="page-title">Create <em>Class</em></h1>
            </div>
            <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">&#9776;</button>
        </header>

        <?php if ($flash_error): ?><div class="cls-alert err"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>

        <section class="panel-card">
            <form action="actions/create_class.php" method="POST" class="cls-form">
                <?php echo csrf_field(); ?>

                <fieldset>
                    <legend>Class Details</legend>
                    <div class="cls-field" style="margin-bottom:14px">
                        <label for="class_title">Class Title <span class="cls-req">*</span></label>
                        <input type="text" id="class_title" name="class_title" maxlength="200" required
                               value="<?php echo $v('class_title'); ?>" placeholder="e.g., Basic FSL &amp; Everyday Greetings">
                    </div>
                    <div class="cls-field" style="margin-bottom:14px">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="What will learners cover?"><?php echo $v('description'); ?></textarea>
                    </div>
                    <div class="cls-grid">
                        <div class="cls-field">
                            <label for="max_capacity">Max Capacity <span class="cls-req">*</span></label>
                            <input type="number" id="max_capacity" name="max_capacity" min="1" max="500" required value="<?php echo $v('max_capacity', '20'); ?>">
                        </div>
                        <div class="cls-field">
                            <label for="status">Status <span class="cls-req">*</span></label>
                            <select id="status" name="status" required>
                                <option value="Open"  <?php echo $status_old === 'Open'  ? 'selected' : ''; ?>>Open (accepting learners)</option>
                                <option value="Draft" <?php echo $status_old === 'Draft' ? 'selected' : ''; ?>>Draft (hidden)</option>
                            </select>
                        </div>
                    </div>
                    <?php echo teacher_assignment_section($teachers, $old['teachers'] ?? []); ?>
                </fieldset>

                <fieldset>
                    <legend>First Session <small style="font-weight:400;opacity:.65">(add more sessions later, one at a time)</small></legend>
                    <div class="cls-field" style="margin-bottom:14px">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" maxlength="255"
                               value="<?php echo $v('venue'); ?>" placeholder="e.g., Brgy. Rawis Community Hall, Legazpi City">
                    </div>
                    <div class="cls-grid">
                        <div class="cls-field">
                            <label for="session_date">Session Date <span class="cls-req">*</span></label>
                            <input type="date" id="session_date" name="session_date" required
                                   min="<?php echo date('Y-m-d'); ?>" value="<?php echo $v('session_date'); ?>">
                        </div>
                        <div class="cls-field">
                            <label for="start_time">Start Time <span class="cls-req">*</span></label>
                            <input type="time" id="start_time" name="start_time" required value="<?php echo $v('start_time'); ?>">
                        </div>
                        <div class="cls-field">
                            <label for="end_time">End Time <span class="cls-req">*</span></label>
                            <input type="time" id="end_time" name="end_time" required value="<?php echo $v('end_time'); ?>">
                        </div>
                    </div>
                </fieldset>

                <div class="cls-actions">
                    <button type="submit" class="cls-btn">Create Class</button>
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
