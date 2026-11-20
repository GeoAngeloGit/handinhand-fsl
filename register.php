<?php
require_once __DIR__ . '/db_connect.php';

$pending = $_SESSION['pending_reg'] ?? null;
$role = $_GET['role'] ?? ($pending['role'] ?? 'learner');
$is_educator = ($role === 'educator');

if (!isset($_SESSION['reg_profiles'])) {
    $_SESSION['reg_profiles'] = [];
}

$error_message = "";
$registration_complete = false;
$completed_summary = [];

if (isset($_GET['remove']) && !$is_educator) {
    $idx = (int)$_GET['remove'];
    if (isset($_SESSION['reg_profiles'][$idx])) {
        array_splice($_SESSION['reg_profiles'], $idx, 1);
    }
    header("Location: register.php?role=learner");
    exit;
}

// Fetch all 18 Albay Municipalities and 720 Barangays from MySQL
$municipalities = [];
$muni_names = [];
$barangays_by_muni = [];
$brgy_names = [];

if ($conn) {
    $muni_res = $conn->query("SELECT municipality_id, municipality_name FROM municipalities ORDER BY municipality_name ASC");
    if ($muni_res) {
        while ($row = $muni_res->fetch_assoc()) {
            $municipalities[] = $row;
            $muni_names[(int)$row['municipality_id']] = $row['municipality_name'];
        }
    }

    $brgy_res = $conn->query("SELECT barangay_id, municipality_id, barangay_name FROM barangays ORDER BY barangay_name ASC");
    if ($brgy_res) {
        while ($row = $brgy_res->fetch_assoc()) {
            $mid = (string)$row['municipality_id'];
            $bid = (int)$row['barangay_id'];
            $barangays_by_muni[$mid][] = [
                'id'   => $bid,
                'name' => $row['barangay_name']
            ];
            $brgy_names[$bid] = $row['barangay_name'];
        }
    }
}

// Fallback list of all 18 Albay municipalities if DB is not connected yet
if (empty($municipalities)) {
    $fallback = [
        1 => 'Bacacay', 2 => 'Camalig', 3 => 'Daraga', 4 => 'Guinobatan',
        5 => 'Jovellar', 6 => 'Legazpi City', 7 => 'Libon', 8 => 'Ligao City',
        9 => 'Malilipot', 10 => 'Malinao', 11 => 'Manito', 12 => 'Oas',
        13 => 'Pio Duran', 14 => 'Polangui', 15 => 'Rapu-Rapu',
        16 => 'Santo Domingo', 17 => 'Tabaco City', 18 => 'Tiwi'
    ];
    foreach ($fallback as $id => $name) {
        $municipalities[] = ['municipality_id' => $id, 'municipality_name' => $name];
        $muni_names[$id] = $name;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['submit_action'] ?? 'finish';
    $role = $_POST['role'] ?? 'learner';
    $is_educator = ($role === 'educator');

    $username = $pending['username'] ?? trim($_POST['username'] ?? '');
    $email = $pending['email'] ?? trim($_POST['email'] ?? '');
    $password_hash = $pending['password_hash'] ?? (!empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '');

    if (!$pending && !empty($username) && !empty($email) && !empty($password_hash)) {
        $_SESSION['pending_reg'] = [
            'role' => $role,
            'username' => $username,
            'email' => $email,
            'password_hash' => $password_hash
        ];
        $pending = $_SESSION['pending_reg'];
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '') ?: null;
    $last_name = trim($_POST['last_name'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $municipality_id = (int)($_POST['municipality_id'] ?? 0);
    $barangay_id = (int)($_POST['barangay_id'] ?? 0);
    $street_address = trim($_POST['street_address'] ?? '') ?: null;

    $hearing_status = trim($_POST['hearing_status'] ?? '');
    $speech_status = trim($_POST['speech_status'] ?? '');
    $accessibility_notes = trim($_POST['accessibility_notes'] ?? '') ?: null;

    $has_current_input = ($first_name !== '' || $last_name !== '' || $birthdate !== '');
    $valid_hearing = ["Hearing", "Mild to Moderate", "Severe", "Profoundly Deaf"];
    $valid_speech  = ["Verbal", "Hard of Speech", "Non-Verbal / Mute"];
    $valid_gender  = ["Male", "Female", "Prefer not to say"];

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error_message = "Your session expired. Please reload the page and try again.";
    } elseif (empty($username) || empty($email) || empty($password_hash)) {
        $error_message = "Account credentials are missing. Please complete Step 1 first.";
    } elseif ($action === 'add_another' || $has_current_input || empty($_SESSION['reg_profiles'])) {
        if (empty($first_name) || empty($last_name) || empty($birthdate) || empty($gender) || empty($contact_number) || $municipality_id <= 0 || $barangay_id <= 0) {
            $error_message = "Please fill out all required personal and Albay address fields (*).";
        } elseif (!in_array($gender, $valid_gender, true)) {
            $error_message = "Please select a valid gender option.";
        } elseif (!($d = DateTime::createFromFormat('Y-m-d', $birthdate)) || $d->format('Y-m-d') !== $birthdate || $d > new DateTime('today')) {
            $error_message = "Please enter a valid birthdate.";
        } elseif (!$is_educator && (!in_array($hearing_status, $valid_hearing, true) || !in_array($speech_status, $valid_speech, true))) {
            $error_message = "Please select valid Hearing Status and Speech Status options.";
        } else {
            $profile_entry = [
                'first_name' => $first_name,
                'middle_name' => $middle_name,
                'last_name' => $last_name,
                'birthdate' => $birthdate,
                'gender' => $gender,
                'contact_number' => $contact_number,
                'municipality_id' => $municipality_id,
                'barangay_id' => $barangay_id,
                'street_address' => $street_address,
                'hearing_status' => $hearing_status,
                'speech_status' => $speech_status,
                'accessibility_notes' => $accessibility_notes
            ];

            if ($action === 'add_another' && !$is_educator) {
                $_SESSION['reg_profiles'][] = $profile_entry;
                header("Location: register.php?role=learner");
                exit;
            } else {
                $all_profiles = $_SESSION['reg_profiles'];
                $all_profiles[] = $profile_entry;
            }
        }
    } else {
        $all_profiles = $_SESSION['reg_profiles'];
    }

    if (empty($error_message) && $action === 'finish' && !empty($all_profiles)) {
        if ($conn) {
            $conn->begin_transaction();
            try {
                $role_id = $is_educator ? 2 : 3;
                $stmt_acc = $conn->prepare("INSERT INTO accounts (role_id, username, email, password_hash) VALUES (?, ?, ?, ?)");
                $stmt_acc->bind_param("isss", $role_id, $username, $email, $password_hash);
                $stmt_acc->execute();
                $account_id = $conn->insert_id;

                $created_ids = [];
                if ($is_educator) {
                    $p = $all_profiles[0];
                    $stmt_prof = $conn->prepare("INSERT INTO teachers (account_id, barangay_id, first_name, middle_name, last_name, gender, birthdate, street_address, contact_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_prof->bind_param("iisssssss", $account_id, $p['barangay_id'], $p['first_name'], $p['middle_name'], $p['last_name'], $p['gender'], $p['birthdate'], $p['street_address'], $p['contact_number']);
                    $stmt_prof->execute();
                    $created_ids[] = $conn->insert_id;
                } else {
                    $stmt_prof = $conn->prepare("INSERT INTO learners (account_id, barangay_id, first_name, middle_name, last_name, birthdate, gender, contact_number, hearing_status, speech_status, accessibility_notes, street_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($all_profiles as $p) {
                        $stmt_prof->bind_param("iissssssssss", $account_id, $p['barangay_id'], $p['first_name'], $p['middle_name'], $p['last_name'], $p['birthdate'], $p['gender'], $p['contact_number'], $p['hearing_status'], $p['speech_status'], $p['accessibility_notes'], $p['street_address']);
                        $stmt_prof->execute();
                        $created_ids[] = $conn->insert_id;
                    }
                }

                $conn->commit();

                login_user([
                    'account_id' => $account_id,
                    'username'   => $username,
                    'email'      => $email,
                    'role_id'    => $role_id,
                ]);
                if (!$is_educator && !empty($created_ids)) {
                    $_SESSION['active_learner_id'] = $created_ids[0];
                }

                $first_p = $all_profiles[0];
                $hub_brgy = $brgy_names[$first_p['barangay_id']] ?? 'Selected Barangay';
                $hub_muni = $muni_names[$first_p['municipality_id']] ?? 'Albay';

                $completed_summary = [
                    'email' => $email,
                    'role' => $role,
                    'hub_label' => $hub_brgy . ', ' . $hub_muni,
                    'profiles' => $all_profiles
                ];

                unset($_SESSION['pending_reg'], $_SESSION['reg_profiles']);
                $registration_complete = true;
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                if ($e->getCode() === 1062) {
                    $error_message = "That username or email is already registered.";
                } else {
                    $error_message = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "Database connection unavailable. Please import handinhand_fsl.sql in phpMyAdmin.";
        }
    }
}

$saved_profiles = $_SESSION['reg_profiles'] ?? [];
$saved_count = count($saved_profiles);
$active_number = $saved_count + 1;
$first_profile = $saved_profiles[0] ?? null;
$account_email_display = $pending['email'] ?? 'juan.delacruz@gmail.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $registration_complete ? 'Registration Complete' : 'Complete Your Profile'; ?> | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="register.css?v=4">
</head>
<body>

<?php if ($registration_complete): ?>
    <main class="complete-wrapper">
        <div class="complete-card">
            <div class="complete-check-circle">&#10003;</div>
            <span class="complete-eyebrow">ACCOUNT &amp; PROFILES CREATED</span>
            <h1 class="complete-title">Registration <em>Complete</em></h1>
            <p class="complete-desc">
                <?php $total = count($completed_summary['profiles']); ?>
                <?php echo $total; ?> <?php echo $is_educator ? 'educator' : 'learner'; ?> <?php echo $total > 1 ? 'profiles have' : 'profile has'; ?> been linked to<br>
                <strong class="blue-email"><?php echo htmlspecialchars($completed_summary['email']); ?></strong> and matched with your Albay community hub.
            </p>

            <div class="household-box">
                <div class="household-box-head">
                    <span>REGISTERED <?php echo $is_educator ? 'EDUCATOR' : 'HOUSEHOLD'; ?></span>
                    <strong class="hub-text"><?php echo htmlspecialchars($completed_summary['hub_label']); ?></strong>
                </div>
                <hr class="household-divider">
                <ul class="household-member-list">
                    <?php foreach ($completed_summary['profiles'] as $idx => $prof): ?>
                        <?php $full_name = trim($prof['first_name'] . ' ' . ($prof['middle_name'] ? $prof['middle_name'] . ' ' : '') . $prof['last_name']); ?>
                        <li>
                            <span class="member-name">
                                <span class="mini-check">&#10003;</span>
                                <?php echo htmlspecialchars($full_name); ?>
                            </span>
                            <span class="member-badge">
                                <?php echo $is_educator ? 'EDUCATOR' : 'LEARNER #' . ($idx + 1); ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <a href="<?php echo $is_educator ? 'teacher_dashboard.php' : 'learner_dashboard.php'; ?>" class="btn-dashboard-maroon">
                CONTINUE TO DASHBOARD
            </a>
        </div>
    </main>

<?php else: ?>
    <main class="reg-page-wrapper">
        <div class="reg-split-card">
            <aside class="brand-panel">
                <div>
                    <div class="brand-top">
                        <a href="index.php" class="brand-name">HandInHand</a>
                        <a href="login.php?mode=register" class="back-link">&larr; Back to Account</a>
                    </div>

                    <div class="step-tracker-pill">
                        <span class="step-done">
                            <span class="step-check">&#10003;</span>
                            <span>01 Account</span>
                        </span>
                        <span class="step-line"></span>
                        <span class="step-current">
                            <span class="step-dot-ring"></span>
                            <span>02 Profile Details</span>
                        </span>
                    </div>

                    <div class="showcase-media-box">
                        <img src="images/fsl-journey.jpg" alt="FSL Community Journey" onerror="this.style.display='none'">
                    </div>

                    <div class="brand-copy">
                        <h2>Tell Us About Your <em>Journey</em></h2>
                        <p>
                            <?php if ($is_educator): ?>
                                Complete your volunteer educator profile so coordinators can assign you to Albay community hubs and sessions.
                            <?php else: ?>
                                Complete your community profile—or add multiple family learners under one email—to match with nearby Albay barangay hubs.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="brand-location">
                    <span class="loc-dot"></span> Albay Province &bull; Bicol Region
                </div>
            </aside>

            <section class="form-panel">
                <div class="form-inner">
                    <span class="step-eyebrow">STEP 2 OF 2 &bull; COMMUNITY PROFILE</span>
                    <h1 class="form-title">Complete Your <em>Profile</em></h1>
                    <p class="form-subtitle">Fill in your personal and barangay details to finish setting up your account.</p>

                    <?php if (!$is_educator): ?>
                        <div class="profiles-tracker-box">
                            <div class="tracker-header">
                                <span>Account Email: <strong class="blue-email"><?php echo htmlspecialchars($account_email_display); ?></strong></span>
                                <span class="profile-count-tag">
                                    <?php echo $active_number === 1 ? '1 LEARNER PROFILE' : $active_number . ' HOUSEHOLD PROFILES'; ?>
                                </span>
                            </div>

                            <div class="profile-pills-row">
                                <?php foreach ($saved_profiles as $idx => $sp): ?>
                                    <?php $sp_name = trim($sp['first_name'] . ' ' . ($sp['middle_name'] ? $sp['middle_name'] . ' ' : '') . $sp['last_name']); ?>
                                    <span class="profile-pill saved">
                                        <span class="pill-check">&#10003;</span>
                                        <span><?php echo htmlspecialchars($sp_name); ?></span>
                                        <a href="register.php?role=learner&remove=<?php echo $idx; ?>" class="pill-remove" title="Remove profile">&times;</a>
                                    </span>
                                <?php endforeach; ?>

                                <span class="profile-pill active">
                                    <span class="pill-dot"></span>
                                    <span>Learner #<?php echo $active_number; ?> (Active Form)</span>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert-box error" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>

                    <form action="register.php?role=<?php echo htmlspecialchars($role); ?>" method="POST" id="profileForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                        <input type="hidden" name="submit_action" id="submit_action" value="finish">

                        <?php if (!$pending): ?>
                            <div class="grid-3 mb-14">
                                <div class="field">
                                    <label for="username">Username <span class="req">*</span></label>
                                    <input type="text" id="username" name="username" maxlength="30" placeholder="Juan_DelaCruz" required>
                                </div>
                                <div class="field">
                                    <label for="email">Email <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" maxlength="255" placeholder="Juan@gmail.com" required>
                                </div>
                                <div class="field">
                                    <label for="password">Password <span class="req">*</span></label>
                                    <input type="password" id="password" name="password" minlength="8" placeholder="Min. 8 chars" required>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="grid-3">
                            <div class="field">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" id="first_name" name="first_name" maxlength="100" placeholder="<?php echo $active_number > 1 ? 'Maria' : 'Juan'; ?>" required>
                            </div>
                            <div class="field">
                                <label for="middle_name">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" maxlength="100" placeholder="De La">
                            </div>
                            <div class="field">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" id="last_name" name="last_name" maxlength="100" placeholder="Cruz" value="<?php echo $first_profile ? htmlspecialchars($first_profile['last_name']) : ''; ?>" required>
                            </div>
                        </div>

                        <div class="grid-3 mt-14">
                            <div class="field">
                                <label for="birthdate">Birth Date <span class="req">*</span></label>
                                <input type="date" id="birthdate" name="birthdate" required>
                            </div>
                            <div class="field">
                                <label for="gender">Gender <span class="req">*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="contact_number">Contact Number <span class="req">*</span></label>
                                <input type="tel" id="contact_number" name="contact_number" maxlength="50" placeholder="+63 917 000 0000" value="<?php echo $first_profile ? htmlspecialchars($first_profile['contact_number']) : ''; ?>" required>
                            </div>
                        </div>

                        <hr class="row-divider">

                        <?php if (!$is_educator && $first_profile): ?>
                            <label class="same-address-check">
                                <input type="checkbox" id="sameAddressCheck">
                                <span>Same municipality, barangay &amp; street address as <?php echo htmlspecialchars($first_profile['first_name']); ?></span>
                            </label>
                        <?php endif; ?>

                        <div class="grid-2">
                            <div class="field">
                                <label for="municipality_id">City/Municipality <span class="req">*</span></label>
                                <select id="municipality_id" name="municipality_id" required>
                                    <option value="">Select City / Municipality</option>
                                    <?php foreach ($municipalities as $muni): ?>
                                        <option value="<?php echo (int)$muni['municipality_id']; ?>">
                                            <?php echo htmlspecialchars($muni['municipality_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="barangay_id">Barangay <span class="req">*</span></label>
                                <select id="barangay_id" name="barangay_id" disabled required>
                                    <option value="">Select Municipality First</option>
                                </select>
                            </div>
                        </div>

                        <div class="field mt-14">
                            <label for="street_address">Street / Purok Address</label>
                            <input type="text" id="street_address" name="street_address" maxlength="255" placeholder="Purok 2, Rizal Street">
                        </div>

                        <?php if (!$is_educator): ?>
                            <hr class="row-divider">

                            <div class="grid-2">
                                <div class="field">
                                    <label for="hearing_status">Hearing Status <span class="req">*</span></label>
                                    <select id="hearing_status" name="hearing_status" required>
                                        <option value="">Select Hearing Status</option>
                                        <option value="Hearing">Hearing</option>
                                        <option value="Mild to Moderate">Mild to Moderate</option>
                                        <option value="Severe">Severe</option>
                                        <option value="Profoundly Deaf">Profoundly Deaf</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="speech_status">Speech Status <span class="req">*</span></label>
                                    <select id="speech_status" name="speech_status" required>
                                        <option value="">Select Speech Status</option>
                                        <option value="Verbal">Verbal</option>
                                        <option value="Hard of Speech">Hard of Speech</option>
                                        <option value="Non-Verbal / Mute">Non-Verbal / Mute</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field mt-14">
                                <label for="accessibility_notes">Accessibility Notes (Optional)</label>
                                <input type="text" id="accessibility_notes" name="accessibility_notes" placeholder="e.g., Prefers front-row seating or visual aids">
                            </div>

                            <div class="dual-submit-row">
                                <button type="submit" class="btn-outline-maroon" onclick="document.getElementById('submit_action').value='add_another';">
                                    + ADD ANOTHER PROFILE
                                </button>
                                <button type="submit" class="btn-complete-maroon" onclick="document.getElementById('submit_action').value='finish';">
                                    <?php echo $saved_count > 0 ? 'FINISH (' . $active_number . ' PROFILES)' : 'COMPLETE REGISTRATION'; ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <button type="submit" class="btn-complete-maroon full-w">COMPLETE REGISTRATION</button>
                        <?php endif; ?>
                    </form>
                </div>
            </section>
        </div>
    </main>

    <script>
        const barangaysByMuni = <?php echo json_encode($barangays_by_muni, JSON_UNESCAPED_UNICODE); ?>;
        const firstProfileAddress = <?php echo json_encode($first_profile ? [
            'municipality_id' => (string)$first_profile['municipality_id'],
            'barangay_id'     => (string)$first_profile['barangay_id'],
            'street_address'  => (string)($first_profile['street_address'] ?? '')
        ] : null); ?>;

        const muniSelect = document.getElementById('municipality_id');
        const brgySelect = document.getElementById('barangay_id');
        const streetInput = document.getElementById('street_address');
        const sameCheck = document.getElementById('sameAddressCheck');

        function populateBarangays(muniId, selectedBrgyId = '') {
            brgySelect.innerHTML = '';

            if (!muniId || !barangaysByMuni[muniId]) {
                brgySelect.disabled = true;
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = muniId ? 'No barangays found (import SQL)' : 'Select Municipality First';
                brgySelect.appendChild(opt);
                return;
            }

            brgySelect.disabled = false;
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = 'Select Barangay';
            brgySelect.appendChild(defaultOpt);

            barangaysByMuni[muniId].forEach(brgy => {
                const opt = document.createElement('option');
                opt.value = brgy.id;
                opt.textContent = brgy.name;
                if (String(brgy.id) === String(selectedBrgyId)) {
                    opt.selected = true;
                }
                brgySelect.appendChild(opt);
            });
        }

        muniSelect.addEventListener('change', function () {
            populateBarangays(this.value);
        });

        if (sameCheck && firstProfileAddress) {
            sameCheck.addEventListener('change', function () {
                if (this.checked) {
                    muniSelect.value = firstProfileAddress.municipality_id;
                    populateBarangays(firstProfileAddress.municipality_id, firstProfileAddress.barangay_id);
                    streetInput.value = firstProfileAddress.street_address;
                } else {
                    muniSelect.value = '';
                    populateBarangays('');
                    streetInput.value = '';
                }
            });
        }
    </script>
<?php endif; ?>
</body>
</html>