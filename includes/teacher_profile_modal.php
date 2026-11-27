<?php
// Shared Editable Teacher Profile Modal — matches Teacher Registration Step 2 fields

$tp_account_id   = (int)($_SESSION['account_id'] ?? 0);
$tp_teacher_id   = (int)($teacher_id ?? 0);
$tp_teacher_code = $tp_teacher_id > 0 ? sprintf('TCH-%05d', $tp_teacher_id) : 'TCH-00001';
$tp_account_code = $tp_account_id > 0 ? sprintf('ACC-%03d', $tp_account_id) : 'ACC-001';

$tp_first       = (string)($teacher_first_name ?? 'Maria');
$tp_middle      = '';
$tp_last        = '';
$tp_full        = (string)($teacher_full_name ?? 'Teacher Maria');
$tp_initials    = (string)($initials ?? 'TM');
$tp_birthdate   = '';
$tp_gender      = '';
$tp_contact     = '';
$tp_email       = '';
$tp_barangay_id = 0;
$tp_muni        = (string)($teacher_location ?? 'Legazpi City');
$tp_brgy        = '';
$tp_street      = '';
$tp_spec        = '';
$tp_experience  = 0;

$tp_municipalities    = [];
$tp_barangays_by_muni = [];

if (isset($conn) && $conn instanceof mysqli) {
    try {
        $bres = $conn->query("
            SELECT b.barangay_id, b.barangay_name, m.municipality_name
            FROM barangays b
            JOIN municipalities m ON m.municipality_id = b.municipality_id
            ORDER BY m.municipality_name ASC, b.barangay_name ASC
        ");
        if ($bres) {
            while ($brow = $bres->fetch_assoc()) {
                $m_name = (string)$brow['municipality_name'];
                $tp_municipalities[$m_name] = true;
                $tp_barangays_by_muni[$m_name][] = [
                    'id'   => (int)$brow['barangay_id'],
                    'name' => (string)$brow['barangay_name'],
                ];
            }
        }

        $where_sql = $tp_teacher_id > 0
            ? "t.teacher_id = {$tp_teacher_id}"
            : ($tp_account_id > 0 ? "t.account_id = {$tp_account_id}" : "1=1");

        $sql_tp = "
            SELECT t.*,
                   a.email AS account_email,
                   b.barangay_name,
                   m.municipality_name
            FROM teachers t
            LEFT JOIN accounts a ON a.account_id = t.account_id
            LEFT JOIN barangays b ON b.barangay_id = t.barangay_id
            LEFT JOIN municipalities m ON m.municipality_id = b.municipality_id
            WHERE {$where_sql}
            ORDER BY t.teacher_id ASC
            LIMIT 1
        ";

        if ($res = $conn->query($sql_tp)) {
            if ($row = $res->fetch_assoc()) {
                $tp_teacher_id   = (int)($row['teacher_id'] ?? $tp_teacher_id);
                $tp_account_id   = (int)($row['account_id'] ?? $tp_account_id);
                $tp_teacher_code = sprintf('TCH-%05d', $tp_teacher_id);
                $tp_account_code = sprintf('ACC-%03d', $tp_account_id);

                $tp_first  = trim((string)($row['first_name'] ?? $tp_first)) ?: $tp_first;
                $tp_middle = trim((string)($row['middle_name'] ?? ''));
                $tp_last   = trim((string)($row['last_name'] ?? ''));
                $tp_full   = trim($tp_first . ($tp_last !== '' ? ' ' . $tp_last : ''));

                $tp_initials = strtoupper(
                    substr($tp_first, 0, 1) .
                    substr($tp_last !== '' ? $tp_last : $tp_first, 0, 1)
                );

                $tp_birthdate   = trim((string)($row['birth_date'] ?? ($row['birthdate'] ?? '')));
                $tp_gender      = trim((string)($row['gender'] ?? ''));
                $tp_contact     = trim((string)($row['contact_number'] ?? ''));
                $tp_email       = trim((string)($row['account_email'] ?? ($row['email'] ?? '')));
                $tp_barangay_id = (int)($row['barangay_id'] ?? 0);

                if (!empty($row['municipality_name'])) {
                    $tp_muni = trim((string)$row['municipality_name']);
                }
                if (!empty($row['barangay_name'])) {
                    $tp_brgy = trim((string)$row['barangay_name']);
                }

                $tp_street     = trim((string)($row['street_address'] ?? ($row['purok_address'] ?? ($row['address'] ?? ''))));
                $tp_spec       = trim((string)($row['fsl_specialization'] ?? ($row['specialization'] ?? '')));
                $tp_experience = (int)($row['years_of_experience'] ?? ($row['experience_years'] ?? 0));
            }
        }
    } catch (Throwable $e) {
        error_log('teacher_profile_modal query error: ' . $e->getMessage());
    }
}

$tp_flash_msg  = $_SESSION['teacher_profile_flash'] ?? '';
$tp_flash_type = $_SESSION['teacher_profile_flash_type'] ?? 'success';
unset($_SESSION['teacher_profile_flash'], $_SESSION['teacher_profile_flash_type']);
$current_script = basename($_SERVER['PHP_SELF'] ?? 'teacher_dashboard.php');
?>

<style>
#teacherProfileModal .tp-edit-input {
    width: 60%;
    min-width: 190px;
    text-align: right;
    font-family: inherit;
    font-size: 0.78rem;
    font-weight: 700;
    color: #2B1A1F;
    background-color: #FFFFFF;
    border: 1px solid #D9CBD0;
    border-radius: 9px;
    padding: 6px 10px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
#teacherProfileModal .tp-edit-input:focus {
    border-color: #5C182B;
    box-shadow: 0 0 0 3px rgba(92, 24, 43, 0.12);
}
#teacherProfileModal select.tp-edit-input {
    text-align-last: right;
    cursor: pointer;
}
</style>

<?php if ($tp_flash_msg !== ''): ?>
<div id="tpToastBanner" role="status" style="position:fixed;bottom:20px;right:20px;z-index:1100;background:<?php echo $tp_flash_type === 'error' ? '#991B1B' : '#5C182B'; ?>;color:#FFFFFF;padding:12px 18px;border-radius:14px;font-size:0.78rem;font-weight:600;box-shadow:0 16px 36px rgba(34,15,21,0.28);display:flex;align-items:center;gap:10px;">
    <span><?php echo htmlspecialchars($tp_flash_msg); ?></span>
</div>
<script>
    setTimeout(function () {
        var b = document.getElementById('tpToastBanner');
        if (b) b.style.display = 'none';
    }, 4200);
</script>
<?php endif; ?>

<!-- ==================== EDITABLE TEACHER PROFILE MODAL ==================== -->
<div class="cls-modal-backdrop" id="teacherProfileModal" aria-hidden="true">
    <div class="cls-modal-card" style="max-width:540px;" role="dialog" aria-modal="true" aria-labelledby="tpModalTitle">
        <div class="cls-modal-header">
            <div class="cls-modal-head-left" style="align-items: center;">
                <div id="tpAvatarInitials" style="width:44px;height:44px;border-radius:50%;background-color:#2B6CB0;color:#FFFFFF;font-size:0.9rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <?php echo htmlspecialchars($tp_initials); ?>
                </div>
                <div>
                    <div class="cls-modal-eyebrow-row">
                        <span>EDIT TEACHER PROFILE &bull; <?php echo htmlspecialchars($tp_teacher_code); ?> (<?php echo htmlspecialchars($tp_account_code); ?>)</span>
                    </div>
                    <h2 class="cls-modal-title" id="tpModalTitle"><?php echo htmlspecialchars($tp_full); ?></h2>
                </div>
            </div>
            <button type="button" class="cls-modal-close" id="closeTeacherProfileBtn" aria-label="Close modal" style="display:flex;align-items:center;justify-content:center;line-height:1;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <form action="actions/teacher_update_profile.php" method="POST" id="teacherProfileEditForm">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string)csrf_token()); ?>">
            <?php elseif (function_exists('csrf_field')): echo csrf_field(); endif; ?>
            <input type="hidden" name="teacher_id" value="<?php echo (int)$tp_teacher_id; ?>">
            <input type="hidden" name="account_id" value="<?php echo (int)$tp_account_id; ?>">
            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($current_script); ?>">

            <div class="cls-detail-rows" style="margin-bottom: 16px;">
                <div class="cls-detail-row">
                    <span class="cls-row-label">Teacher ID</span>
                    <strong class="cls-row-val"><?php echo htmlspecialchars($tp_teacher_code); ?></strong>
                </div>

                <div class="cls-detail-row">
                    <span class="cls-row-label">Account ID</span>
                    <strong class="cls-row-val"><?php echo htmlspecialchars($tp_account_code); ?></strong>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpFirstName">First Name *</label>
                    <input type="text" id="tpFirstName" name="first_name" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_first); ?>" required>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpMiddleName">Middle Name</label>
                    <input type="text" id="tpMiddleName" name="middle_name" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_middle); ?>" placeholder="Optional">
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpLastName">Last Name *</label>
                    <input type="text" id="tpLastName" name="last_name" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_last); ?>" required>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpBirthDate">Birth Date *</label>
                    <input type="date" id="tpBirthDate" name="birth_date" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_birthdate); ?>" required>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpGender">Gender *</label>
                    <select id="tpGender" name="gender" class="tp-edit-input" required>
                        <option value="">Select</option>
                        <?php foreach (['Male', 'Female', 'Non-binary', 'Prefer not to say'] as $g_opt): ?>
                            <option value="<?php echo $g_opt; ?>" <?php echo strcasecmp($tp_gender, $g_opt) === 0 ? 'selected' : ''; ?>>
                                <?php echo $g_opt; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpContact">Contact Number *</label>
                    <input type="tel" id="tpContact" name="contact_number" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_contact); ?>" placeholder="+63 917 000 0000" required>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpEmail">Email Address *</label>
                    <input type="email" id="tpEmail" name="email" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_email); ?>" required>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpMunicipality">City / Municipality *</label>
                    <select id="tpMunicipality" class="tp-edit-input" required>
                        <option value="">Select City / Municipality</option>
                        <?php foreach (array_keys($tp_municipalities) as $muni_opt): ?>
                            <option value="<?php echo htmlspecialchars($muni_opt, ENT_QUOTES); ?>" <?php echo strcasecmp($tp_muni, $muni_opt) === 0 ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($muni_opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpBarangay">Barangay *</label>
                    <select id="tpBarangay" name="barangay_id" class="tp-edit-input" required>
                        <option value="">Select Barangay</option>
                    </select>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpStreet">Street / Purok Address</label>
                    <input type="text" id="tpStreet" name="street_address" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_street); ?>" placeholder="Purok 2, Rizal Street">
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpSpec">FSL Specialization *</label>
                    <input type="text" id="tpSpec" name="fsl_specialization" class="tp-edit-input" value="<?php echo htmlspecialchars($tp_spec); ?>" placeholder="e.g., Basic FSL, Deaf Education" required>
                </div>

                <div class="cls-detail-row">
                    <label class="cls-row-label" for="tpExp">Years of Experience *</label>
                    <input type="number" id="tpExp" name="years_of_experience" min="0" max="60" class="tp-edit-input" value="<?php echo (int)$tp_experience; ?>" required>
                </div>
            </div>

            <div class="cls-modal-footer">
                <button type="button" class="btn-cls-secondary" id="cancelTeacherProfileBtn">Cancel</button>
                <button type="submit" class="btn-cls-primary" style="border:none;cursor:pointer;">Save Profile Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const tpModal = document.getElementById('teacherProfileModal');
    const closeTpBtn = document.getElementById('closeTeacherProfileBtn');
    const cancelTpBtn = document.getElementById('cancelTeacherProfileBtn');
    const profileCards = document.querySelectorAll('.sidebar-footer .profile-card');
    const muniSelect = document.getElementById('tpMunicipality');
    const brgySelect = document.getElementById('tpBarangay');
    const firstInput = document.getElementById('tpFirstName');
    const lastInput = document.getElementById('tpLastName');
    const titleEl = document.getElementById('tpModalTitle');
    const avatarEl = document.getElementById('tpAvatarInitials');

    const barangaysByMuni = <?php echo json_encode($tp_barangays_by_muni, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>;
    const savedBarangayId = <?php echo (int)$tp_barangay_id; ?>;

    function populateBarangays(muniName, preselectId) {
        if (!brgySelect) return;
        const list = barangaysByMuni[muniName] || [];
        brgySelect.innerHTML = '<option value="">Select Barangay</option>';
        list.forEach(function (b) {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            if (preselectId && Number(b.id) === Number(preselectId)) {
                opt.selected = true;
            }
            brgySelect.appendChild(opt);
        });
    }

    if (muniSelect) {
        if (muniSelect.value) {
            populateBarangays(muniSelect.value, savedBarangayId);
        }
        muniSelect.addEventListener('change', function () {
            populateBarangays(this.value, 0);
        });
    }

    function syncHeaderPreview() {
        const f = firstInput ? firstInput.value.trim() : '';
        const l = lastInput ? lastInput.value.trim() : '';
        if (titleEl) titleEl.textContent = (f + ' ' + l).trim() || 'Teacher Profile';
        if (avatarEl) {
            const init = ((f[0] || '') + (l[0] || f[1] || '')).toUpperCase();
            avatarEl.textContent = init || 'TC';
        }
    }
    if (firstInput) firstInput.addEventListener('input', syncHeaderPreview);
    if (lastInput) lastInput.addEventListener('input', syncHeaderPreview);

    if (!tpModal) return;

    function openTpModal() {
        tpModal.classList.add('open');
        tpModal.setAttribute('aria-hidden', 'false');
    }

    function closeTpModal() {
        tpModal.classList.remove('open');
        tpModal.setAttribute('aria-hidden', 'true');
    }

    profileCards.forEach(function (card) {
        card.setAttribute('role', 'button');
        card.setAttribute('tabindex', '0');
        card.setAttribute('title', 'Edit Teacher Profile');
        card.addEventListener('click', function (e) {
            if (e.target.closest('.logout-link')) return;
            openTpModal();
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openTpModal();
            }
        });
    });

    if (closeTpBtn) closeTpBtn.addEventListener('click', closeTpModal);
    if (cancelTpBtn) cancelTpBtn.addEventListener('click', closeTpModal);
    tpModal.addEventListener('click', function (e) {
        if (e.target === tpModal) closeTpModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && tpModal.classList.contains('open')) {
            closeTpModal();
        }
    });
})();
</script>