<?php
session_start();

$error_message = "";
$success_message = "";

// Preserve entered values if validation fails
$old = [
    "username" => "",
    "email" => "",
    "first_name" => "",
    "middle_name" => "",
    "last_name" => "",
    "birthdate" => "",
    "gender" => "",
    "contact_number" => "",
    "municipality_id" => "",
    "barangay_id" => "",
    "street_address" => "",
    "hearing_status" => "",
    "speech_status" => "",
    "accessibility_notes" => ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    foreach ($old as $key => $val) {
        $old[$key] = trim($_POST[$key] ?? "");
    }
    $password = $_POST["password"] ?? "";

    // Allowed values matching Data Dictionary CHECK constraints
    $valid_hearing = ["Hearing", "Mild to Moderate", "Severe", "Profoundly Deaf"];
    $valid_speech  = ["Verbal", "Hard of Speech", "Non-Verbal / Mute"];

    if (empty($old["username"]) || empty($old["email"]) || empty($password) || empty($old["first_name"]) || empty($old["last_name"])) {
        $error_message = "Please fill out all required fields marked with (*).";
    } elseif (!in_array($old["hearing_status"], $valid_hearing, true) || !in_array($old["speech_status"], $valid_speech, true)) {
        $error_message = "Please select valid Hearing Status and Speech Status options.";
    } else {
        /* =====================================================================
           BACKEND TEAMMATE TODO: Run inside a MySQL Transaction:
           1. Hash password: $hash = password_hash($password, PASSWORD_DEFAULT);
           2. INSERT INTO Accounts (role_id, username, email, password_hash)
           3. $account_id = $conn->insert_id;
           4. INSERT INTO Learners (account_id, first_name, middle_name, last_name,
              birthdate, contact_number, gender, hearing_status, speech_status,
              accessibility_notes, barangay_id, street_address)
           5. Redirect to login.php?registered=1
           ===================================================================== */
        $success_message = "Form validated! Ready for MySQL transaction insert into Accounts and Learners.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Learner Account | HandInHand FSL</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="register.css">
</head>
<body>

    <div class="page-wrapper">
        <div class="outer-maroon-shell">
            
            <!-- Top Bar -->
            <header class="top-bar">
                <a href="index.php" class="brand-logo">HandinHand</a>
                <a href="login.php" class="top-signin-link">Already have an account? <strong>Sign In</strong></a>
            </header>

            <!-- Inner Berry Card -->
            <main class="inner-berry-card">
                <h1 class="page-title">Create Learner Account</h1>

                <?php if (!empty($error_message)): ?>
                    <div style="background:#fde8ec;color:#7a142c;padding:12px 18px;border-radius:14px;margin-bottom:16px;font-size:0.88rem;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div style="background:#e6f4ea;color:#135c2a;padding:12px 18px;border-radius:14px;margin-bottom:16px;font-size:0.88rem;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <div class="outlined-form-box">
                    <form action="register.php" method="POST" id="registerForm">
                        
                        <!-- SECTION 1: ACCOUNT CREDENTIALS -->
                        <fieldset class="form-section">
                            <legend>Account</legend>
                            <div class="grid-3">
                                <div class="field">
                                    <label for="username">Username <span class="req">*</span></label>
                                    <input type="text" id="username" name="username" maxlength="30" value="<?php echo htmlspecialchars($old['username']); ?>" placeholder="e.g., juan_delacruz" required>
                                </div>
                                <div class="field">
                                    <label for="email">Email <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" maxlength="255" value="<?php echo htmlspecialchars($old['email']); ?>" placeholder="juan@example.com" required>
                                </div>
                                <div class="field">
                                    <label for="password">Password <span class="req">*</span></label>
                                    <input type="password" id="password" name="password" minlength="8" placeholder="Minimum 8 characters" required>
                                </div>
                            </div>
                        </fieldset>

                        <hr class="section-divider">

                        <!-- SECTION 2: LEARNER PROFILE -->
                        <fieldset class="form-section">
                            <legend>Learner Profile</legend>
                            <div class="grid-3">
                                <div class="field">
                                    <label for="first_name">First Name <span class="req">*</span></label>
                                    <input type="text" id="first_name" name="first_name" maxlength="100" value="<?php echo htmlspecialchars($old['first_name']); ?>" required>
                                </div>
                                <div class="field">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name" maxlength="100" value="<?php echo htmlspecialchars($old['middle_name']); ?>" placeholder="Optional">
                                </div>
                                <div class="field">
                                    <label for="last_name">Last Name <span class="req">*</span></label>
                                    <input type="text" id="last_name" name="last_name" maxlength="100" value="<?php echo htmlspecialchars($old['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="grid-3 mt-16">
                                <div class="field">
                                    <label for="birthdate">Birthdate</label>
                                    <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($old['birthdate']); ?>">
                                </div>
                                <div class="field">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php if ($old['gender'] === 'Male') echo 'selected'; ?>>Male</option>
                                        <option value="Female" <?php if ($old['gender'] === 'Female') echo 'selected'; ?>>Female</option>
                                        <option value="Non-binary" <?php if ($old['gender'] === 'Non-binary') echo 'selected'; ?>>Non-binary</option>
                                        <option value="Prefer not to say" <?php if ($old['gender'] === 'Prefer not to say') echo 'selected'; ?>>Prefer not to say</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="contact_number">Contact Number</label>
                                    <input type="tel" id="contact_number" name="contact_number" maxlength="50" value="<?php echo htmlspecialchars($old['contact_number']); ?>" placeholder="09XX XXX XXXX">
                                </div>
                            </div>
                        </fieldset>

                        <hr class="section-divider">

                        <!-- SECTION 3: RESIDENTIAL ADDRESS (ALBAY PROVINCE ONLY) -->
                        <fieldset class="form-section">
                            <legend>Residential Address <span class="scope-note">(Albay Province Only)</span></legend>
                            <div class="grid-3">
                                <div class="field">
                                    <label for="municipality_id">Municipality/City <span class="req">*</span></label>
                                    <select id="municipality_id" name="municipality_id" required>
                                        <option value="">Select Municipality/City</option>
                                        <option value="1">Bacacay</option>
                                        <option value="2">Camalig</option>
                                        <option value="3">Daraga</option>
                                        <option value="4">Guinobatan</option>
                                        <option value="5">Jovellar</option>
                                        <option value="6">Legazpi City</option>
                                        <option value="7">Libon</option>
                                        <option value="8">Ligao City</option>
                                        <option value="9">Malilipot</option>
                                        <option value="10">Malinao</option>
                                        <option value="11">Manito</option>
                                        <option value="12">Oas</option>
                                        <option value="13">Pio Duran</option>
                                        <option value="14">Polangui</option>
                                        <option value="15">Rapu-Rapu</option>
                                        <option value="16">Santo Domingo</option>
                                        <option value="17">Tabaco City</option>
                                        <option value="18">Tiwi</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="barangay_id">Barangay <span class="req">*</span></label>
                                    <select id="barangay_id" name="barangay_id" required>
                                        <option value="">Select Barangay</option>
                                        <!-- Sample option until backend populates from Barangays table -->
                                        <option value="1">Barangay 1 - Em's Barrio</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="street_address">Street / Purok</label>
                                    <input type="text" id="street_address" name="street_address" maxlength="255" value="<?php echo htmlspecialchars($old['street_address']); ?>" placeholder="House No., Street, Purok">
                                </div>
                            </div>
                        </fieldset>

                        <hr class="section-divider">

                        <!-- SECTION 4: ACCESSIBILITY & COMMUNICATION PROFILE -->
                        <fieldset class="form-section">
                            <legend>Accessibility and Communication Profile</legend>
                            <div class="grid-3">
                                <div class="field">
                                    <label for="hearing_status">Hearing Status <span class="req">*</span></label>
                                    <select id="hearing_status" name="hearing_status" required>
                                        <option value="">Select Hearing Status</option>
                                        <option value="Hearing" <?php if ($old['hearing_status'] === 'Hearing') echo 'selected'; ?>>Hearing</option>
                                        <option value="Mild to Moderate" <?php if ($old['hearing_status'] === 'Mild to Moderate') echo 'selected'; ?>>Mild to Moderate</option>
                                        <option value="Severe" <?php if ($old['hearing_status'] === 'Severe') echo 'selected'; ?>>Severe</option>
                                        <option value="Profoundly Deaf" <?php if ($old['hearing_status'] === 'Profoundly Deaf') echo 'selected'; ?>>Profoundly Deaf</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="speech_status">Speech Status <span class="req">*</span></label>
                                    <select id="speech_status" name="speech_status" required>
                                        <option value="">Select Speech Status</option>
                                        <option value="Verbal" <?php if ($old['speech_status'] === 'Verbal') echo 'selected'; ?>>Verbal</option>
                                        <option value="Hard of Speech" <?php if ($old['speech_status'] === 'Hard of Speech') echo 'selected'; ?>>Hard of Speech</option>
                                        <option value="Non-Verbal / Mute" <?php if ($old['speech_status'] === 'Non-Verbal / Mute') echo 'selected'; ?>>Non-Verbal / Mute</option>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="accessibility_notes">Accessibility Notes (Optional)</label>
                                    <input type="text" id="accessibility_notes" name="accessibility_notes" value="<?php echo htmlspecialchars($old['accessibility_notes']); ?>" placeholder="e.g., Prefers front-row seating">
                                </div>
                            </div>
                        </fieldset>

                        <hr class="section-divider">

                        <!-- SUBMIT BUTTON -->
                        <div class="submit-row">
                            <button type="submit" class="btn-create-account">Create Account</button>
                        </div>

                    </form>
                </div>
            </main>
        </div>
    </div>

</body>
</html>