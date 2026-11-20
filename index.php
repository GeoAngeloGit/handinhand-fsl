<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HandInHand | Filipino Sign Language Class Management System</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Linked External CSS -->
    <link rel="stylesheet" href="landing.css">
</head>
<body>

    <!-- ==================== HERO SECTION ==================== -->
    <div class="hero-wrapper" id="home">
        <header class="hero-card">
            <nav class="navbar">
                <a href="#home" class="brand-logo">HandInHand</a>

                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <ul class="nav-links" id="navLinks">
                    <li><a href="#home">HOME</a></li>
                    <li><a href="#our-values">OUR VALUES</a></li>
                    <li><a href="#about-us">ABOUT US</a></li>
                    <li><a href="#contact-us">CONTACT US</a></li>
                </ul>

                <a href="login.php" class="btn-outline-light nav-cta">GET STARTED</a>
            </nav>

            <div class="hero-content">
                <div class="hero-text">
                    <h1>
                        Where <em>Educators</em> and<br>
                        <em>Learners</em> <strong>Connect</strong><br>
                        <strong>Through</strong> <em>Sign</em>
                    </h1>
                    <p>
                        Spend less time on paperwork and more time teaching—keep schedules, registrations, and class reports in one place.
                    </p>
                    <a href="login.php?mode=register" class="btn-blush">GET STARTED</a>
                </div>

                <!-- Right Interactive Sign Card -->
                <div class="sign-widget-outer">
                    <div class="sign-widget-inner">
                        <span class="widget-label">LEARN YOUR FIRST SIGN</span>

                        <div class="sign-tabs" role="tablist">
                            <button class="sign-tab active" data-sign="love" role="tab" aria-selected="true">Love Sign</button>
                            <button class="sign-tab" data-sign="thankyou" role="tab" aria-selected="false">Thank You</button>
                            <button class="sign-tab" data-sign="hello" role="tab" aria-selected="false">Hello</button>
                        </div>

                        <div class="sign-circle-stage">
                            <div class="sign-circle-dashed">
                                <img 
                                    id="signImage" 
                                    class="sign-img" 
                                    src="images/love-sign.png" 
                                    alt="Love Sign hand illustration"
                                >
                            </div>
                        </div>

                        <hr class="widget-divider">

                        <h3 class="sign-title" id="signTitle">LOVE SIGN</h3>
                        <p class="sign-desc" id="signDesc">
                            Extend thumb, index, and pinky fingers with palm facing forward.
                        </p>
                    </div>
                </div>
            </div>

            <a href="#our-values" class="scroll-down-circle" aria-label="Scroll to Our Values">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </a>
        </header>
    </div>

    <!-- ==================== OUR VALUES SECTION ==================== -->
    <section class="values-section" id="our-values">
        <div class="container">
            <span class="section-eyebrow">WHY CHOOSE US</span>
            <h2 class="section-heading">Our Values</h2>

            <div class="values-grid">
                <div class="value-item">
                    <div class="value-icon-box" aria-hidden="true"></div>
                    <h3>Holistic Inclusion</h3>
                    <p>
                        Welcoming Deaf, hard-of-speech, and hearing learners alike into an accessible learning environment tailored to their individual communication needs.
                    </p>
                </div>

                <div class="value-item">
                    <div class="value-icon-box" aria-hidden="true"></div>
                    <h3>Adaptive Continuity</h3>
                    <p>
                        Ensuring steady, long-term community support across Albay so learners never lose their academic progress when relocating between barangays or navigating schedule changes.
                    </p>
                </div>

                <div class="value-item">
                    <div class="value-icon-box" aria-hidden="true"></div>
                    <h3>Nurturing Empowerment</h3>
                    <p>
                        Freeing volunteer FSL instructors from disorganized manual paperwork so their time and energy go directly toward teaching and mentoring.
                    </p>
                </div>

                <div class="value-item">
                    <div class="value-icon-box" aria-hidden="true"></div>
                    <h3>Dependable Stewardship</h3>
                    <p>
                        Maintaining accurate, centralized records of class rosters, attendance, and learner growth to prevent missed sessions and student drop-outs.
                    </p>
                </div>
            </div>
        </div>
    </section>


    <!-- ==================== ABOUT US SECTION ==================== -->
    <section class="about-section" id="about-us">
        <div class="container about-grid">
            <div class="bento-collage">
                <div class="bento-col-left">
                    <div class="bento-block maroon-dark tall"></div>
                    <div class="bento-block white-card short"></div>
                </div>
                <div class="bento-col-right">
                    <div class="bento-stat-card">
                        <div class="stat-top">
                            <span class="stat-number">1,000+</span>
                            <svg class="trend-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2E5D8A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <p class="stat-caption">Active FSL learners &amp; volunteer mentors across Albay</p>
                        <hr class="stat-divider">
                        <div class="avatar-dots">
                            <span class="dot d1">JJ</span>
                            <span class="dot d2">GL</span>
                            <span class="dot d3">RC</span>
                            <span class="dot d4">ZB</span>
                            <span class="dot d5">+42</span>
                        </div>
                    </div>
                    <div class="bento-block maroon-berry medium"></div>
                </div>
            </div>

            <div class="about-text">
                <span class="section-eyebrow align-left">OUR STORY</span>
                <h2 class="section-heading align-left">About Us</h2>
                <p>
                    HandInHand connects Deaf, hard-of-speech, and hearing learners across Albay through accessible Filipino Sign Language education. By pairing volunteer FSL instructors with centralized progress tracking, every learner builds lasting fluency—even when navigating schedule changes or relocating between barangays.
                </p>
                <a href="register.php" class="btn-maroon-pill">Explore More</a>
            </div>
        </div>
    </section>

 <!-- ==================== CONTACT / FOOTER SECTION ==================== -->
<footer class="contact-footer" id="contact-us">
    <div class="container contact-grid">
        <div class="contact-info">
            <span class="eyebrow-light">GET IN TOUCH</span>
            <h2>Connect with our<br>signing community</h2>
            <p>
                Whether you are a learner starting your first signs, a volunteer FSL instructor, or a local hub across Albay, we would love to hear from you.
            </p>

            <div class="hub-list">
                <div class="hub-item">
                    <div class="hub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                    </div>
                    <div>
                        <span class="hub-label">COMMUNITY HUBS</span>
                        <strong class="hub-value">Albay Province, Bicol Region</strong>
                    </div>
                </div>

                <div class="hub-item">
                    <div class="hub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>
                    <div>
                        <span class="hub-label">COORDINATOR DESK</span>
                        <strong class="hub-value">handinhand.fsl@bicol-u.edu.ph</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="contact-card">
            <h3>Ready to join a class?</h3>
            <p>Create your learner or volunteer profile to view upcoming Albay FSL sessions.</p>
            <div class="contact-card-actions">
                <a href="login.php?mode=register" class="btn-blush full-width">Register Now</a>
                <a href="login.php?mode=login" class="btn-outline-light full-width">Sign In to Portal</a>
            </div>
        </div>
    </div>
</footer>
<script>
    const signData = {
        love: {
            title: 'LOVE SIGN',
            desc: 'Extend thumb, index, and pinky fingers with palm facing forward.',
            imgSrc: 'images/love-sign.png',
            imgAlt: 'Love Sign hand illustration'
        },
        thankyou: {
            title: 'THANK YOU',
            desc: 'Touch fingertips of a flat hand to your chin and move forward.',
            imgSrc: 'images/thank-you.png',
            imgAlt: 'Thank You sign hand illustration'
        },
        hello: {
            title: 'HELLO',
            desc: 'Place open hand near forehead with palm out and move outward.',
            imgSrc: 'images/hello.png',
            imgAlt: 'Hello sign hand illustration'
        }
    };

    const tabs = document.querySelectorAll('.sign-tab');
    const signTitle = document.getElementById('signTitle');
    const signDesc = document.getElementById('signDesc');
    const signImage = document.getElementById('signImage');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const key = this.getAttribute('data-sign');
            const selected = signData[key];
            if (!selected) return;

            tabs.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');

            signTitle.textContent = selected.title;
            signDesc.textContent = selected.desc;

            if (signImage) {
                signImage.src = selected.imgSrc;
                signImage.alt = selected.imgAlt;
            }
        });
    });

    // Mobile Navigation Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.getElementById('navLinks');
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });
    }
</script>
</body>
</html>
</body>
</html>