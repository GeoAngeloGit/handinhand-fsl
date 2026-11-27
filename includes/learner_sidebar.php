<?php
$activePage = $activePage ?? 'dashboard';
function navClasses(string $page, string $current): string {
    if ($page === $current) {
        return 'w-full flex items-center justify-between gap-2 px-[14px] py-[11px] rounded-[14px] text-left text-[13px] font-semibold bg-white/[0.13] border border-white/[0.14] text-white';
    }
    return 'w-full flex items-center justify-between gap-2 px-[14px] py-[11px] rounded-[14px] text-left text-[13px] font-medium border border-transparent text-white/[0.78] hover:text-white hover:bg-white/[0.07]';
}
?>

<!-- Mobile Top Header -->
<header class="lg:hidden sticky top-0 z-30 bg-[#5B0F2B] text-white px-4 py-3 flex items-center justify-between border-b border-white/10 shadow-md">
  <div class="flex items-center gap-2.5">
    <span class="font-display font-bold text-[21px] tracking-tight text-white">HandInHand</span>
    <span class="px-2 py-0.5 rounded-full bg-white/[0.14] text-white text-[9px] font-bold uppercase tracking-[0.10em]">LEARNER</span>
  </div>
  <button type="button" onclick="toggleMobileSidebar()" aria-label="Toggle Menu" class="p-2 rounded-xl bg-white/10 hover:bg-white/15 text-white cursor-pointer">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
  </button>
</header>

<div id="mobile-sidebar-backdrop" onclick="toggleMobileSidebar(false)" class="hidden fixed inset-0 z-30 bg-[#1A050D]/50 backdrop-blur-[1px] lg:hidden"></div>

<!-- Persistent Left Sidebar -->
<aside id="learner-sidebar" class="fixed lg:static top-0 left-0 z-40 h-screen w-[240px] shrink-0 bg-[#5B0F2B] text-white flex flex-col justify-between px-[18px] py-[26px] transition-transform duration-200 ease-out -translate-x-full lg:translate-x-0 select-none overflow-y-auto">
  <div class="flex flex-col gap-[26px] w-full">
    <!-- Brand -->
    <div class="flex flex-col gap-[5px] w-full px-1">
      <div class="flex items-center gap-2.5">
        <span class="font-display font-bold text-[18.5px] leading-none text-white tracking-tight">HandInHand</span>
        <span class="px-2 py-[2.5px] rounded-full bg-white/[0.14] text-white text-[8.5px] font-bold uppercase tracking-[0.09em]">LEARNER</span>
      </div>
      <p class="text-[11px] text-white/65">Albay Province • Bicol Region</p>
    </div>

    <!-- Multi-Page Navigation -->
    <nav class="flex flex-col gap-[6px] w-full">
      <span class="pl-[12px] pb-1 text-[9.5px] font-bold uppercase tracking-[0.12em] text-white/45">MAIN MENU</span>

      <a href="learner_dashboard.php" class="<?= navClasses('dashboard', $activePage) ?>">
        <span class="flex items-center gap-[12px]">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85"><rect width="7" height="7" x="3" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="14" rx="1.5"/><rect width="7" height="7" x="3" y="14" rx="1.5"/></svg>
          <span>Dashboard</span>
        </span>
      </a>

      <a href="learner_browse.php" class="<?= navClasses('browse', $activePage) ?>">
        <span class="flex items-center gap-[12px]">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <span>Browse Classes</span>
        </span>
      </a>

      <a href="learner_classes.php" class="<?= navClasses('classes', $activePage) ?>">
        <span class="flex items-center gap-[12px]">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          <span>My Classes</span>
        </span>
        <span id="sidebar-enrolled-badge" class="px-2 py-0.5 rounded-full bg-white/[0.16] text-white text-[10px] font-bold tabular-code">0</span>
      </a>

      <a href="learner_grades.php" class="<?= navClasses('grades', $activePage) ?>">
        <span class="flex items-center gap-[12px]">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
          <span>My Grades</span>
        </span>
      </a>
    </nav>
  </div>

  <!-- Switch User + Learner Profile Footer -->
  <div class="pt-3 flex flex-col gap-2.5">
    <div class="w-full rounded-2xl bg-white/[0.06] border border-white/[0.10] p-2.5 flex flex-col gap-1.5">
      <span id="switch-user-account-label" class="text-[9px] font-bold uppercase tracking-[0.10em] text-white/55 px-1 tabular-code">SWITCH USER</span>
      <button
        type="button"
        id="openNewLearnerProfileBtn"
        class="mt-2 w-full py-1.5 px-3 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white text-[11px] font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
      >
        <span class="text-[13px] leading-none">+</span>
        <span>Add New Learner</span>
      </button>
      <select id="learner-switcher" onchange="switchActiveLearner(this.value)" aria-label="Switch learner profile" class="sidebar-select w-full h-[32px] rounded-xl bg-white/[0.10] hover:bg-white/[0.16] border border-white/[0.14] px-2.5 text-[11.5px] font-semibold text-white focus:outline-none cursor-pointer truncate"></select>
    </div>

    <div
      role="button"
      tabindex="0"
      onclick="openProfileModal(false)"
      onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openProfileModal(false); }"
      class="w-full rounded-2xl bg-white/[0.08] hover:bg-white/[0.14] border border-white/[0.12] px-3 py-2.5 flex items-center gap-2.5 cursor-pointer transition-colors group"
    >
      <span id="sidebar-learner-initials" class="w-9 h-9 rounded-full bg-[#2E5D8A] text-white text-[11.5px] font-bold flex items-center justify-center shrink-0">—</span>
      <div class="min-w-0 flex-1 text-left">
        <span id="sidebar-learner-name" class="block text-[12px] font-bold text-white leading-tight truncate group-hover:underline">Loading...</span>
        <span class="block text-[10.5px] text-white/65 leading-tight mt-0.5 truncate tabular-code">
          <span id="sidebar-learner-code">—</span> •
          <button
            type="button"
            onclick="event.stopPropagation(); openModal('modal-logout')"
            class="text-white/85 hover:underline cursor-pointer bg-transparent border-0 p-0 font-inherit"
          >Log out</button>
        </span>
      </div>
    </div>
  </div>
</aside>