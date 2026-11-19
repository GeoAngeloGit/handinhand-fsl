<?php
session_start();

// Simulated logged-in account & linked Learner profiles (Replace with MySQL queries later)
$account_username = $_SESSION['username'] ?? 'maria_santos';

$learner_profiles = [
    [
        'learner_id' => 101,
        'full_name' => 'Maria Clara Santos',
        'relationship' => 'Primary Account Holder',
        'birthdate' => '2002-05-14',
        'gender' => 'Female',
        'contact_number' => '0917 555 0142',
        'municipality' => 'Legazpi City',
        'barangay' => "Barangay 1 - Em's Barrio",
        'street_address' => 'Purok 3, Rizal St.',
        'hearing_status' => 'Profoundly Deaf',
        'speech_status' => 'Non-Verbal / Mute',
        'accessibility_notes' => 'Prefers front-row seating with clear line of sight to instructor.'
    ],
    [
        'learner_id' => 102,
        'full_name' => 'Mateo Gabriel Santos',
        'relationship' => 'Family Member (Brother)',
        'birthdate' => '2012-09-22',
        'gender' => 'Male',
        'contact_number' => '0917 555 0142',
        'municipality' => 'Legazpi City',
        'barangay' => "Barangay 1 - Em's Barrio",
        'street_address' => 'Purok 3, Rizal St.',
        'hearing_status' => 'Hearing',
        'speech_status' => 'Verbal',
        'accessibility_notes' => 'Learning FSL to communicate with sister.'
    ]
];

// Active selected profile (defaults to first profile)
$selected_id = isset($_GET['profile_id']) ? (int)$_GET['profile_id'] : $learner_profiles[0]['learner_id'];
$active_profile = $learner_profiles[0];
foreach ($learner_profiles as $p) {
    if ($p['learner_id'] === $selected_id) {
        $active_profile = $p;
        break;
    }
}

// Sample Open Classes from MySQL `Class` + `Class_Teacher`
$open_classes = [
    [
        'class_id' => 1,
        'class_title' => 'FSL Level 1: Foundations & Everyday Greetings',
        'description' => 'Covers the Filipino manual alphabet, numbers, family signs, and basic conversational etiquette for beginners.',
        'instructor' => 'Inst. Elena R. Mendoza (Lead Instructor)',
        'schedule_summary' => 'Saturdays • 9:00 AM – 11:30 AM',
        'venue' => 'Legazpi City Community Hub - Room B',
        'current_enrolled' => 18,
        'max_capacity' => 25,
        'status' => 'Open'
    ],
    [
        'class_id' => 2,
        'class_title' => 'FSL Level 2: Community & Workplace Communication',
        'description' => 'Builds fluency in expressive storytelling, non-manual signals, and practical civic vocabulary across Albay.',
        'instructor' => 'Inst. Carlo B. Navarro (Lead Instructor)',
        'schedule_summary' => 'Sundays • 1:30 PM – 4:00 PM',
        'venue' => 'Daraga Municipal Library Hall',
        'current_enrolled' => 20,
        'max_capacity' => 20,
        'status' => 'Open'
    ],
    [
        'class_id' => 3,
        'class_title' => 'Family & Youth Inclusive Signing Circle',
        'description' => 'Interactive group sessions designed for Deaf youth and their hearing family members to practice together.',
        'instructor' => 'Inst. Sofia T. Villanueva (Lead Instructor)',
        'schedule_summary' => 'Wednesdays • 4:00 PM – 6:00 PM',
        'venue' => 'Tabaco City Cultural Center',
        'current_enrolled' => 11,
        'max_capacity' => 20,
        'status' => 'Open'
    ]
];

// Sample Upcoming & Past Sessions from `Class_Schedule` + `Session_Attendance`
$schedules = [
    [
        'date' => 'Nov 21, 2026',
        'time' => '9:00 AM – 11:30 AM',
        'class_title' => 'FSL Level 1: Foundations & Everyday Greetings',
        'venue' => 'Legazpi City Community Hub - Room B',
        'session_status' => 'Scheduled',
        'attendance' => 'Upcoming'
    ],
    [
        'date' => 'Nov 14, 2026',
        'time' => '9:00 AM – 11:30 AM',
        'class_title' => 'FSL Level 1: Foundations & Everyday Greetings',
        'venue' => 'Legazpi City Community Hub - Room B',
        'session_status' => 'Completed',
        'attendance' => 'Present'
    ],
    [
        'date' => 'Nov 07, 2026',
        'time' => '9:00 AM – 11:30 AM',
        'class_title' => 'FSL Level 1: Foundations & Everyday Greetings',
        'venue' => 'Legazpi City Community Hub - Room B',
        'session_status' => 'Completed',
        'attendance' => 'Present'
    ]
];

$flash_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'enroll_class') {
        $flash_msg = "Enrollment request submitted! (Ready for MySQL INSERT into Class_Enrollments)";
    } elseif ($action === 'add_profile') {
        $flash_msg = "New family learner profile saved! (Ready for MySQL INSERT into Learners)";
    } elseif ($action === 'update_profile') {
        $flash_msg = "Learner profile & Albay address updated! (Ready for MySQL UPDATE on Learners)";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learner Portal | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="learner.css">
</head>
<body>

    <div class="dashboard-layout">
        
        <!-- ==================== LEFT SIDEBAR ==================== -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="brand-logo">HandInHand</a>
                <span class="role-badge">FSL LEARNER</span>
            </div>

            <!-- Active Learner Profile Switcher -->
            <div class="profile-switcher-box">
                <label for="profileSwitcher">ACTIVE LEARNER PROFILE</label>
                <select id="profileSwitcher" onchange="window.location.href='learner_dashboard.php?profile_id=' + this.value">
                    <?php foreach ($learner_profiles as $prof): ?>
                        <option value="<?php echo $prof['learner_id']; ?>" <?php echo ($prof['learner_id'] === $active_profile['learner_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prof['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn-add-family" onclick="openModal('addProfileModal')">
                    + Add Family Member
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="sidebar-nav">
                <button type="button" class="nav-item active" data-target="tab-overview">
                    <span class="nav-dot"></span> Overview &amp; Schedule
                </button>
                <button type="button" class="nav-item" data-target="tab-classes">
                    <span class="nav-dot"></span> Browse Open Classes
                </button>
                <button type="button" class="nav-item" data-target="tab-profile">
                    <span class="nav-dot"></span> Manage Learner Profile
                </button>
            </nav>

            <div class="sidebar-footer">
                <div class="account-info">
                    <small>Signed in as</small>
                    <strong>@<?php echo htmlspecialchars($account_username); ?></strong>
                </div>
                <a href="login.php" class="btn-logout">Sign Out</a>
            </div>
        </aside>

        <!-- ==================== MAIN CONTENT AREA ==================== -->
        <main class="main-content">
            
            <!-- Top Mobile Header -->
            <header class="topbar">
                <button type="button" class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
                    <span></span><span></span><span></span>
                </button>
                <div>
                    <p class="welcome-eyebrow">ALBAY PROVINCE FSL COMMUNITY</p>
                    <h1 class="page-heading">Welcome, <?php echo htmlspecialchars($active_profile['full_name']); ?></h1>
                </div>
                <div class="topbar-pills">
                    <span class="pill-tag"><?php echo htmlspecialchars($active_profile['hearing_status']); ?></span>
                    <span class="pill-tag secondary"><?php echo htmlspecialchars($active_profile['municipality']); ?></span>
                </div>
            </header>

            <?php if (!empty($flash_msg)): ?>
                <div class="flash-banner" role="status">
                    <span><?php echo htmlspecialchars($flash_msg); ?></span>
                    <button type="button" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- ==================== TAB 1: OVERVIEW & SCHEDULE ==================== -->
            <section class="tab-panel active" id="tab-overview">
                <!-- Summary Cards -->
                <div class="stats-row">
                    <div class="stat-card">
                        <span class="stat-label">ENROLLED CLASSES</span>
                        <strong class="stat-val">1 Active</strong>
                        <small>FSL Level 1: Foundations</small>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">NEXT SESSION</span>
                        <strong class="stat-val">Nov 21, 2026</strong>
                        <small>9:00 AM • Legazpi Community Hub</small>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">ATTENDANCE RECORD</span>
                        <strong class="stat-val">100%</strong>
                        <small>2 of 2 completed sessions attended</small>
                    </div>
                </div>

                <!-- Class Schedule & Attendance Table -->
                <div class="content-card">
                    <div class="card-header">
                        <div>
                            <h2>Class Schedule &amp; Attendance History</h2>
                            <p>Showing sessions for <strong><?php echo htmlspecialchars($active_profile['full_name']); ?></strong></p>
                        </div>
                        <button type="button" class="btn-primary-sm" onclick="switchTab('tab-classes')">+ Enroll in Another Class</button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date &amp; Time</th>
                                    <th>Class Title</th>
                                    <th>Venue</th>
                                    <th>Session Status</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['date']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['time']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['class_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['venue']); ?></td>
                                        <td>
                                            <span class="badge <?php echo strtolower($row['session_status']); ?>">
                                                <?php echo htmlspecialchars($row['session_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo strtolower($row['attendance']); ?>">
                                                <?php echo htmlspecialchars($row['attendance']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ==================== TAB 2: BROWSE OPEN CLASSES ==================== -->
            <section class="tab-panel" id="tab-classes">
                <div class="section-intro">
                    <h2>Available FSL Classes in Albay</h2>
                    <p>Select an open class below to enroll <strong><?php echo htmlspecialchars($active_profile['full_name']); ?></strong> or another family member.</p>
                </div>

                <div class="classes-grid">
                    <?php foreach ($open_classes as $cls): 
                        $slots_left = $cls['max_capacity'] - $cls['current_enrolled'];
                        $is_full = $slots_left <= 0;
                    ?>
                        <article class="class-card">
                            <div class="class-card-top">
                                <span class="slot-badge <?php echo $is_full ? 'full' : 'available'; ?>">
                                    <?php echo $is_full ? 'Class Full' : $slots_left . ' Slots Left'; ?>
                                </span>
                                <span class="capacity-text">
                                    <?php echo $cls['current_enrolled'] . '/' . $cls['max_capacity']; ?> Enrolled
                                </span>
                            </div>

                            <h3><?php echo htmlspecialchars($cls['class_title']); ?></h3>
                            <p class="class-desc"><?php echo htmlspecialchars($cls['description']); ?></p>

                            <ul class="class-meta">
                                <li><strong>Instructor:</strong> <?php echo htmlspecialchars($cls['instructor']); ?></li>
                                <li><strong>Schedule:</strong> <?php echo htmlspecialchars($cls['schedule_summary']); ?></li>
                                <li><strong>Venue:</strong> <?php echo htmlspecialchars($cls['venue']); ?></li>
                            </ul>

                            <div class="class-card-footer">
                                <?php if ($is_full): ?>
                                    <button type="button" class="btn-disabled" disabled>Waitlist Closed</button>
                                <?php else: ?>
                                    <button 
                                        type="button" 
                                        class="btn-enroll" 
                                        onclick="openEnrollModal(<?php echo $cls['class_id']; ?>, '<?php echo htmlspecialchars(addslashes($cls['class_title'])); ?>')"
                                    >
                                        Register in Class
                                    </button>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- ==================== TAB 3: MANAGE LEARNER PROFILE ==================== -->
            <section class="tab-panel" id="tab-profile">
                <div class="content-card">
                    <div class="card-header">
                        <div>
                            <h2>Manage Learner Profile</h2>
                            <p>Keep residential barangay and communication preferences updated so progress carries across Albay.</p>
                        </div>
                    </div>

                    <form action="learner_dashboard.php?profile_id=<?php echo $active_profile['learner_id']; ?>" method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="learner_id" value="<?php echo $active_profile['learner_id']; ?>">

                        <h3 class="form-subhead">Personal Information</h3>
                        <div class="form-grid-3">
                            <div class="field">
                                <label>Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($active_profile['full_name']); ?>" required>
                            </div>
                            <div class="field">
                                <label>Birthdate</label>
                                <input type="date" name="birthdate" value="<?php echo htmlspecialchars($active_profile['birthdate']); ?>">
                            </div>
                            <div class="field">
                                <label>Contact Number</label>
                                <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($active_profile['contact_number']); ?>">
                            </div>
                        </div>

                        <h3 class="form-subhead">Albay Residential Address</h3>
                        <div class="form-grid-3">
                            <div class="field">
                                <label>Municipality / City</label>
                                <select name="municipality">
                                    <option value="Legazpi City" selected>Legazpi City</option>
                                    <option value="Daraga">Daraga</option>
                                    <option value="Tabaco City">Tabaco City</option>
                                    <option value="Ligao City">Ligao City</option>
                                    <option value="Camalig">Camalig</option>
                                    <option value="Guinobatan">Guinobatan</option>
                                    <option value="Polangui">Polangui</option>
                                    <option value="Tiwi">Tiwi</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>Barangay</label>
                                <input type="text" name="barangay" value="<?php echo htmlspecialchars($active_profile['barangay']); ?>">
                            </div>
                            <div class="field">
                                <label>Street / Purok</label>
                                <input type="text" name="street_address" value="<?php echo htmlspecialchars($active_profile['street_address']); ?>">
                            </div>
                        </div>

                        <h3 class="form-subhead">Accessibility &amp; Communication Needs</h3>
                        <div class="form-grid-3">
                            <div class="field">
                                <label>Hearing Status</label>
                                <select name="hearing_status">
                                    <?php foreach (['Hearing', 'Mild to Moderate', 'Severe', 'Profoundly Deaf'] as $hs): ?>
                                        <option value="<?php echo $hs; ?>" <?php echo ($active_profile['hearing_status'] === $hs) ? 'selected' : ''; ?>>
                                            <?php echo $hs; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Speech Status</label>
                                <select name="speech_status">
                                    <?php foreach (['Verbal', 'Hard of Speech', 'Non-Verbal / Mute'] as $ss): ?>
                                        <option value="<?php echo $ss; ?>" <?php echo ($active_profile['speech_status'] === $ss) ? 'selected' : ''; ?>>
                                            <?php echo $ss; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Accessibility Notes</label>
                                <input type="text" name="accessibility_notes" value="<?php echo htmlspecialchars($active_profile['accessibility_notes']); ?>">
                            </div>
                        </div>

                        <div class="form-footer-right">
                            <button type="submit" class="btn-save-maroon">Save Profile Changes</button>
                        </div>
                    </form>
                </div>
            </section>

        </main>
    </div>

    <!-- ==================== MODAL 1: ENROLL IN CLASS ==================== -->
    <div class="modal-backdrop" id="enrollModal">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Confirm Class Registration</h3>
                <button type="button" class="close-modal" onclick="closeModal('enrollModal')">&times;</button>
            </div>
            <form action="learner_dashboard.php" method="POST">
                <input type="hidden" name="action" value="enroll_class">
                <input type="hidden" name="class_id" id="modalClassId">

                <p class="modal-class-label">Selected Class:</p>
                <div class="selected-class-banner" id="modalClassTitle">FSL Level 1</div>

                <div class="field mt-16">
                    <label for="enroll_learner_id">Select Learner Profile to Enroll</label>
                    <select name="learner_id" id="enroll_learner_id" required>
                        <?php foreach ($learner_profiles as $prof): ?>
                            <option value="<?php echo $prof['learner_id']; ?>" <?php echo ($prof['learner_id'] === $active_profile['learner_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prof['full_name'] . ' (' . $prof['hearing_status'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('enrollModal')">Cancel</button>
                    <button type="submit" class="btn-confirm-blue">Confirm Enrollment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL 2: ADD FAMILY LEARNER PROFILE ==================== -->
    <div class="modal-backdrop" id="addProfileModal">
        <div class="modal-box wide">
            <div class="modal-head">
                <h3>Add Family Member / Learner Profile</h3>
                <button type="button" class="close-modal" onclick="closeModal('addProfileModal')">&times;</button>
            </div>
            <form action="learner_dashboard.php" method="POST">
                <input type="hidden" name="action" value="add_profile">

                <div class="form-grid-2">
                    <div class="field">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="field">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="field">
                        <label>Birthdate</label>
                        <input type="date" name="birthdate">
                    </div>
                    <div class="field">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Hearing Status *</label>
                        <select name="hearing_status" required>
                            <option value="Hearing">Hearing</option>
                            <option value="Mild to Moderate">Mild to Moderate</option>
                            <option value="Severe">Severe</option>
                            <option value="Profoundly Deaf">Profoundly Deaf</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Speech Status *</label>
                        <select name="speech_status" required>
                            <option value="Verbal">Verbal</option>
                            <option value="Hard of Speech">Hard of Speech</option>
                            <option value="Non-Verbal / Mute">Non-Verbal / Mute</option>
                        </select>
                    </div>
                </div>

                <div class="field mt-16">
                    <label>Accessibility Notes (Optional)</label>
                    <input type="text" name="accessibility_notes" placeholder="e.g., Needs visual aids or front seating">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('addProfileModal')">Cancel</button>
                    <button type="submit" class="btn-confirm-blue">Save Learner Profile</button>
                </div>
            </form>
        </div>
    </div>

    <script src="learner.js"></script>
</body>
</html>