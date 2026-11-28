<!-- MODAL 1: CLASS DETAILS & DROP ENROLLMENT MODAL -->
<div id="modal-class-details" role="dialog" aria-modal="true" aria-labelledby="class-details-title" class="hidden fixed inset-0 z-50 bg-[#1A050D]/45 backdrop-blur-[1px] flex items-center justify-center p-4" onclick="if (event.target === this) closeModal('modal-class-details')">
  <div class="relative z-10 w-full max-w-[520px] max-h-[90vh] overflow-y-auto bg-white rounded-2xl p-6 sm:p-7 shadow-[0_24px_60px_rgba(26,5,13,0.28)] flex flex-col gap-4">
    <div class="flex items-start justify-between gap-3 pb-3.5 border-b border-[#5B0F2B]/[0.08]">
      <div class="flex items-start gap-3.5 min-w-0 flex-1">
        <div id="cd-modal-artwork" class="w-12 h-12 rounded-2xl bg-[#F7ECEF] text-[#5B0F2B] font-display font-bold text-[15px] flex items-center justify-center shrink-0 shadow-2xs mt-0.5">FSL</div>
        <div class="min-w-0 flex-1 flex flex-col gap-1">
          <div class="flex items-center justify-between gap-2">
            <span id="cd-modal-id" class="text-[10px] font-bold uppercase tracking-[0.10em] text-[#2E5D8A] tabular-code">CLS-001</span>
            <span id="cd-modal-status-badge" class="shrink-0"></span>
          </div>
          <h2 id="class-details-title" class="font-display font-bold text-[20px] sm:text-[22px] leading-snug text-[#5B0F2B] break-words">Class Details</h2>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-class-details')" aria-label="Close modal" class="w-8 h-8 rounded-full bg-[#F8F6F7] hover:bg-[#5B0F2B] hover:text-white text-[#5B0F2B] flex items-center justify-center shrink-0 transition-colors cursor-pointer">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <p id="cd-modal-description" class="text-[12.5px] text-[#2D141E] leading-relaxed"></p>
    <div id="cd-modal-details" class="flex flex-col gap-2"></div>
    <div id="cd-modal-sessions" class="flex flex-col gap-2"></div>
    <div id="cd-modal-footer" class="pt-3.5 border-t border-[#5B0F2B]/[0.08] flex items-center justify-between gap-2.5"></div>
  </div>
</div>

<!-- MODAL 2: CONFIRM CLASS REGISTRATION MODAL -->
<div id="modal-register-confirm" role="dialog" aria-modal="true" aria-labelledby="register-modal-title" class="hidden fixed inset-0 z-50 bg-[#1A050D]/45 backdrop-blur-[1px] flex items-center justify-center p-4" onclick="if (event.target === this) closeModal('modal-register-confirm')">
  <div class="w-full max-w-[440px] bg-white rounded-2xl p-6 shadow-[0_24px_60px_rgba(26,5,13,0.28)] flex flex-col gap-4 view-animate">
    <div>
      <span class="text-[9.5px] font-bold uppercase tracking-[0.10em] text-[#8D2645]">COURSE ENROLLMENT</span>
      <h2 id="register-modal-title" class="font-display font-bold text-[22px] text-[#5B0F2B] mt-0.5">Confirm <em class="italic text-[#8D2645]">Class Registration</em></h2>
    </div>
    <div id="register-modal-body" class="flex flex-col gap-2.5 text-[12px] text-[#6D4C57]">
      <div><span class="font-semibold text-[#5B0F2B]">Class code:</span> <span id="rc-modal-class-code"></span></div>
      <div><span class="font-semibold text-[#5B0F2B]">Class:</span> <span id="rc-modal-class-title"></span></div>
      <div><span class="font-semibold text-[#5B0F2B]">Venue:</span> <span id="rc-modal-venue"></span></div>
      <div><span class="font-semibold text-[#5B0F2B]">Instructor:</span> <span id="rc-modal-instructor"></span></div>
      <div><span class="font-semibold text-[#5B0F2B]">Learner:</span> <span id="rc-modal-learner-label"></span></div>
    </div>
    <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-[#5B0F2B]/[0.08]">
      <button type="button" onclick="closeModal('modal-register-confirm')" class="px-4 py-2 rounded-xl border border-[#5B0F2B]/15 text-[#5B0F2B] text-[12px] font-semibold hover:bg-[#F8F6F7] cursor-pointer">Cancel</button>
      <button id="rc-confirm-btn" type="button" class="px-5 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-semibold btn-burgundy-shadow cursor-pointer">Confirm Registration</button>
    </div>
  </div>
</div>

<!-- MODAL 3: LEARNER FULL PROFILE & EDIT MODAL -->
<!-- Learner Profile View & Edit Modal -->
<div id="modal-profile" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title" class="hidden fixed inset-0 z-50 bg-[#1A050D]/60 backdrop-blur-[3px] flex items-center justify-center p-4">
  <div class="bg-white rounded-3xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 shadow-[0_24px_60px_rgba(26,5,13,0.34)] border border-[#5B0F2B]/15 flex flex-col gap-4">
    
    <!-- Modal Header -->
    <div class="flex items-start justify-between gap-3 pb-3 border-b border-[#5B0F2B]/[0.08]">
      <div class="flex items-center gap-3 min-w-0">
        <div id="profile-modal-avatar" class="w-11 h-11 rounded-full bg-[#2E5D8A] text-white font-bold text-[15px] flex items-center justify-center shrink-0">
          GL
        </div>
        <div class="min-w-0">
          <span id="profile-modal-eyebrow" class="block text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#8D2645] tabular-code">
            LEARNER PROFILE
          </span>
          <h3 id="profile-modal-title" class="font-display font-bold text-[21px] leading-tight text-[#5B0F2B] truncate">
            Learner Name
          </h3>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-profile')" aria-label="Close modal" class="w-8 h-8 rounded-full bg-[#F8F6F7] hover:bg-[#5B0F2B] text-[#5B0F2B] hover:text-white flex items-center justify-center shrink-0 transition-colors cursor-pointer">
        ✕
      </button>
    </div>

    <!-- Read-Only Profile Rows -->
    <div id="profile-view-container" class="flex flex-col gap-2"></div>

    <!-- View Mode Footer -->
    <div id="profile-view-footer" class="flex items-center justify-between gap-2 pt-2 border-t border-[#5B0F2B]/[0.08]">
      <button type="button" onclick="toggleProfileEditMode(true)" class="px-4 py-2 rounded-xl bg-[#EEF4F9] hover:bg-[#2E5D8A] text-[#2E5D8A] hover:text-white border border-[#2E5D8A]/20 text-[12px] font-bold transition-colors cursor-pointer">
        Edit Profile
      </button>
      <button type="button" onclick="closeModal('modal-profile')" class="px-5 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-semibold transition-colors cursor-pointer">
        Close
      </button>
    </div>

    <!-- Edit Profile Form -->
    <form id="profile-edit-form" method="POST" action="actions/learner_update_profile.php" class="hidden flex-col gap-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />
      <input type="hidden" id="edit-learner-id-hidden" name="learner_id" />
      <input type="hidden" name="redirect" value="../<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>" />

      <div class="grid grid-cols-2 gap-2.5">
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Learner ID</label>
          <input id="edit-learner-id" type="text" readonly class="w-full h-9 rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/12 px-3 text-[12px] font-bold text-[#6D4C57] tabular-code" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Account ID</label>
          <input id="edit-account-id" type="text" readonly class="w-full h-9 rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/12 px-3 text-[12px] font-bold text-[#6D4C57] tabular-code" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">First Name</label>
          <input id="edit-first-name" name="first_name" type="text" required class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12px] font-semibold text-[#2D141E]" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Middle Name</label>
          <input id="edit-middle-name" name="middle_name" type="text" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12px] font-semibold text-[#2D141E]" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Last Name</label>
          <input id="edit-last-name" name="last_name" type="text" required class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12px] font-semibold text-[#2D141E]" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Birthdate</label>
          <input id="edit-birthdate" name="birthdate" type="date" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12px] font-semibold text-[#2D141E]" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Contact Number</label>
          <input id="edit-contact-number" name="contact_number" type="text" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12px] font-semibold text-[#2D141E]" />
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Gender</label>
          <select id="edit-gender" name="gender" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12px] font-semibold text-[#2D141E]">
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Non-binary">Non-binary</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2.5">
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Hearing Status</label>
          <select id="edit-hearing-status" name="hearing_status" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12px] font-semibold text-[#2D141E]">
            <option value="Hearing">Hearing</option>
            <option value="Deaf">Deaf</option>
            <option value="Hard of Hearing">Hard of Hearing</option>
          </select>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Speech Status</label>
          <select id="edit-speech-status" name="speech_status" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12px] font-semibold text-[#2D141E]">
            <option value="Verbal">Verbal</option>
            <option value="Non-verbal">Non-verbal</option>
            <option value="Limited Speech">Limited Speech</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">City / Municipality</label>
          <select id="edit-municipality-id" name="municipality_id" onchange="populateBarangayDropdown(this.value)" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12px] font-semibold text-[#2D141E]"></select>
        </div>
        <div>
          <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Barangay</label>
          <select id="edit-barangay-id" name="barangay_id" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12px] font-semibold text-[#2D141E]"></select>
        </div>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Street / Purok Address</label>
        <input id="edit-street-address" name="street_address" type="text" class="w-full h-9 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12px] font-semibold text-[#2D141E]" />
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase text-[#6D4C57] mb-1">Accessibility Notes</label>
        <textarea id="edit-accessibility-notes" name="accessibility_notes" rows="2" class="w-full rounded-xl bg-white border border-[#5B0F2B]/15 p-2.5 text-[12px] font-semibold text-[#2D141E]"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#5B0F2B]/[0.08]">
        <button type="button" onclick="toggleProfileEditMode(false)" class="px-4 py-2 rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] text-[#5B0F2B] text-[12px] font-semibold cursor-pointer">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-bold cursor-pointer">
          Save Changes
        </button>
      </div>
    </form>

  </div>
</div>
<!-- MODAL 4: LEARNER LOG OUT CONFIRMATION -->
<div id="modal-logout" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title" class="hidden fixed inset-0 z-50 bg-[#1A050D]/45 backdrop-blur-[1px] flex items-center justify-center p-4" onclick="if (event.target === this) closeModal('modal-logout')">
  <div class="w-full max-w-[390px] bg-white rounded-2xl p-6 shadow-[0_24px_60px_rgba(26,5,13,0.28)] flex flex-col gap-4 text-center view-animate">
    <div id="logout-modal-initials" class="w-11 h-11 rounded-full bg-[#2E5D8A] text-white font-bold text-[14px] flex items-center justify-center mx-auto">GL</div>
    <div>
      <span class="text-[9.5px] font-bold uppercase tracking-[0.10em] text-[#8D2645]">ALBAY PROVINCE LEARNER</span>
      <h2 id="logout-modal-title" class="font-display font-bold text-[22px] text-[#5B0F2B] mt-0.5">Sign Out of <em class="italic text-[#8D2645]">Portal?</em></h2>
      <p id="logout-modal-desc" class="text-[12px] text-[#6D4C57] mt-1 leading-relaxed">Your active learner session will be locked.</p>
    </div>
    <div class="flex items-center gap-2.5 pt-1">
      <button type="button" onclick="closeModal('modal-logout')" class="flex-1 py-2 rounded-xl border border-[#5B0F2B]/15 text-[#5B0F2B] text-[12px] font-semibold hover:bg-[#F8F6F7] cursor-pointer">Stay Signed In</button>
      <a href="actions/logout.php" class="flex-1 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-semibold cursor-pointer inline-flex items-center justify-center">Log out</a>
    </div>
  </div>
</div>

<!-- ==================== MODAL: ADD NEW LEARNER PROFILE ==================== -->

<?php
$alp_municipalities = [];

if (!isset($conn) || !($conn instanceof mysqli)) {
    @require_once __DIR__ . '/db_connect.php';
}

if (isset($conn) && $conn instanceof mysqli) {
    try {
        $res = $conn->query("SELECT municipality_id, municipality_name FROM municipalities ORDER BY municipality_name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $alp_municipalities[(int)$row['municipality_id']] = (string)$row['municipality_name'];
            }
        }
    } catch (Throwable $e) {
        error_log('Modal municipalities query error: ' . $e->getMessage());
    }
}
?>
<div id="addLearnerProfileModal" aria-hidden="true" class="hidden fixed inset-0 z-50 bg-[#1A050D]/55 backdrop-blur-xs flex items-center justify-center p-4">
  <div role="dialog" aria-modal="true" aria-labelledby="alpModalTitle" class="w-full max-w-[620px] max-h-[92vh] overflow-y-auto bg-white rounded-[22px] p-6 shadow-[0_24px_60px_rgba(26,5,13,0.32)]">

    <!-- Modal Header -->
    <div class="flex items-center justify-between gap-3 pb-4 mb-4 border-b border-[#5B0F2B]/[0.08]">
      <div class="flex items-center gap-3">
        <div id="alpInitialsPreview" class="w-10 h-10 rounded-full bg-[#2E5D8A] text-white font-bold text-[14px] flex items-center justify-center shrink-0">
          +
        </div>
        <div>
          <span class="block text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#2E5D8A]">
            NEW LEARNER PROFILE &bull; <?php echo sprintf('ACC-%03d', (int)($_SESSION['account_id'] ?? 0)); ?>
          </span>
          <h2 id="alpModalTitle" class="font-display font-bold text-[20px] leading-tight text-[#5B0F2B]">
            Add New Learner
          </h2>
        </div>
      </div>
      <button type="button" id="closeAlpModalBtn" aria-label="Close" class="w-8 h-8 rounded-full bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/10 text-[#5B0F2B] text-lg flex items-center justify-center cursor-pointer">
        &times;
      </button>
    </div>

    <!-- Form (matches register.php multi-column stacked-label grid) -->
    <form action="actions/learner_add_profile.php" method="POST" id="addLearnerProfileForm" class="flex flex-col gap-3.5">
      <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

      <!-- Row 1 (3 columns): First Name *, Middle Name, Last Name * -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label for="alpFirstName" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            First Name <span class="text-[#8D2645]">*</span>
          </label>
          <input type="text" id="alpFirstName" name="first_name" placeholder="e.g., Juan" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
        </div>
        <div>
          <label for="alpMiddleName" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Middle Name
          </label>
          <input type="text" id="alpMiddleName" name="middle_name" placeholder="Optional" class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
        </div>
        <div>
          <label for="alpLastName" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Last Name <span class="text-[#8D2645]">*</span>
          </label>
          <input type="text" id="alpLastName" name="last_name" placeholder="e.g., Dela Cruz" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
        </div>
      </div>

      <!-- Row 2 (3 columns): Birth Date *, Gender *, Contact Number * -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label for="alpBirthdate" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Birth Date <span class="text-[#8D2645]">*</span>
          </label>
          <input type="date" id="alpBirthdate" name="birth_date" max="<?php echo date('Y-m-d'); ?>" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
        </div>
        <div>
          <label for="alpGender" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Gender <span class="text-[#8D2645]">*</span>
          </label>
          <select id="alpGender" name="gender" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
            <option value="">Select gender...</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Prefer not to say">Prefer not to say</option>
          </select>
        </div>
        <div>
          <label for="alpContact" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Contact Number <span class="text-[#8D2645]">*</span>
          </label>
          <input type="tel" id="alpContact" name="contact_number" placeholder="09XXXXXXXXX" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
        </div>
      </div>

      <!-- Row 3 (2 columns): City/Municipality *, Barangay * -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label for="alpMunicipality" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            City / Municipality <span class="text-[#8D2645]">*</span>
          </label>
          <select id="alpMunicipality" name="municipality_id" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
            <option value="">Select City / Municipality...</option>
            <?php foreach ($alp_municipalities as $muni_id => $muni_name): ?>
              <option value="<?php echo (int)$muni_id; ?>">
                <?php echo htmlspecialchars($muni_name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="alpBarangay" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Barangay <span class="text-[#8D2645]">*</span>
          </label>
          <select id="alpBarangay" name="barangay_id" disabled required class="w-full h-10 rounded-xl bg-white disabled:bg-[#F8F6F7] disabled:text-[#7D6570] border border-[#5B0F2B]/15 px-2.5 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
            <option value="">Select Municipality First</option>
          </select>
        </div>
      </div>

      <!-- Row 4 (full width): Street / Purok Address -->
      <div>
        <label for="alpStreetAddress" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
          Street / Purok Address
        </label>
        <input type="text" id="alpStreetAddress" name="street_address" placeholder="e.g., Purok 3, Rizal St." class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-3 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
      </div>

      <!-- Row 5 (2 columns): Hearing Status *, Speech Status * -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label for="alpHearing" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Hearing Status <span class="text-[#8D2645]">*</span>
          </label>
          <select id="alpHearing" name="hearing_status" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
            <option value="">Select hearing status...</option>
            <option value="Hearing">Hearing</option>
            <option value="Mild to Moderate">Mild to Moderate</option>
            <option value="Severe">Severe</option>
            <option value="Profoundly Deaf">Profoundly Deaf</option>
          </select>
        </div>
        <div>
          <label for="alpSpeech" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
            Speech Status <span class="text-[#8D2645]">*</span>
          </label>
          <select id="alpSpeech" name="speech_status" required class="w-full h-10 rounded-xl bg-white border border-[#5B0F2B]/15 px-2.5 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]">
            <option value="">Select speech status...</option>
            <option value="Verbal">Verbal</option>
            <option value="Hard of Speech">Hard of Speech</option>
            <option value="Non-Verbal / Mute">Non-Verbal / Mute</option>
          </select>
        </div>
      </div>

      <!-- Row 6 (full width): Accessibility Notes (Optional) -->
      <div>
        <label for="alpAccessibilityNotes" class="block text-[10.5px] font-bold uppercase tracking-[0.04em] text-[#6D4C57] mb-1">
          Accessibility Notes <span class="font-normal normal-case text-[#7D6570]">(Optional)</span>
        </label>
        <textarea id="alpAccessibilityNotes" name="accessibility_notes" rows="2" placeholder="e.g., Prefers front-row seating with clear line of sight to instructor." class="w-full rounded-xl bg-white border border-[#5B0F2B]/15 p-2.5 text-[12.5px] font-semibold text-[#2D141E] focus:outline-none focus:border-[#8D2645]"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2.5 pt-4 mt-1 border-t border-[#5B0F2B]/[0.08]">
        <button type="button" id="cancelAlpModalBtn" class="px-4 py-2 rounded-full border border-[#5B0F2B]/15 bg-white hover:bg-[#F8F6F7] text-[#5B0F2B] text-[12px] font-bold cursor-pointer">
          Cancel
        </button>
        <button type="submit" class="px-5 py-2 rounded-full bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-bold cursor-pointer transition-colors">
          Save Learner Profile
        </button>
      </div>
    </form>
  </div>
</div>