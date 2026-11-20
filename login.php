<?php
require_once __DIR__ . '/includes/db_connect.php';

// Already signed in? Skip the form.
if (!empty($_SESSION['logged_in'])) {
    header('Location: ' . (ROLE_DASHBOARD[$_SESSION['role_id']] ?? 'index.php'));
    exit;
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$errors = [
    'invalid' => 'Invalid email/username or password.',
    'locked'  => 'Too many failed attempts. Please try again in 15 minutes.',
    'csrf'    => 'Your session expired. Please try again.',
];
$error_message = $errors[$_GET['error'] ?? ''] ?? '';
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'] ?? 'login';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $mode = $form_type === 'register' ? 'register' : 'login';
        $error_message = $errors['csrf'];
    } elseif ($form_type === 'register') {
        $mode = 'register';
        $role = ($_POST['role'] ?? 'learner') === 'educator' ? 'educator' : 'learner';
        $username = trim($_POST['reg_username'] ?? '');
        $email = trim($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $error_message = 'Please fill in all required account fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } else {
            $duplicate = false;
            if ($conn) {
                $stmt = $conn->prepare("SELECT account_id FROM accounts WHERE username = ? OR email = ? LIMIT 1");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $duplicate = true;
                    $error_message = 'That username or email is already taken.';
                }
                $stmt->close();
            }

            if (!$duplicate) {
                $_SESSION['pending_reg'] = [
                    'role'          => $role,
                    'username'      => $username,
                    'email'         => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT)
                ];
                unset($_SESSION['reg_profiles']);
                header("Location: register.php?role=" . urlencode($role));
                exit;
            }
        }
    } elseif ($form_type === 'login') {
        $mode = 'login';

        if (($_SESSION['lockout_until'] ?? 0) > time()) {
            $error_message = $errors['locked'];
        } else {
            $identifier = trim($_POST['login_identifier'] ?? '');
            $password = $_POST['login_password'] ?? ($_POST['password'] ?? '');

            if (empty($identifier) || empty($password)) {
                $error_message = 'Please enter your username/email and password.';
            } else {
                $account = authenticate($conn, $identifier, $password);

                if ($account === null) {
                    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                    if ($_SESSION['login_attempts'] >= 5) {
                        $_SESSION['lockout_until'] = time() + 900;
                        $error_message = $errors['locked'];
                    } else {
                        $error_message = $errors['invalid'];
                    }
                } else {
                    login_user($account);

                    if ((int)$account['role_id'] === ROLE_LEARNER) {
                        $l_stmt = $conn->prepare("SELECT learner_id FROM learners WHERE account_id = ? AND is_active = 1 ORDER BY learner_id ASC LIMIT 1");
                        $l_stmt->bind_param("i", $account['account_id']);
                        $l_stmt->execute();
                        if ($l_row = $l_stmt->get_result()->fetch_assoc()) {
                            $_SESSION['active_learner_id'] = (int)$l_row['learner_id'];
                        }
                        $l_stmt->close();
                    }

                    $dest = post_login_destination($conn, (int)$account['account_id'], (int)$account['role_id']);
                    header("Location: " . $dest);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'register' ? 'Join HandInHand' : 'Welcome Back'; ?> | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<main class="auth-page-wrapper">
    <div class="auth-split-card">

        <!-- LEFT BRANDING PANEL -->
        <aside class="brand-panel">
            <div>
                <div class="brand-top">
                    <a href="index.php" class="brand-name">HandInHand</a>
                    <a href="index.php" class="back-home">&larr; Back to Home</a>
                </div>

                <div class="showcase-media-box">
                    <img src="images/fsl-community.jpg" alt="Filipino Sign Language Community" onerror="this.style.display='none'">
                </div>

                <div class="brand-copy">
                    <h2>Where <em>Educators</em> and <em>Learners</em> Connect Through <em>Sign</em></h2>
                    <p>
                        Spend less time on paperwork and more time teaching—keep schedules, registrations, and class reports in one place.
                    </p>
                </div>
            </div>

            <div class="brand-location">
                <span class="loc-dot"></span> Albay Province &bull; Bicol Region
            </div>
        </aside>

        <!-- RIGHT FORM PANEL -->
        <section class="form-panel">
            <div class="form-inner">

                <!-- MODE TABS (Create Account / Login) -->
                <div class="auth-tabs" role="tablist">
                    <button type="button" class="tab-btn <?php echo $mode === 'register' ? 'active' : ''; ?>" data-target="register">Create Account</button>
                    <button type="button" class="tab-btn <?php echo $mode === 'login' ? 'active' : ''; ?>" data-target="login">Login</button>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-box error" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                <?php elseif ($registered): ?>
                    <div class="alert-box" style="background:#e6f4ea;color:#135c2a;border:1px solid #b7dfc5;" role="status">Account created! Please sign in.</div>
                <?php endif; ?>

                <!-- ==================== VIEW 1: CREATE ACCOUNT ==================== -->
                <div id="view-register" class="auth-view <?php echo $mode === 'register' ? 'active' : ''; ?>">
                    <h1 class="form-title">Join <em>HandInHand</em></h1>
                    <p class="form-subtitle">Create your free account to start learning or mentoring.</p>

                    <form action="login.php?mode=register" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="form_type" value="register">
                        <input type="hidden" name="role" id="roleInput" value="learner">

                        <div class="role-grid">
                            <button type="button" class="role-card active" data-role="learner">
                                <div class="role-card-top">
                                    <span class="role-badge">LEARNER</span>
                                    <span class="role-radio"></span>
                                </div>
                                <strong>I’m an FSL Learner</strong>
                            </button>

                            <button type="button" class="role-card" data-role="educator">
                                <div class="role-card-top">
                                    <span class="role-badge">EDUCATOR</span>
                                    <span class="role-radio"></span>
                                </div>
                                <strong>Volunteer Educator</strong>
                            </button>
                        </div>

                        <div class="field mt-16">
                            <label for="reg_username">Username</label>
                            <input type="text" id="reg_username" name="reg_username" maxlength="30" placeholder="Juan Dela Cruz" required>
                        </div>

                        <div class="field mt-14">
                            <label for="reg_email">Email</label>
                            <input type="email" id="reg_email" name="reg_email" maxlength="255" placeholder="Juan@gmail.com" required>
                        </div>

                        <div class="field mt-14">
                            <label for="reg_password">Password</label>
                            <div class="pass-wrap">
                                <input type="password" id="reg_password" name="reg_password" minlength="8" placeholder="••••••••••••••" required>
                                <button type="button" class="toggle-pass" data-input="reg_password" aria-label="Toggle password visibility">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit-maroon">CONTINUE TO PROFILE DETAILS &rarr;</button>
                    </form>
                </div>

                <!-- ==================== VIEW 2: LOGIN ==================== -->
                <div id="view-login" class="auth-view <?php echo $mode === 'login' ? 'active' : ''; ?>">
                    <h1 class="form-title">Welcome <em>Back</em></h1>
                    <p class="form-subtitle">Sign in to access your classes, schedules, and community.</p>

                    <form action="login.php?mode=login" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="form_type" value="login">

                        <div class="field mt-16">
                            <label for="login_identifier">Email or Username</label>
                            <input type="text" id="login_identifier" name="login_identifier" placeholder="Juan@gmail.com" required>
                        </div>

                        <div class="field mt-14">
                            <label for="login_password">Password</label>
                            <div class="pass-wrap">
                                <input type="password" id="login_password" name="login_password" placeholder="••••••••••••••" required>
                                <button type="button" class="toggle-pass" data-input="login_password" aria-label="Toggle password visibility">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit-maroon">SIGN IN TO PORTAL &rarr;</button>
                    </form>
                </div>

            </div>
        </section>

    </div>
</main>

<script>
    const tabBtns = document.querySelectorAll('.tab-btn');
    const views = {
        login: document.getElementById('view-login'),
        register: document.getElementById('view-register')
    };

    function switchAuthMode(target) {
        tabBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.target === target));
        Object.keys(views).forEach(key => views[key].classList.toggle('active', key === target));
        const url = new URL(window.location);
        url.searchParams.set('mode', target);
        window.history.replaceState({}, '', url);
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => switchAuthMode(btn.dataset.target));
    });

    const roleCards = document.querySelectorAll('.role-card');
    const roleInput = document.getElementById('roleInput');
    roleCards.forEach(card => {
        card.addEventListener('click', () => {
            roleCards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            roleInput.value = card.dataset.role;
        });
    });

    document.querySelectorAll('.toggle-pass').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.input);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
</script>

</body>
</html>