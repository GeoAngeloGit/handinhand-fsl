<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <!-- Blurred Background Layer (Matches Landing Page Hero) -->
    <div class="bg-backdrop" aria-hidden="true">
        <div class="bg-hero-mock">
            <div class="bg-nav">
                <span>HandInHand</span>
                <div class="bg-links">
                    <span>HOME</span>
                    <span>OUR VALUES</span>
                    <span>HOW IT WORKS</span>
                    <span>ABOUT US</span>
                    <span>CONTACT US</span>
                </div>
                <span class="bg-pill">GET STARTED</span>
            </div>
            <div class="bg-content">
                <div class="bg-left">
                    <h2>Where <em>Educators</em> and<br><em>Learners</em> Connect<br>Through <em>Sign</em></h2>
                    <p>Spend less time on paperwork and more time teaching—keep schedules, registrations, and class reports in one place.</p>
                    <span class="bg-btn">GET STARTED</span>
                </div>
                <div class="bg-right-card"></div>
            </div>
        </div>
    </div>

    <!-- Foreground Sign-In Modal -->
    <main class="auth-overlay">
        <div class="login-card">
            <a href="index.php" class="back-home" title="Back to Home">&times;</a>
            
            <h1>Sign In</h1>

            <!-- PHP Error Alert Placeholder (Show via PHP if login fails) -->
            <!-- <div class="alert-error">Invalid email/username or password.</div> -->

            <form action="login_process.php" method="POST" class="login-form">
                <div class="form-group">
                    <label for="login_identifier">Email or Username</label>
                    <input 
                        type="text" 
                        id="login_identifier" 
                        name="login_identifier" 
                        placeholder="Enter your email or username" 
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password" 
                            required
                            autocomplete="current-password"
                        >
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Show password">
                            Show
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-login">Log In</button>
                </div>
            </form>

            <p class="switch-auth">
                New Learner? <a href="register.html">Create an Account</a>
            </p>
        </div>
    </main>

    <script>
        const togglePw = document.getElementById('togglePw');
        const pwInput = document.getElementById('password');
        togglePw.addEventListener('click', () => {
            const isHidden = pwInput.type === 'password';
            pwInput.type = isHidden ? 'text' : 'password';
            togglePw.textContent = isHidden ? 'Hide' : 'Show';
        });
    </script>
</body>
</html>