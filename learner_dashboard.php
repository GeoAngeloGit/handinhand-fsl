<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activePage = 'dashboard';
$pageTitle  = 'Learner Dashboard — HandInHand';

$toast_text = '';
if (isset($_GET['profile_added'])) {
    $toast_text = 'New learner profile created and set as your active profile.';
} elseif (isset($_GET['profile_error'])) {
    $toast_text = match ($_GET['profile_error']) {
        'csrf'    => 'Session expired. Please reload the page and try again.',
        'missing' => 'Please complete all required learner profile fields.',
        default   => 'Could not add learner profile. Please check your inputs.',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include __DIR__ . '/includes/learner_head.php'; ?>
</head>
<body class="bg-[#F8F6F7] text-[#2D141E] min-h-screen lg:h-screen lg:overflow-hidden flex flex-col lg:flex-row overflow-x-hidden">

  <!-- Toast Notification Banner -->
  <div id="toast-banner" role="status" aria-live="polite" class="<?php echo $toast_text !== '' ? '' : 'hidden '; ?>fixed bottom-5 right-5 z-[60] max-w-[92vw] sm:max-w-md bg-[#5B0F2B] text-white border border-[#E5B4C2]/35 px-4 py-3 rounded-2xl shadow-[0_18px_40px_rgba(26,5,13,0.34)] flex items-center gap-3 text-[12.5px] font-medium">
    <span class="w-6 h-6 rounded-full bg-[#2E5D8A] text-white flex items-center justify-center shrink-0">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span id="toast-message" class="leading-snug"><?php echo htmlspecialchars($toast_text !== '' ? $toast_text : 'Action completed.'); ?></span>
  </div>

  <!-- Shared Left Sidebar -->
  <?php include __DIR__ . '/includes/learner_sidebar.php'; ?>

  <!-- Main Workspace -->
  <main class="flex-1 w-full min-w-0 lg:h-screen bg-[#F8F6F7] px-4 py-5 sm:px-7 sm:py-6 lg:px-[30px] lg:py-[24px] overflow-y-auto overflow-x-hidden">
    <div class="w-full max-w-none pb-8 flex flex-col gap-[16px]">

      <!-- Workspace Header -->
      <div class="w-full flex items-center justify-between gap-3">
        <div class="min-w-0">
          <span class="block text-[9.5px] font-bold uppercase tracking-[0.11em] text-[#8D2645]">
            LEARNER PORTAL • OVERVIEW
          </span>
          <h1 class="font-display font-bold text-[27px] sm:text-[30px] leading-tight text-[#5B0F2B] mt-0.5">
            Learner <em class="italic text-[#8D2645]">Dashboard</em>
          </h1>
        </div>
      </div>

      <!-- 2-Column Dashboard Split: Left Main Column (8 cols) + Right Session Calendar (4 cols) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start w-full min-w-0">

        <!-- Left Main Column (8 cols) -->
        <div class="lg:col-span-8 flex flex-col gap-[16px] min-w-0">
          <!-- Burgundy Welcome Card -->
          <div class="welcome-burgundy-card bg-gradient-to-br from-[#5B0F2B] to-[#8D2645] relative overflow-hidden rounded-2xl px-6 py-5 sm:px-7 sm:py-6 text-white flex flex-col justify-center">
            <div class="welcome-radial-glow absolute -right-16 -top-16 w-64 h-64 rounded-full pointer-events-none"></div>
            <div class="relative z-10 max-w-2xl flex flex-col gap-1.5">
              <h2 id="dashboard-banner-greeting" class="font-display font-bold text-[25px] sm:text-[28px] leading-tight text-white">
                Welcome, <em class="italic text-[#F7D6E0]">Learner!</em>
              </h2>
              <p id="dashboard-banner-subtitle" class="text-[12.5px] text-white/85 leading-relaxed"></p>
            </div>
          </div>

          <!-- 3 KPI Cards Row -->
          <div id="dashboard-kpi-grid" class="grid grid-cols-1 sm:grid-cols-3 gap-[14px]"></div>

          <!-- 2-Column Overview Cards: My Grades + Registered Classes -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-stretch">
            <div class="card-surface rounded-2xl p-5 flex flex-col gap-3.5 min-w-0">
              <div class="flex items-center justify-between gap-2 pb-3 border-b border-[#5B0F2B]/[0.08]">
                <div>
                  <span class="block text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#8D2645]">
                    PERFORMANCE RECORD
                  </span>
                  <h3 class="font-display font-bold text-[19px] leading-tight text-[#5B0F2B] mt-0.5">
                    My Grades
                  </h3>
                </div>
                <a
                  href="learner_grades.php"
                  class="px-3 py-1.5 rounded-xl bg-[#EEF4F9] hover:bg-[#2E5D8A] text-[#2E5D8A] hover:text-white border border-[#2E5D8A]/15 text-[11px] font-bold whitespace-nowrap shrink-0 transition-colors"
                >
                  View All
                </a>
              </div>
              <div id="dashboard-grades-preview" class="flex-1 flex flex-col gap-2.5"></div>
            </div>

            <div class="card-surface rounded-2xl p-5 flex flex-col gap-3.5 min-w-0">
              <div class="flex items-center justify-between gap-2 pb-3 border-b border-[#5B0F2B]/[0.08]">
                <div>
                  <span class="block text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#2E5D8A]">
                    ENROLLED COURSES
                  </span>
                  <h3 class="font-display font-bold text-[19px] leading-tight text-[#5B0F2B] mt-0.5">
                    Registered Classes
                  </h3>
                </div>
                <a
                  href="learner_classes.php"
                  class="px-3 py-1.5 rounded-xl bg-[#F7ECEF] hover:bg-[#5B0F2B] text-[#8D2645] hover:text-white border border-[#8D2645]/15 text-[11px] font-bold whitespace-nowrap shrink-0 transition-colors"
                >
                  Manage
                </a>
              </div>
              <div id="dashboard-enrolled-list" class="flex-1 flex flex-col gap-2.5"></div>
            </div>
          </div>
        </div>

        <!-- Right Interactive Session Calendar Column (4 cols) -->
        <div class="lg:col-span-4 min-w-0">
          <div id="dashboard-calendar-container" class="card-surface rounded-2xl p-5 flex flex-col gap-4 w-full min-w-0"></div>
        </div>

      </div>
    </div>
  </main>

  <!-- Shared Modals -->
  <?php include __DIR__ . '/includes/learner_modals.php'; ?>

  <!-- Front-End State & Interactive Controllers -->
  <script src="assets/js/learner.js?v=3"></script>
  <?php if ($toast_text !== ''): ?>
  <script>
    setTimeout(() => {
      document.getElementById('toast-banner')?.classList.add('hidden');
    }, 4500);
  </script>
  <?php endif; ?>
</body>
</html>