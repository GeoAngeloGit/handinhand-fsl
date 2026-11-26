<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$activePage = 'classes';
$pageTitle  = 'My Classes — HandInHand | Albay Province';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include __DIR__ . '/includes/learner_head.php'; ?>
</head>
<body class="bg-[#F8F6F7] text-[#2D141E] min-h-screen lg:h-screen lg:overflow-hidden flex flex-col lg:flex-row overflow-x-hidden">

  <!-- Toast Notification Banner -->
  <div id="toast-banner" role="status" aria-live="polite" class="hidden fixed bottom-5 right-5 z-[60] max-w-[92vw] sm:max-w-md bg-[#5B0F2B] text-white border border-[#E5B4C2]/35 px-4 py-3 rounded-2xl shadow-[0_18px_40px_rgba(26,5,13,0.34)] flex items-center gap-3 text-[12.5px] font-medium">
    <span class="w-6 h-6 rounded-full bg-[#2E5D8A] text-white flex items-center justify-center shrink-0">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
    </span>
    <span id="toast-message" class="leading-snug">Action completed.</span>
  </div>

  <!-- Shared Left Sidebar -->
  <?php include __DIR__ . '/includes/learner_sidebar.php'; ?>

  <!-- Main Workspace -->
  <main class="flex-1 w-full min-w-0 lg:h-screen bg-[#F8F6F7] px-4 py-5 sm:px-7 sm:py-6 lg:px-[30px] lg:py-[24px] overflow-y-auto overflow-x-hidden">
    <div class="w-full max-w-none pb-8 flex flex-col gap-[16px]">

      <!-- Workspace Header -->
      <div class="w-full flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
          <span class="block text-[9.5px] font-bold uppercase tracking-[0.11em] text-[#8D2645]">
            LEARNER PORTAL • ENROLLED COURSES
          </span>
          <h1 class="font-display font-bold text-[27px] sm:text-[30px] leading-tight text-[#5B0F2B] mt-0.5">
            My <em class="italic text-[#8D2645]">Classes</em>
          </h1>
        </div>
        <div class="flex items-center gap-2">
          <span id="my-classes-count" class="px-3 py-1.5 rounded-full bg-white border border-[#5B0F2B]/12 text-[11px] font-bold text-[#5B0F2B] tabular-code">
            0 Enrolled
          </span>
          <a
            href="learner_browse.php"
            class="px-3.5 py-1.5 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[11.5px] font-bold transition-colors no-underline"
          >
            + Browse More Classes
          </a>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="card-surface rounded-2xl p-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="relative flex-1 min-w-0">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#6D4C57] pointer-events-none"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input
            id="my-classes-search"
            type="text"
            oninput="renderClassesView()"
            placeholder="Filter your enrolled classes by title, code, teacher, or venue..."
            class="w-full h-[38px] rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/12 pl-9 pr-3 text-[12px] font-medium text-[#2D141E] placeholder:text-[#6D4C57]/70 focus:outline-none focus:border-[#8D2645]"
          />
        </div>

        <select
          id="my-classes-filter"
          onchange="renderClassesView()"
          class="brand-select h-[38px] rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/12 px-3 text-[12px] font-bold text-[#5B0F2B] focus:outline-none cursor-pointer"
        >
          <option value="ALL">All Enrolled Courses</option>
          <option value="ACTIVE">Active / Ongoing</option>
          <option value="COMPLETED">Completed</option>
        </select>
      </div>

      <!-- Enrolled Classes Grid -->
      <div id="my-classes-grid" class="grid grid-cols-1 lg:grid-cols-2 gap-4"></div>

    </div>
  </main>

  <!-- Shared Modals -->
  <?php include __DIR__ . '/includes/learner_modals.php'; ?>

  <!-- Front-End State & Interactive Controllers -->
  <script src="assets/js/learner.js"></script>
</body>
</html>