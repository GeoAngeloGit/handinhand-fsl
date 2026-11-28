const DB = window.HIH_INITIAL_STATE || {};

const _initToday = new Date();
const _initTodayIso = `${_initToday.getFullYear()}-${String(_initToday.getMonth() + 1).padStart(2, '0')}-${String(_initToday.getDate()).padStart(2, '0')}`;

const learnerState = {
  csrfToken: DB.csrfToken || '',
  activeLearnerId: Number(DB.activeLearnerId) || 0,
  learners: Array.isArray(DB.learners) ? DB.learners : [],
  classes: Array.isArray(DB.classes) ? DB.classes : [],
  schedules: Array.isArray(DB.schedules) ? DB.schedules : [],
  enrollments: Array.isArray(DB.enrollments) ? DB.enrollments : [],
  sessionGrades: Array.isArray(DB.sessionGrades) ? DB.sessionGrades : [],
  municipalities: Array.isArray(DB.municipalities) ? DB.municipalities : [],
  barangays: Array.isArray(DB.barangays) ? DB.barangays : [],

  // Interactive Session Calendar State (defaults to current local date)
  calendarYear: _initToday.getFullYear(),
  calendarMonth: _initToday.getMonth(),
  selectedCalendarDate: _initTodayIso,
  calendarPickerOpen: false,
  calendarClassFilter: 'ALL',
  calendarSortBy: 'DATE', // 'DATE' | 'TIME'
  calendarSortOrder: 'ASC' // 'ASC' | 'DESC'
};

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];
const SHORT_MONTH_NAMES = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];
const ARTWORK_THEMES = ['berry', 'slate', 'emerald', 'plum', 'amber', 'teal'];

/* =========================================================================
 * HELPERS & FORMATTERS (100% Database-Driven)
 * ========================================================================= */
function getActiveLearner() {
  return (
    learnerState.learners.find((l) => Number(l.learner_id) === Number(learnerState.activeLearnerId)) ||
    learnerState.learners[0] ||
    null
  );
}

function formatCode(prefix, id, pad = 3) {
  return `${prefix}-${String(id).padStart(pad, '0')}`;
}

function getInitials(first, last) {
  return `${(first || 'L')[0]}${(last || 'N')[0]}`.toUpperCase();
}

function escapeHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function getClassById(classId) {
  return learnerState.classes.find((c) => Number(c.class_id) === Number(classId)) || null;
}

function isEnrolledInClass(classId) {
  const learner = getActiveLearner();
  if (!learner) return false;
  return learnerState.enrollments.some(
    (e) =>
      Number(e.class_id) === Number(classId) &&
      Number(e.learner_id) === Number(learner.learner_id) &&
      ['enrolled', 'completed'].includes(String(e.status || '').toLowerCase())
  );
}

function parseSessionMinutes(timeStr) {
  const match = String(timeStr || '').trim().match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
  if (!match) return 0;
  let hours = parseInt(match[1], 10) % 12;
  const mins = parseInt(match[2], 10);
  if (match[3].toUpperCase() === 'PM') hours += 12;
  return hours * 60 + mins;
}

function getSchedulesForClass(classId) {
  return learnerState.schedules
    .filter((s) => Number(s.class_id) === Number(classId))
    .sort((a, b) => {
      const dCmp = String(a.session_date).localeCompare(String(b.session_date));
      if (dCmp !== 0) return dCmp;
      return parseSessionMinutes(a.start_time) - parseSessionMinutes(b.start_time);
    });
}

function getClassArtworkSvg(classId) {
  const palettes = {
    berry: { bg: '#5B0F2B', c1: '#8D2645', c2: '#E5B4C2', c3: '#F7D6E0' },
    slate: { bg: '#1F4366', c1: '#2E5D8A', c2: '#9EC3E6', c3: '#EEF4F9' },
    emerald: { bg: '#064E3B', c1: '#047857', c2: '#6EE7B7', c3: '#ECFDF5' },
    plum: { bg: '#3E0A1D', c1: '#701A3C', c2: '#D896AE', c3: '#F7ECEF' },
    amber: { bg: '#78350F', c1: '#B45309', c2: '#FCD34D', c3: '#FFFBEB' },
    teal: { bg: '#134E4A', c1: '#0F766E', c2: '#5EEAD4', c3: '#F0FDFA' }
  };
  const theme = ARTWORK_THEMES[(Math.max(1, Number(classId)) - 1) % ARTWORK_THEMES.length] || 'berry';
  const p = palettes[theme];
  return `
    <svg viewBox="0 0 80 80" class="w-full h-full block" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect width="80" height="80" fill="${p.bg}"/>
      <circle cx="62" cy="18" r="26" fill="${p.c1}"/>
      <path d="M0 54 Q 28 34, 54 58 T 80 52 L 80 80 L 0 80 Z" fill="${p.c2}" opacity="0.88"/>
      <circle cx="24" cy="28" r="9" fill="${p.c3}" opacity="0.92"/>
    </svg>
  `;
}

function getStatusBadgeHtml(status) {
  const normalized = String(status || 'Open');
  if (normalized === 'Open' || normalized === 'Active') {
    return `<span class="px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-bold shrink-0">Active</span>`;
  }
  if (normalized === 'Completed') {
    return `<span class="px-2.5 py-0.5 rounded-full bg-[#EEF4F9] border border-[#2E5D8A]/20 text-[#2E5D8A] text-[10px] font-bold shrink-0">Completed</span>`;
  }
  return `<span class="px-2.5 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-bold shrink-0">${escapeHtml(normalized)}</span>`;
}

function getGradePillHtml(grade, size = 'sm') {
  const pad = size === 'md' ? 'px-3 py-1 text-[11px]' : 'px-2.5 py-0.5 text-[10px]';
  if (grade === 'Satisfactory') {
    return `
      <span class="inline-flex items-center gap-1.5 ${pad} rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold whitespace-nowrap shrink-0">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
        <span>Satisfactory</span>
      </span>
    `;
  }
  if (grade === 'Needs Practice' || grade === 'Needs Improvement') {
    return `
      <span class="inline-flex items-center gap-1.5 ${pad} rounded-full bg-amber-50 border border-amber-200 text-amber-800 font-bold whitespace-nowrap shrink-0">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
        <span>${escapeHtml(grade)}</span>
      </span>
    `;
  }
  if (grade === 'Unsatisfactory') {
    return `
      <span class="inline-flex items-center gap-1.5 ${pad} rounded-full bg-rose-50 border border-rose-200 text-rose-700 font-bold whitespace-nowrap shrink-0">
        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
        <span>Unsatisfactory</span>
      </span>
    `;
  }
  return `
    <span class="inline-flex items-center gap-1.5 ${pad} rounded-full bg-[#EEF4F9] border border-[#2E5D8A]/20 text-[#2E5D8A] font-bold whitespace-nowrap shrink-0">
      <span class="w-1.5 h-1.5 rounded-full bg-[#2E5D8A]"></span>
      <span>${escapeHtml(grade || 'Pending Evaluation')}</span>
    </span>
  `;
}

function getEnrollmentRating(enr) {
  if (!enr) return '';
  if (enr.session_rating) return enr.session_rating;
  const rated = learnerState.sessionGrades.filter(
    (sg) =>
      (Number(sg.enrollment_id) === Number(enr.enrollment_id) ||
        Number(sg.class_id) === Number(enr.class_id)) &&
      sg.session_rating
  );
  if (rated.length === 0) return '';
  return rated[rated.length - 1].session_rating || '';
}

function getEnrollmentNotes(enr) {
  if (!enr) return '';
  if (enr.instructor_notes && String(enr.instructor_notes).trim() !== '') {
    return String(enr.instructor_notes).trim();
  }
  const withNotes = learnerState.sessionGrades.filter(
    (sg) =>
      (Number(sg.enrollment_id) === Number(enr.enrollment_id) ||
        Number(sg.class_id) === Number(enr.class_id)) &&
      sg.summary_notes &&
      String(sg.summary_notes).trim() !== ''
  );
  if (withNotes.length === 0) return '';
  return String(withNotes[withNotes.length - 1].summary_notes).trim();
}

function renderInstructorsPills(instructorStr) {
  const list = String(instructorStr || 'TBA')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);
  const avatarColors = ['bg-[#5B0F2B]', 'bg-[#2E5D8A]', 'bg-[#8D2645]', 'bg-emerald-700'];

  return `
    <div class="flex flex-wrap items-center gap-1.5">
      ${list
        .map((name, idx) => {
          const clean = name.replace(/^Teacher\s+/i, '');
          const parts = clean.split(/\s+/);
          const init =
            parts.length >= 2
              ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
              : clean.slice(0, 2).toUpperCase();
          return `
            <span class="inline-flex items-center gap-1.5 pl-1 pr-2.5 py-0.5 rounded-full bg-[#F7ECEF] border border-[#8D2645]/20 text-[#5B0F2B] text-[10px] font-bold leading-none">
              <span class="w-4 h-4 rounded-full ${avatarColors[idx % avatarColors.length]} text-white text-[8px] font-bold flex items-center justify-center shrink-0">
                ${init}
              </span>
              <span class="truncate">${escapeHtml(name)}</span>
            </span>
          `;
        })
        .join('')}
    </div>
  `;
}

/* =========================================================================
 * SIDEBAR, MODALS & POST ACTIONS
 * ========================================================================= */
function toggleMobileSidebar(forceOpen) {
  const sidebar = document.getElementById('learner-sidebar');
  const backdrop = document.getElementById('mobile-sidebar-backdrop');
  if (!sidebar || !backdrop) return;
  const isOpen = !sidebar.classList.contains('-translate-x-full');
  const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;
  sidebar.classList.toggle('-translate-x-full', !shouldOpen);
  backdrop.classList.toggle('hidden', !shouldOpen);
}

function openModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.classList.remove('hidden');
}

function closeModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.classList.add('hidden');
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    ['modal-class-details', 'modal-register-confirm', 'modal-profile', 'modal-logout'].forEach(closeModal);
  }
});

function showToast(message) {
  const banner = document.getElementById('toast-banner');
  const msgEl = document.getElementById('toast-message');
  if (!banner || !msgEl) return;
  msgEl.textContent = message;
  banner.classList.remove('hidden');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => banner.classList.add('hidden'), 3200);
}

function switchActiveLearner(newId) {
  const url = new URL(window.location.href);
  url.searchParams.set('switch_learner', newId);
  window.location.href = url.toString();
}

function submitPostAction(actionUrl, fields) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = actionUrl;
  Object.entries(fields).forEach(([key, val]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = val ?? '';
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}

function renderSidebarAndHeader() {
  const learner = getActiveLearner();
  if (!learner) return;

  const fullName = `${learner.first_name} ${learner.last_name}`.trim();
  const lrnCode = formatCode('LRN', learner.learner_id, 5);
  const accCode = formatCode('ACC', learner.account_id, 3);
  const initials = getInitials(learner.first_name, learner.last_name);
  const myEnrolled = learnerState.enrollments.filter(
    (e) =>
      Number(e.learner_id) === Number(learner.learner_id) &&
      (e.status === 'enrolled' || e.status === 'completed')
  );

  const switcher = document.getElementById('learner-switcher');
  if (switcher) {
    switcher.innerHTML = learnerState.learners
      .map(
        (l) => `
          <option value="${l.learner_id}" class="bg-[#5B0F2B] text-white font-semibold" ${
            Number(l.learner_id) === Number(learner.learner_id) ? 'selected' : ''
          }>
            ${escapeHtml(l.first_name)} ${escapeHtml(l.last_name)} (${formatCode('LRN', l.learner_id, 5)})
          </option>
        `
      )
      .join('');
  }

  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };
  setText('switch-user-account-label', `SWITCH USER • ${accCode}`);
  setText('sidebar-learner-initials', initials);
  setText('sidebar-learner-name', fullName);
  setText('sidebar-learner-code', lrnCode);
  setText('sidebar-enrolled-badge', String(myEnrolled.length));
  setText('logout-modal-initials', initials);

  const logoutDesc = document.getElementById('logout-modal-desc');
  if (logoutDesc) {
    logoutDesc.innerHTML = `Your active learner session for <strong class="text-[#2D141E]">${escapeHtml(
      fullName
    )} (${lrnCode})</strong> will be locked.`;
  }
}

/* =========================================================================
 * INTERACTIVE SESSION CALENDAR (RIGHT COLUMN OF DASHBOARD)
 * ========================================================================= */
function toggleCalendarPicker() {
  learnerState.calendarPickerOpen = !learnerState.calendarPickerOpen;
  renderDashboardView();
}

function setCalendarYear(year) {
  learnerState.calendarYear = Number(year);
  learnerState.selectedCalendarDate = null;
  renderDashboardView();
}

function setCalendarMonth(month) {
  learnerState.calendarMonth = Number(month);
  learnerState.selectedCalendarDate = null;
  learnerState.calendarPickerOpen = false;
  renderDashboardView();
}

function getTodayDateInfo() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const day = now.getDate();
  const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  const shortLabel = `${SHORT_MONTH_NAMES[month]} ${day}`;
  return { year, month, day, iso, shortLabel };
}

function resetCalendarToToday() {
  const today = getTodayDateInfo();
  learnerState.calendarYear = today.year;
  learnerState.calendarMonth = today.month;
  learnerState.selectedCalendarDate = today.iso;
  learnerState.calendarPickerOpen = false;
  renderDashboardView();
}

function changeCalendarMonth(delta) {
  let nextMonth = learnerState.calendarMonth + delta;
  let nextYear = learnerState.calendarYear;
  if (nextMonth < 0) {
    nextMonth = 11;
    nextYear -= 1;
  } else if (nextMonth > 11) {
    nextMonth = 0;
    nextYear += 1;
  }
  learnerState.calendarYear = nextYear;
  learnerState.calendarMonth = nextMonth;
  learnerState.selectedCalendarDate = null;
  renderDashboardView();
}

function selectCalendarDate(dateStr) {
  learnerState.selectedCalendarDate =
    learnerState.selectedCalendarDate === dateStr ? null : dateStr;
  renderDashboardView();
}

function setCalendarClassFilter(classId) {
  learnerState.calendarClassFilter = classId || 'ALL';
  renderDashboardView();
}

function setCalendarSortBy(sortBy) {
  learnerState.calendarSortBy = sortBy === 'TIME' ? 'TIME' : 'DATE';
  renderDashboardView();
}

function toggleCalendarSortOrder() {
  learnerState.calendarSortOrder = learnerState.calendarSortOrder === 'ASC' ? 'DESC' : 'ASC';
  renderDashboardView();
}

function buildInteractiveCalendarHtml() {
  const learner = getActiveLearner();
  if (!learner) return '';

  const todayInfo = getTodayDateInfo();
  const year = learnerState.calendarYear;
  const month = learnerState.calendarMonth;
  const myEnrollments = learnerState.enrollments.filter(
    (e) =>
      Number(e.learner_id) === Number(learner.learner_id) &&
      (e.status === 'enrolled' || e.status === 'completed')
  );
  const enrolledClassIds = new Set(myEnrollments.map((e) => Number(e.class_id)));

  const allEnrolledSchedules = learnerState.schedules.filter((s) =>
    enrolledClassIds.has(Number(s.class_id))
  );
  const sessionDateSet = new Set(allEnrolledSchedules.map((s) => s.session_date));

  const firstDayIndex = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const totalCells = Math.ceil((firstDayIndex + daysInMonth) / 7) * 7;

  const dayCells = [];
  for (let i = 0; i < totalCells; i++) {
    const dayNum = i - firstDayIndex + 1;
    if (dayNum < 1 || dayNum > daysInMonth) {
      dayCells.push({ empty: true });
    } else {
      const mm = String(month + 1).padStart(2, '0');
      const dd = String(dayNum).padStart(2, '0');
      dayCells.push({ empty: false, day: dayNum, dateStr: `${year}-${mm}-${dd}` });
    }
  }

  const monthPrefix = `${year}-${String(month + 1).padStart(2, '0')}`;
  let listedSchedules = allEnrolledSchedules.filter((s) => {
    if (learnerState.selectedCalendarDate) {
      return s.session_date === learnerState.selectedCalendarDate;
    }
    return String(s.session_date).startsWith(monthPrefix);
  });

  if (learnerState.calendarClassFilter !== 'ALL') {
    listedSchedules = listedSchedules.filter(
      (s) => Number(s.class_id) === Number(learnerState.calendarClassFilter)
    );
  }

  const sortDir = learnerState.calendarSortOrder === 'ASC' ? 1 : -1;
  listedSchedules.sort((a, b) => {
    if (learnerState.calendarSortBy === 'TIME') {
      const tCmp = parseSessionMinutes(a.start_time) - parseSessionMinutes(b.start_time);
      if (tCmp !== 0) return tCmp * sortDir;
      return String(a.session_date).localeCompare(String(b.session_date)) * sortDir;
    }
    const dCmp = String(a.session_date).localeCompare(String(b.session_date));
    if (dCmp !== 0) return dCmp * sortDir;
    return (parseSessionMinutes(a.start_time) - parseSessionMinutes(b.start_time)) * sortDir;
  });

  const listHeading = learnerState.selectedCalendarDate
    ? `Sessions on ${learnerState.selectedCalendarDate}`
    : `${MONTH_NAMES[month]} ${year} Sessions`;

  const pickerDrawerHtml = learnerState.calendarPickerOpen
    ? `
      <div class="rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.12] p-3 flex flex-col gap-2.5 view-animate">
        <div class="flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5">
            <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">Year:</span>
            ${[2025, 2026, 2027]
              .map(
                (y) => `
                  <button
                    type="button"
                    onclick="setCalendarYear(${y})"
                    class="px-2 py-0.5 rounded-md text-[10.5px] font-bold tabular-code cursor-pointer transition-colors ${
                      y === year
                        ? 'bg-[#5B0F2B] text-white'
                        : 'bg-white border border-[#5B0F2B]/12 text-[#5B0F2B] hover:bg-[#F7ECEF]'
                    }"
                  >${y}</button>
                `
              )
              .join('')}
          </div>
          <button
            type="button"
            onclick="resetCalendarToToday()"
            class="px-2 py-0.5 rounded-md bg-[#EEF4F9] hover:bg-[#2E5D8A] text-[#2E5D8A] hover:text-white text-[10px] font-bold transition-colors cursor-pointer"
          >Today (${todayInfo.shortLabel})</button>
        </div>
        <div class="grid grid-cols-4 gap-1">
          ${SHORT_MONTH_NAMES.map(
            (mName, mIdx) => `
              <button
                type="button"
                onclick="setCalendarMonth(${mIdx})"
                class="py-1 rounded-lg text-[10.5px] font-bold transition-colors cursor-pointer ${
                  mIdx === month
                    ? 'bg-[#8D2645] text-white shadow-2xs'
                    : 'bg-white border border-[#5B0F2B]/[0.08] text-[#2D141E] hover:bg-[#F7ECEF] hover:text-[#5B0F2B]'
                }"
              >${mName}</button>
            `
          ).join('')}
        </div>
      </div>
    `
    : '';

  const gridHtml = dayCells
    .map((cell) => {
      if (cell.empty) return `<div class="h-8"></div>`;
      const hasSession = sessionDateSet.has(cell.dateStr);
      const isSelected = learnerState.selectedCalendarDate === cell.dateStr;
      const isToday = cell.dateStr === todayInfo.iso;

      let btnClass =
        'w-8 h-8 mx-auto rounded-full flex items-center justify-center text-[11.5px] font-semibold tabular-code transition-all cursor-pointer ';
      if (isToday) {
        btnClass +=
          'bg-[#5B0F2B] hover:bg-[#8D2645] text-white font-bold shadow-xs' +
          (isSelected ? ' ring-2 ring-[#2E5D8A] scale-105' : '');
      } else if (isSelected) {
        btnClass +=
          'bg-[#8D2645] text-white font-bold ring-2 ring-[#5B0F2B]/30 scale-105 shadow-xs';
      } else if (hasSession) {
        btnClass +=
          'bg-[#F7D6E0] hover:bg-[#8D2645] text-[#5B0F2B] hover:text-white font-bold border border-[#8D2645]/25';
      } else {
        btnClass += 'text-[#2D141E] hover:bg-[#F7ECEF] hover:text-[#5B0F2B]';
      }

      return `<button type="button" onclick="selectCalendarDate('${cell.dateStr}')" class="${btnClass}">${cell.day}</button>`;
    })
    .join('');

  const enrolledClassesList = myEnrollments
    .map((e) => getClassById(e.class_id))
    .filter(Boolean);

  const sessionsHtml =
    listedSchedules.length > 0
      ? listedSchedules
          .map((sch) => {
            const cls = getClassById(sch.class_id);
            const clsCode = formatCode('CLS', sch.class_id);
            const schCode = formatCode('SCH', sch.schedule_id);
            return `
              <button
                type="button"
                onclick="openClassDetailsModal(${sch.class_id})"
                class="w-full text-left rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/[0.07] p-2.5 flex flex-col gap-1 transition-colors cursor-pointer"
              >
                <div class="flex items-center justify-between gap-2 text-[10px] font-bold tabular-code">
                  <span class="text-[#2E5D8A]">${clsCode} • ${schCode}</span>
                  <span class="text-[#8D2645]">${sch.session_date}</span>
                </div>
                <span class="text-[12px] font-bold text-[#5B0F2B] truncate">
                  ${cls ? escapeHtml(cls.class_title) : clsCode}
                </span>
                <span class="text-[10.5px] text-[#6D4C57] tabular-code truncate">
                  🕒 ${sch.start_time} • ${escapeHtml((sch.venue || '').split('(')[0].trim())}
                </span>
              </button>
            `;
          })
          .join('')
      : `
        <div class="rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] px-3 py-4 text-center text-[11px] text-[#6D4C57]">
          No matching classes scheduled for this selection.
        </div>
      `;

  return `
    <div class="flex flex-wrap xl:flex-nowrap items-center justify-between gap-2 pb-3 border-b border-[#5B0F2B]/[0.08]">
      <div class="min-w-0 shrink-0">
        <span class="block text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#8D2645]">CLASS SCHEDULE</span>
        <h3 class="font-display font-bold text-[18.5px] leading-tight text-[#5B0F2B] mt-0.5 whitespace-nowrap">Session Calendar</h3>
      </div>

      <div class="flex items-center gap-1 shrink-0 ml-auto">
        <button type="button" onclick="changeCalendarMonth(-1)" aria-label="Previous month" class="w-6.5 h-6.5 rounded-full bg-[#F8F6F7] hover:bg-[#5B0F2B] text-[#5B0F2B] hover:text-white flex items-center justify-center transition-colors cursor-pointer">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button type="button" onclick="toggleCalendarPicker()" class="px-2 py-1 rounded-full bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/[0.10] text-[11px] font-bold text-[#5B0F2B] inline-flex items-center gap-1 whitespace-nowrap transition-colors cursor-pointer">
          <span>${SHORT_MONTH_NAMES[month]} ${year}</span>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="transition-transform ${learnerState.calendarPickerOpen ? 'rotate-180' : ''}"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <button type="button" onclick="changeCalendarMonth(1)" aria-label="Next month" class="w-6.5 h-6.5 rounded-full bg-[#F8F6F7] hover:bg-[#5B0F2B] text-[#5B0F2B] hover:text-white flex items-center justify-center transition-colors cursor-pointer">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
    </div>

    ${pickerDrawerHtml}

    <div class="grid grid-cols-7 gap-1 text-center text-[9.5px] font-bold uppercase tracking-[0.06em] text-[#6D4C57]">
      <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
    </div>

    <div class="grid grid-cols-7 gap-y-1.5 gap-x-1">${gridHtml}</div>

    <div class="flex flex-wrap items-center justify-between gap-2 text-[10px] font-semibold text-[#6D4C57] pt-1 border-t border-[#5B0F2B]/[0.06]">
      <div class="flex items-center gap-2.5 flex-wrap">
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#5B0F2B]"></span><span>Today (${todayInfo.shortLabel})</span></span>
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#F7D6E0] border border-[#8D2645]/30"></span><span>Scheduled</span></span>
      </div>
      ${
        learnerState.selectedCalendarDate
          ? `<button type="button" onclick="selectCalendarDate('${learnerState.selectedCalendarDate}')" class="text-[#8D2645] font-bold hover:underline cursor-pointer">Show Full Month</button>`
          : `<span>Click a date to filter</span>`
      }
    </div>

    <div class="pt-2.5 border-t border-[#5B0F2B]/[0.07] flex flex-col gap-2">
      <div class="flex items-center gap-1.5">
        <select onchange="setCalendarClassFilter(this.value)" class="brand-select flex-1 min-w-0 h-[30px] rounded-lg bg-[#F8F6F7] border border-[#5B0F2B]/[0.12] px-2.5 text-[10.5px] font-bold text-[#5B0F2B] focus:outline-none cursor-pointer truncate">
          <option value="ALL" ${learnerState.calendarClassFilter === 'ALL' ? 'selected' : ''}>All Enrolled Classes (${enrolledClassesList.length})</option>
          ${enrolledClassesList
            .map(
              (c) => `
                <option value="${c.class_id}" ${Number(learnerState.calendarClassFilter) === Number(c.class_id) ? 'selected' : ''}>
                  ${formatCode('CLS', c.class_id)} — ${escapeHtml(c.class_title)}
                </option>
              `
            )
            .join('')}
        </select>

        <div class="inline-flex items-center p-0.5 h-[30px] rounded-lg bg-[#F8F6F7] border border-[#5B0F2B]/[0.12] shrink-0">
          <button type="button" onclick="setCalendarSortBy('DATE')" class="px-2 h-full rounded-md text-[10px] font-bold transition-colors cursor-pointer ${learnerState.calendarSortBy === 'DATE' ? 'bg-[#5B0F2B] text-white' : 'text-[#6D4C57] hover:text-[#5B0F2B]'}">Date</button>
          <button type="button" onclick="setCalendarSortBy('TIME')" class="px-2 h-full rounded-md text-[10px] font-bold transition-colors cursor-pointer ${learnerState.calendarSortBy === 'TIME' ? 'bg-[#5B0F2B] text-white' : 'text-[#6D4C57] hover:text-[#5B0F2B]'}">Time</button>
        </div>

        <button type="button" onclick="toggleCalendarSortOrder()" title="Toggle sort direction" class="w-[30px] h-[30px] rounded-lg bg-[#F8F6F7] hover:bg-[#5B0F2B] border border-[#5B0F2B]/[0.12] text-[#5B0F2B] hover:text-white flex items-center justify-center text-[11px] font-bold shrink-0 transition-colors cursor-pointer">
          ${learnerState.calendarSortOrder === 'ASC' ? '↑' : '↓'}
        </button>
      </div>

      <div class="flex items-center justify-between gap-2">
        <span class="text-[10px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">${listHeading}</span>
        <span class="text-[10px] font-bold text-[#2E5D8A] tabular-code">${listedSchedules.length} Session${listedSchedules.length === 1 ? '' : 's'}</span>
      </div>

      <div class="flex flex-col gap-2 max-h-[210px] overflow-y-auto pr-0.5">${sessionsHtml}</div>
    </div>
  `;
}

/* =========================================================================
 * DASHBOARD RENDERER
 * ========================================================================= */
function renderDashboardView() {
  const learner = getActiveLearner();
  if (!learner) return;

  const greetingEl = document.getElementById('dashboard-banner-greeting');
  const subtitleEl = document.getElementById('dashboard-banner-subtitle');
  const kpiGrid = document.getElementById('dashboard-kpi-grid');
  const gradesPreview = document.getElementById('dashboard-grades-preview');
  const enrolledList = document.getElementById('dashboard-enrolled-list');
  const calendarContainer = document.getElementById('dashboard-calendar-container');

  const displayName = `${learner.first_name} ${learner.last_name}`.trim();
  const myEnrollments = learnerState.enrollments.filter(
    (e) =>
      Number(e.learner_id) === Number(learner.learner_id) &&
      (e.status === 'enrolled' || e.status === 'completed')
  );
  const enrolledCount = myEnrollments.length;
  const openClassesCount = learnerState.classes.filter(
    (c) => (c.status === 'Open' || c.status === 'Active') && !isEnrolledInClass(c.class_id)
  ).length;

  if (greetingEl) {
    greetingEl.innerHTML = `Welcome, <em class="italic text-[#F7D6E0]">${escapeHtml(displayName)}!</em>`;
  }
  if (subtitleEl) {
    subtitleEl.textContent = `You are enrolled in ${enrolledCount} FSL class${
      enrolledCount === 1 ? '' : 'es'
    } in Albay Province, with ${openClassesCount} open course${
      openClassesCount === 1 ? '' : 's'
    } available for registration.`;
  }

  const enrolledClassIds = new Set(myEnrollments.map((e) => Number(e.class_id)));
  const upcomingSessionsCount = learnerState.schedules.filter((s) =>
    enrolledClassIds.has(Number(s.class_id))
  ).length;
  const satisfactoryCount = myEnrollments.filter((e) => getEnrollmentRating(e) === 'Satisfactory').length;

  if (kpiGrid) {
    const kpis = [
      {
        label: 'MY CLASSES',
        badge: 'ENROLLED',
        badgeClass: 'bg-emerald-50 text-emerald-700',
        value: enrolledCount,
        sub: `${openClassesCount} open classes available`,
        href: 'learner_classes.php'
      },
      {
        label: 'TIMETABLE',
        badge: 'UPCOMING',
        badgeClass: 'bg-[#EEF4F9] text-[#2E5D8A]',
        value: upcomingSessionsCount,
        sub: 'Scheduled Albay hub sessions',
        href: 'learner_classes.php'
      },
      {
        label: 'MY GRADES',
        badge: 'EVALUATED',
        badgeClass: 'bg-[#5B0F2B]/[0.09] text-[#5B0F2B]',
        value: `${satisfactoryCount}/${enrolledCount}`,
        sub: 'Satisfactory evaluations',
        href: 'learner_grades.php'
      }
    ];

    kpiGrid.innerHTML = kpis
      .map(
        (k) => `
          <a href="${k.href}" class="interactive-card rounded-2xl px-5 py-4 text-left flex flex-col gap-1.5 no-underline">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#6D4C57]">${k.label}</span>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold ${k.badgeClass}">${k.badge}</span>
            </div>
            <p class="font-display font-bold text-[30px] leading-none text-[#5B0F2B] tabular-code">${k.value}</p>
            <span class="text-[11px] font-medium text-[#6D4C57]">${k.sub}</span>
          </a>
        `
      )
      .join('');
  }

  if (gradesPreview) {
    if (myEnrollments.length === 0) {
      gradesPreview.innerHTML = `
        <div class="rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] p-4 text-center text-[11.5px] text-[#6D4C57]">
          No course grades recorded yet. Browse open classes to enroll!
        </div>
      `;
    } else {
      gradesPreview.innerHTML = myEnrollments
        .map((enr) => {
          const cls = getClassById(enr.class_id);
          const clsCode = formatCode('CLS', enr.class_id);
          return `
            <a
              href="learner_grades.php?enrollment_id=${enr.enrollment_id}"
              class="w-full text-left rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/[0.07] px-3.5 py-3 flex flex-col gap-2 transition-colors no-underline"
            >
              <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-[#5B0F2B]/[0.07]">
                <span class="text-[10px] font-bold text-[#2E5D8A] tabular-code">${clsCode}</span>
                ${getGradePillHtml(getEnrollmentRating(enr), 'sm')}
              </div>
              <span class="text-[12.5px] font-bold text-[#5B0F2B] truncate">
                ${cls ? escapeHtml(cls.class_title) : clsCode}
              </span>
            </a>
          `;
        })
        .join('');
    }
  }

  if (enrolledList) {
    if (myEnrollments.length === 0) {
      enrolledList.innerHTML = `
        <div class="rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] p-4 text-center text-[11.5px] text-[#6D4C57]">
          You are not enrolled in any classes yet.
        </div>
      `;
    } else {
      enrolledList.innerHTML = myEnrollments
        .map((enr) => {
          const cls = getClassById(enr.class_id);
          const clsCode = formatCode('CLS', enr.class_id);
          const schedules = getSchedulesForClass(enr.class_id);
          const nextSch = schedules[0];
          return `
            <button
              type="button"
              onclick="openClassDetailsModal(${enr.class_id})"
              class="w-full text-left rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/[0.07] px-3.5 py-3 flex flex-col gap-1 transition-colors cursor-pointer"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] font-bold text-[#8D2645] tabular-code">${clsCode}</span>
                ${cls ? getStatusBadgeHtml(cls.status) : ''}
              </div>
              <span class="text-[12.5px] font-bold text-[#5B0F2B] truncate">
                ${cls ? escapeHtml(cls.class_title) : clsCode}
              </span>
              <span class="text-[10.5px] text-[#6D4C57] truncate">
                ${
                  nextSch
                    ? `Next: ${nextSch.session_date} • ${nextSch.start_time}`
                    : cls
                    ? escapeHtml(cls.venue)
                    : ''
                }
              </span>
            </button>
          `;
        })
        .join('');
    }
  }

  if (calendarContainer) {
    calendarContainer.innerHTML = buildInteractiveCalendarHtml();
  }
}

/* =========================================================================
 * BROWSE CLASSES RENDERER & REGISTRATION (Connected to learner_register_class.php)
 * ========================================================================= */
function populateBrowseVenueFilter() {
  const venueSelect = document.getElementById('browse-venue-filter');
  if (!venueSelect || venueSelect.dataset.populated === '1') return;

  const venues = [...new Set(learnerState.classes.map((c) => c.venue).filter(Boolean))].sort();
  venues.forEach((v) => {
    const opt = document.createElement('option');
    opt.value = v;
    opt.textContent = v;
    venueSelect.appendChild(opt);
  });
  venueSelect.dataset.populated = '1';
}

function renderBrowseView() {
  const grid = document.getElementById('browse-classes-grid');
  if (!grid) return;

  populateBrowseVenueFilter();

  const searchVal = (document.getElementById('browse-search-input')?.value || '').trim().toLowerCase();
  const venueVal = document.getElementById('browse-venue-filter')?.value || 'ALL';
  const statusVal = document.getElementById('browse-status-filter')?.value || 'ALL';

  const filtered = learnerState.classes.filter((cls) => {
    const clsCode = formatCode('CLS', cls.class_id).toLowerCase();
    const enrolled = isEnrolledInClass(cls.class_id);
    const isOpen = cls.status === 'Open' || cls.status === 'Active';
    const hasSeats = Number(cls.current_enrolled) < Number(cls.max_capacity);

    if (venueVal !== 'ALL' && cls.venue !== venueVal) return false;
    if (statusVal === 'OPEN_AVAILABLE' && (!isOpen || !hasSeats || enrolled)) return false;
    if (statusVal === 'ENROLLED' && !enrolled) return false;

    if (searchVal) {
      const hay = `${clsCode} ${cls.class_title} ${cls.description || ''} ${cls.instructor || ''} ${cls.venue || ''}`.toLowerCase();
      if (!hay.includes(searchVal)) return false;
    }
    return true;
  });

  const countBadge = document.getElementById('browse-results-count');
  if (countBadge) {
    countBadge.textContent = `${filtered.length} Class${filtered.length === 1 ? '' : 'es'} Found`;
  }

  if (filtered.length === 0) {
    grid.innerHTML = `
      <div class="col-span-full card-surface rounded-2xl p-8 text-center flex flex-col items-center gap-2">
        <p class="font-display font-bold text-[20px] text-[#5B0F2B]">No matching FSL classes found</p>
        <p class="text-[12px] text-[#6D4C57]">Try clearing your search filter or selecting a different Albay venue.</p>
      </div>
    `;
    return;
  }

  grid.innerHTML = filtered
    .map((cls) => {
      const clsCode = formatCode('CLS', cls.class_id);
      const enrolled = isEnrolledInClass(cls.class_id);
      const enrolledCount = Number(cls.current_enrolled) || 0;
      const maxCap = Math.max(1, Number(cls.max_capacity) || 1);
      const isFull = enrolledCount >= maxCap;
      const isOpen = cls.status === 'Open' || cls.status === 'Active';
      const pct = Math.min(100, Math.round((enrolledCount / maxCap) * 100));
      const schedules = getSchedulesForClass(cls.class_id);
      const nextSch = schedules[0];

      let actionBtnHtml = '';
      if (enrolled) {
        actionBtnHtml = `
          <span class="px-3.5 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-[11.5px] font-bold">
            ✓ Enrolled
          </span>
        `;
      } else if (!isOpen) {
        actionBtnHtml = `
          <span class="px-3.5 py-2 rounded-xl bg-slate-100 text-slate-500 text-[11.5px] font-bold">
            Closed
          </span>
        `;
      } else if (isFull) {
        actionBtnHtml = `
          <span class="px-3.5 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-[11.5px] font-bold">
            Class Full
          </span>
        `;
      } else {
        actionBtnHtml = `
          <button
            type="button"
            onclick="openRegisterConfirmModal(${cls.class_id})"
            class="px-4 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[11.5px] font-bold transition-colors cursor-pointer"
          >
            Register Class
          </button>
        `;
      }

      return `
        <article class="card-surface rounded-2xl p-5 flex flex-col justify-between gap-4 min-w-0">
          <div class="flex flex-col gap-3">
            <div class="flex items-start gap-3">
              <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-[#5B0F2B]/10">
                ${getClassArtworkSvg(cls.class_id)}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-[10px] font-bold text-[#8D2645] tabular-code">${clsCode}</span>
                  ${getStatusBadgeHtml(cls.status)}
                </div>
                <h2 class="font-display font-bold text-[19px] leading-snug text-[#5B0F2B] mt-0.5 line-clamp-2">
                  ${escapeHtml(cls.class_title)}
                </h2>
              </div>
            </div>

            <p class="text-[12px] text-[#6D4C57] leading-relaxed line-clamp-2">
              ${escapeHtml(cls.description || 'Filipino Sign Language community training session in Albay Province.')}
            </p>

            <div class="flex flex-col gap-1.5 text-[11px] text-[#6D4C57]">
              <div class="truncate"><strong class="text-[#5B0F2B]">Venue:</strong> ${escapeHtml(cls.venue || 'TBA')}</div>
              <div class="truncate tabular-code">
                <strong class="text-[#5B0F2B]">Schedule:</strong>
                ${nextSch ? `${nextSch.session_date} • ${nextSch.start_time} (${schedules.length} session${schedules.length === 1 ? '' : 's'})` : 'Schedule TBA'}
              </div>
            </div>

            <div>${renderInstructorsPills(cls.instructor)}</div>
          </div>

          <div class="pt-3 border-t border-[#5B0F2B]/[0.08] flex flex-col gap-3">
            <div class="flex flex-col gap-1">
              <div class="flex items-center justify-between text-[10.5px] font-bold tabular-code">
                <span class="text-[#6D4C57]">Seat Availability</span>
                <span class="${isFull ? 'text-rose-700' : 'text-[#2E5D8A]'}">${enrolledCount} / ${maxCap} Learners</span>
              </div>
              <div class="w-full h-1.5 rounded-full bg-[#F8F6F7] overflow-hidden">
                <div class="h-full rounded-full ${isFull ? 'bg-rose-500' : 'bg-[#2E5D8A]'}" style="width: ${pct}%"></div>
              </div>
            </div>

            <div class="flex items-center justify-between gap-2">
              <button
                type="button"
                onclick="openClassDetailsModal(${cls.class_id})"
                class="px-3 py-2 rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/12 text-[#5B0F2B] text-[11.5px] font-bold transition-colors cursor-pointer"
              >
                Details
              </button>
              ${actionBtnHtml}
            </div>
          </div>
        </article>
      `;
    })
    .join('');
}

function openRegisterConfirmModal(classId) {
  const cls = getClassById(classId);
  const learner = getActiveLearner();
  if (!cls || !learner) return;

  const modal = document.getElementById('modal-register-confirm');
  if (!modal) {
    confirmClassRegistration(classId);
    return;
  }

  document.getElementById('rc-modal-class-code').textContent = formatCode('CLS', cls.class_id);
  document.getElementById('rc-modal-class-title').textContent = cls.class_title;
  document.getElementById('rc-modal-venue').textContent = cls.venue || 'TBA';
  document.getElementById('rc-modal-instructor').textContent = cls.instructor || 'TBA';
  document.getElementById('rc-modal-learner-label').textContent = `${learner.first_name} ${learner.last_name} (${formatCode('LRN', learner.learner_id, 5)})`;

  const confirmBtn = document.getElementById('rc-confirm-btn');
  if (confirmBtn) {
    confirmBtn.onclick = () => confirmClassRegistration(cls.class_id);
  }

  openModal('modal-register-confirm');
}

function confirmClassRegistration(classId) {
  const learner = getActiveLearner();
  if (!learner) return;
  submitPostAction('actions/learner_register_class.php', {
    csrf_token: learnerState.csrfToken,
    class_id: classId,
    learner_id: learner.learner_id,
    redirect: '../' + (window.location.pathname.split('/').pop() || 'learner_browse.php')
  });
}

/* =========================================================================
 * CLASS DETAILS MODAL & DROP ACTION
 * ========================================================================= */
function openClassDetailsModal(classId) {
  const cls = getClassById(classId);
  if (!cls) return;

  const enrolledRecord = learnerState.enrollments.find(
    (e) => Number(e.class_id) === Number(classId)
  );
  const schedules = getSchedulesForClass(classId);
  const clsCode = formatCode('CLS', cls.class_id);

  const artworkEl = document.getElementById('cd-modal-artwork');
  if (artworkEl) artworkEl.innerHTML = getClassArtworkSvg(cls.class_id);

  document.getElementById('cd-modal-id').textContent = clsCode;
  document.getElementById('cd-modal-status-badge').innerHTML = getStatusBadgeHtml(cls.status);
  document.getElementById('class-details-title').textContent = cls.class_title;
  document.getElementById('cd-modal-description').textContent = cls.description || '';

  const rows = [
    { label: 'Class Venue', value: escapeHtml(cls.venue) },
    { label: 'Assigned Teachers', value: renderInstructorsPills(cls.instructor), isHtml: true },
    { label: 'Capacity', value: `${cls.current_enrolled} / ${cls.max_capacity} Learners` }
  ];
  if (enrolledRecord) {
    rows.push({
      label: 'Current Grade',
      value: getGradePillHtml(getEnrollmentRating(enrolledRecord), 'sm'),
      isHtml: true
    });
  }

  document.getElementById('cd-modal-details').innerHTML = rows
    .map(
      (r) => `
        <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] text-[12px]">
          <span class="font-semibold text-[#6D4C57] shrink-0">${r.label}</span>
          ${
            r.isHtml
              ? `<div class="flex justify-end flex-wrap gap-1.5">${r.value}</div>`
              : `<span class="font-bold text-[#5B0F2B] text-right">${r.value}</span>`
          }
        </div>
      `
    )
    .join('');

  document.getElementById('cd-modal-sessions').innerHTML =
    schedules.length > 0
      ? `
        <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">Scheduled Sessions</span>
        <div class="flex flex-wrap gap-1.5">
          ${schedules
            .map(
              (s) => `
                <span class="px-2.5 py-1 rounded-lg bg-[#EEF4F9] border border-[#2E5D8A]/15 text-[#2E5D8A] text-[11px] font-bold tabular-code">
                  ${formatCode('SCH', s.schedule_id)}: ${s.session_date} • ${s.start_time}
                </span>
              `
            )
            .join('')}
        </div>
      `
      : '';

  const footerEl = document.getElementById('cd-modal-footer');
  if (enrolledRecord && (cls.status === 'Open' || cls.status === 'Active')) {
    footerEl.innerHTML = `
      <button type="button" onclick="dropEnrollment(${cls.class_id})" class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-600 border border-rose-200 text-rose-700 hover:text-white text-[11.5px] font-bold transition-colors cursor-pointer">
        Drop Class
      </button>
      <button type="button" onclick="closeModal('modal-class-details')" class="px-5 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-semibold transition-colors cursor-pointer">
        Close
      </button>
    `;
  } else {
    footerEl.innerHTML = `
      <button type="button" onclick="closeModal('modal-class-details')" class="ml-auto px-5 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-semibold cursor-pointer">
        Close
      </button>
    `;
  }

  openModal('modal-class-details');
}

function dropEnrollment(classId) {
  const learner = getActiveLearner();
  if (!learner) return;
  submitPostAction('actions/learner_drop_class.php', {
    csrf_token: learnerState.csrfToken,
    class_id: classId,
    learner_id: learner.learner_id,
    redirect: '../' + (window.location.pathname.split('/').pop() || 'learner_dashboard.php')
  });
}

/* =========================================================================
 * LEARNER PROFILE VIEW & EDIT MODAL (Global Scope for Inline Handlers)
 * ========================================================================= */
function populateBarangayDropdown(municipalityId, selectedBarangayId = null) {
  const brgySelect = document.getElementById('edit-barangay-id');
  if (!brgySelect) return;
  const muniId = Number(municipalityId);
  const filtered = (learnerState.barangays || []).filter(
    (b) => Number(b.municipality_id) === muniId
  );
  brgySelect.innerHTML = filtered
    .map(
      (b) =>
        `<option value="${b.barangay_id}" ${
          Number(b.barangay_id) === Number(selectedBarangayId) ? 'selected' : ''
        }>${escapeHtml(b.barangay_name)}</option>`
    )
    .join('');
}

function openProfileModal(startInEdit = false) {
  const l = getActiveLearner();
  if (!l) return;

  const lrnCode = formatCode('LRN', l.learner_id, 5);
  const accCode = formatCode('ACC', l.account_id, 3);
  const displayName = `${l.first_name} ${l.last_name}`.trim();

  document.getElementById('profile-modal-avatar').textContent = getInitials(l.first_name, l.last_name);
  document.getElementById('profile-modal-eyebrow').textContent = `LEARNER PROFILE • ${lrnCode} (${accCode})`;
  document.getElementById('profile-modal-title').textContent = displayName;

  const infoRows = [
    { label: 'Learner ID', value: lrnCode, mono: true },
    { label: 'Account ID', value: accCode, mono: true },
    { label: 'First Name', value: escapeHtml(l.first_name) },
    { label: 'Middle Name', value: escapeHtml(l.middle_name || '—') },
    { label: 'Last Name', value: escapeHtml(l.last_name) },
    { label: 'Birthdate', value: escapeHtml(l.birthdate || '—'), mono: true },
    { label: 'Contact Number', value: escapeHtml(l.contact_number || '—'), mono: true },
    { label: 'Gender', value: escapeHtml(l.gender || '—') },
    { label: 'Hearing Status', value: escapeHtml(l.hearing_status) },
    { label: 'Speech Status', value: escapeHtml(l.speech_status) },
    { label: 'City / Municipality', value: escapeHtml(l.municipality_name || '—') },
    { label: 'Barangay', value: escapeHtml(l.barangay_name || '—') },
    { label: 'Street / Purok Address', value: escapeHtml(l.street_address || '—') },
    { label: 'Accessibility Notes', value: escapeHtml(l.accessibility_notes || 'None specified') }
  ];

  document.getElementById('profile-view-container').innerHTML = infoRows
    .map(
      (r) => `
        <div class="flex items-start justify-between gap-3 px-3.5 py-2 rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] text-[12px]">
          <span class="font-semibold text-[#6D4C57] shrink-0">${r.label}</span>
          <span class="font-bold text-[#5B0F2B] text-right ${r.mono ? 'tabular-code' : ''}">${r.value}</span>
        </div>
      `
    )
    .join('');

  toggleProfileEditMode(Boolean(startInEdit));
  openModal('modal-profile');
}

function toggleProfileEditMode(isEdit) {
  const viewContainer = document.getElementById('profile-view-container');
  const viewFooter = document.getElementById('profile-view-footer');
  const editForm = document.getElementById('profile-edit-form');
  if (!viewContainer || !editForm) return;

  if (isEdit) {
    const l = getActiveLearner();
    if (!l) return;

    document.getElementById('edit-learner-id-hidden').value = l.learner_id;
    document.getElementById('edit-learner-id').value = formatCode('LRN', l.learner_id, 5);
    document.getElementById('edit-account-id').value = formatCode('ACC', l.account_id, 3);
    document.getElementById('edit-first-name').value = l.first_name || '';
    document.getElementById('edit-middle-name').value = l.middle_name || '';
    document.getElementById('edit-last-name').value = l.last_name || '';
    document.getElementById('edit-birthdate').value = l.birthdate || '';
    document.getElementById('edit-contact-number').value = l.contact_number || '';
    document.getElementById('edit-gender').value = l.gender || 'Prefer not to say';
    document.getElementById('edit-hearing-status').value = l.hearing_status || 'Hearing';
    document.getElementById('edit-speech-status').value = l.speech_status || 'Verbal';
    document.getElementById('edit-street-address').value = l.street_address || '';
    document.getElementById('edit-accessibility-notes').value = l.accessibility_notes || '';

    const muniSelect = document.getElementById('edit-municipality-id');
    if (muniSelect) {
      muniSelect.innerHTML = (learnerState.municipalities || [])
        .map(
          (m) =>
            `<option value="${m.municipality_id}" ${
              Number(m.municipality_id) === Number(l.municipality_id) ? 'selected' : ''
            }>${escapeHtml(m.municipality_name)}</option>`
        )
        .join('');
      populateBarangayDropdown(l.municipality_id, l.barangay_id);
    }

    viewContainer.classList.add('hidden');
    if (viewFooter) viewFooter.classList.add('hidden');
    editForm.classList.remove('hidden');
    editForm.classList.add('flex');
  } else {
    editForm.classList.add('hidden');
    editForm.classList.remove('flex');
    viewContainer.classList.remove('hidden');
    if (viewFooter) viewFooter.classList.remove('hidden');
  }
}

/* =========================================================================
 * MY GRADES RENDERER (learner_grades.php)
 * ========================================================================= */
function clearFocusedEnrollment() {
  const url = new URL(window.location.href);
  url.searchParams.delete('enrollment_id');
  window.history.replaceState({}, '', url.toString());
  renderGradesView();
}

function renderGradesView() {
  const container = document.getElementById('grades-list-container');
  if (!container) return;

  const learner = getActiveLearner();
  if (!learner) return;

  const params = new URLSearchParams(window.location.search);
  const focusedEnrollmentId = Number(params.get('enrollment_id')) || 0;

  const resetFocusBtn = document.getElementById('grades-reset-focus-btn');
  if (resetFocusBtn) {
    resetFocusBtn.classList.toggle('hidden', focusedEnrollmentId <= 0);
  }

  const searchVal = (document.getElementById('grades-search-input')?.value || '').trim().toLowerCase();
  const ratingVal = document.getElementById('grades-rating-filter')?.value || 'ALL';

  const myEnrollments = learnerState.enrollments.filter(
    (e) =>
      Number(e.learner_id) === Number(learner.learner_id) &&
      ['enrolled', 'completed'].includes(String(e.status || '').toLowerCase())
  );

  // KPI Metrics across all active/completed enrollments
  const kpiGrid = document.getElementById('grades-kpi-grid');
  if (kpiGrid) {
    const totalCourses = myEnrollments.length;
    const satisfactoryCount = myEnrollments.filter((e) => getEnrollmentRating(e) === 'Satisfactory').length;
    const pendingCount = myEnrollments.filter((e) => !getEnrollmentRating(e)).length;
    const myEnrollmentIds = new Set(myEnrollments.map((e) => Number(e.enrollment_id)));
    const sessionEvalCount = learnerState.sessionGrades.filter(
      (sg) => myEnrollmentIds.has(Number(sg.enrollment_id)) && Boolean(sg.session_rating)
    ).length;

    const kpis = [
      {
        label: 'SATISFACTORY STANDING',
        badge: 'OVERALL',
        badgeClass: 'bg-emerald-50 text-emerald-700',
        value: `${satisfactoryCount}/${totalCourses}`,
        sub: 'Courses rated Satisfactory'
      },
      {
        label: 'SESSION EVALUATIONS',
        badge: 'RECORDED',
        badgeClass: 'bg-[#EEF4F9] text-[#2E5D8A]',
        value: sessionEvalCount,
        sub: 'Individual session assessments'
      },
      {
        label: 'AWAITING RATING',
        badge: 'IN PROGRESS',
        badgeClass: 'bg-[#5B0F2B]/[0.09] text-[#5B0F2B]',
        value: pendingCount,
        sub: 'Courses pending final evaluation'
      }
    ];

    kpiGrid.innerHTML = kpis
      .map(
        (k) => `
          <div class="card-surface rounded-2xl px-5 py-4 flex flex-col gap-1.5">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.09em] text-[#6D4C57]">${k.label}</span>
              <span class="px-2 py-0.5 rounded-full text-[9px] font-bold ${k.badgeClass}">${k.badge}</span>
            </div>
            <p class="font-display font-bold text-[30px] leading-none text-[#5B0F2B] tabular-code">${k.value}</p>
            <span class="text-[11px] font-medium text-[#6D4C57]">${k.sub}</span>
          </div>
        `
      )
      .join('');
  }

  // Filter enrollments
  let filtered = myEnrollments.filter((enr) => {
    if (focusedEnrollmentId > 0 && Number(enr.enrollment_id) !== focusedEnrollmentId) {
      return false;
    }

    const rating = getEnrollmentRating(enr);
    if (ratingVal === 'PENDING' && rating !== '') return false;
    if (ratingVal !== 'ALL' && ratingVal !== 'PENDING' && rating !== ratingVal) return false;

    const cls = getClassById(enr.class_id);
    if (searchVal) {
      const clsCode = cls ? formatCode('CLS', cls.class_id).toLowerCase() : '';
      const sessionRemarks = learnerState.sessionGrades
        .filter((sg) => Number(sg.enrollment_id) === Number(enr.enrollment_id))
        .map((sg) => sg.remarks || '')
        .join(' ');
      const overallNotes = getEnrollmentNotes(enr);
      const hay = `${clsCode} ${cls?.class_title || ''} ${cls?.instructor || ''} ${overallNotes} ${sessionRemarks}`.toLowerCase();
      if (!hay.includes(searchVal)) return false;
    }
    return true;
  });

  const summaryBadge = document.getElementById('grades-summary-badge');
  if (summaryBadge) {
    summaryBadge.textContent = `${filtered.length} Course${filtered.length === 1 ? '' : 's'}`;
  }

  if (filtered.length === 0) {
    container.innerHTML = `
      <div class="card-surface rounded-2xl p-8 text-center flex flex-col items-center gap-3">
        <p class="font-display font-bold text-[21px] text-[#5B0F2B]">No grade records match this filter</p>
        <p class="text-[12px] text-[#6D4C57] max-w-md">
          ${
            myEnrollments.length === 0
              ? 'Enroll in a Filipino Sign Language class to track your performance and teacher evaluations.'
              : 'Clear your search query or select All Ratings to view all course evaluations.'
          }
        </p>
        ${
          myEnrollments.length === 0
            ? `<a href="learner_browse.php" class="px-4 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-bold transition-colors no-underline">Browse Open Classes</a>`
            : ''
        }
      </div>
    `;
    return;
  }

  container.innerHTML = filtered
    .map((enr) => {
      const cls = getClassById(enr.class_id);
      const clsCode = formatCode('CLS', enr.class_id);
      const enrCode = formatCode('ENR', enr.enrollment_id, 4);
      const isFocused = focusedEnrollmentId > 0 && Number(enr.enrollment_id) === focusedEnrollmentId;

      const sessionRows = learnerState.sessionGrades.filter(
        (sg) => Number(sg.enrollment_id) === Number(enr.enrollment_id)
      );

      const sessionTableHtml =
        sessionRows.length > 0
          ? `
            <div class="overflow-x-auto rounded-xl border border-[#5B0F2B]/[0.08]">
              <table class="w-full text-left border-collapse text-[11.5px]">
                <thead>
                  <tr class="bg-[#F8F6F7] border-b border-[#5B0F2B]/[0.08] text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">
                    <th class="py-2.5 px-3">Session</th>
                    <th class="py-2.5 px-3">Date &amp; Time</th>
                    <th class="py-2.5 px-3">Evaluation</th>
                    <th class="py-2.5 px-3">Teacher Remarks</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-[#5B0F2B]/[0.06] bg-white">
                  ${sessionRows
                    .map(
                      (sg) => `
                        <tr class="hover:bg-[#F8F6F7]/70 transition-colors">
                          <td class="py-2.5 px-3 font-bold text-[#2E5D8A] tabular-code whitespace-nowrap">
                            ${formatCode('SCH', sg.schedule_id)}
                          </td>
                          <td class="py-2.5 px-3 text-[#2D141E] font-medium tabular-code whitespace-nowrap">
                            ${escapeHtml(sg.session_date || '—')} • ${escapeHtml(sg.start_time || '')}
                          </td>
                          <td class="py-2.5 px-3 whitespace-nowrap">
                            ${getGradePillHtml(sg.session_rating, 'sm')}
                          </td>
                          <td class="py-2.5 px-3 text-[#6D4C57]">
                            ${escapeHtml(sg.remarks || 'No additional remarks.')}
                          </td>
                        </tr>
                      `
                    )
                    .join('')}
                </tbody>
              </table>
            </div>
          `
          : `
            <div class="rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] px-3.5 py-3 text-[11.5px] text-[#6D4C57]">
              No individual session evaluations recorded yet for this course.
            </div>
          `;

      return `
        <article class="card-surface rounded-2xl p-5 flex flex-col gap-4 ${
          isFocused ? 'ring-2 ring-[#8D2645]/40' : ''
        }">
          <!-- Course Header -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3.5 border-b border-[#5B0F2B]/[0.08]">
            <div class="flex items-start gap-3 min-w-0">
              <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-[#5B0F2B]/10">
                ${getClassArtworkSvg(enr.class_id)}
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-[10px] font-bold text-[#8D2645] tabular-code">${clsCode} • ${enrCode}</span>
                  ${cls ? getStatusBadgeHtml(cls.status) : ''}
                </div>
                <h2 class="font-display font-bold text-[20px] leading-snug text-[#5B0F2B] mt-0.5">
                  ${cls ? escapeHtml(cls.class_title) : clsCode}
                </h2>
                <p class="text-[11px] text-[#6D4C57] mt-0.5 truncate">
                  ${cls ? escapeHtml(cls.venue || 'TBA') : ''}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
              ${getGradePillHtml(getEnrollmentRating(enr), 'md')}
              <button
                type="button"
                onclick="openClassDetailsModal(${enr.class_id})"
                class="px-3 py-1.5 rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/12 text-[#5B0F2B] text-[11px] font-bold transition-colors cursor-pointer"
              >
                Class Info
              </button>
            </div>
          </div>

          <!-- Instructors & Overall Feedback -->
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
            <div class="lg:col-span-4 flex flex-col gap-1.5">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">Evaluating Teachers</span>
              ${renderInstructorsPills(cls?.instructor)}
            </div>
            <div class="lg:col-span-8 rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] px-3.5 py-2.5 flex flex-col gap-1">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">Overall Instructor Notes</span>
              <p class="text-[12px] text-[#2D141E] leading-relaxed">
                ${escapeHtml(getEnrollmentNotes(enr) || 'No overall course remarks posted by the instructor yet.')}
              </p>
            </div>
          </div>

          <!-- Per-Session Evaluation Breakdown -->
          <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">
                Session-by-Session Evaluations (${sessionRows.length})
              </span>
            </div>
            ${sessionTableHtml}
          </div>
        </article>
      `;
    })
    .join('');
}

/* =========================================================================
 * INITIALIZATION
 * ========================================================================= */
document.addEventListener('DOMContentLoaded', () => {
  renderSidebarAndHeader();

  if (document.getElementById('my-classes-grid')) {
    renderClassesView();
  }
  if (document.getElementById('dashboard-enrolled-list') || document.getElementById('dashboard-calendar-container')) {
    renderDashboardView();
  }
  if (document.getElementById('browse-classes-grid')) {
    renderBrowseView();
  }
  if (document.getElementById('grades-list-container')) {
    renderGradesView();
  }

  const params = new URLSearchParams(window.location.search);
  const toastType = params.get('toast');
  const errorMsg = params.get('error');
  if (toastType === 'enrolled') showToast('Successfully registered for FSL class!');
  if (toastType === 'dropped') showToast('Class enrollment dropped.');
  if (toastType === 'profile_updated') showToast('Learner profile saved to database.');
  if (errorMsg) showToast(errorMsg);

  /* =========================================================================
 * MY CLASSES RENDERER (learner_classes.php)
 * ========================================================================= */
function renderClassesView() {
  const grid = document.getElementById('my-classes-grid');
  if (!grid) return;

  const learner = getActiveLearner();
  if (!learner) return;

  const searchVal = (document.getElementById('my-classes-search')?.value || '').trim().toLowerCase();
  const filterVal = document.getElementById('my-classes-filter')?.value || 'ALL';

  const myEnrollments = learnerState.enrollments.filter(
    (e) =>
      Number(e.learner_id) === Number(learner.learner_id) &&
      (e.status === 'enrolled' || e.status === 'completed')
  );

  const filtered = myEnrollments.filter((enr) => {
    const cls = getClassById(enr.class_id);
    if (!cls) return false;

    const isCompleted = enr.status === 'completed' || cls.status === 'Completed';
    if (filterVal === 'ACTIVE' && isCompleted) return false;
    if (filterVal === 'COMPLETED' && !isCompleted) return false;

    if (searchVal) {
      const clsCode = formatCode('CLS', cls.class_id).toLowerCase();
      const hay = `${clsCode} ${cls.class_title} ${cls.description || ''} ${cls.instructor || ''} ${cls.venue || ''}`.toLowerCase();
      if (!hay.includes(searchVal)) return false;
    }
    return true;
  });

  const countBadge = document.getElementById('my-classes-count');
  if (countBadge) {
    countBadge.textContent = `${filtered.length} Enrolled Class${filtered.length === 1 ? '' : 'es'}`;
  }

  if (filtered.length === 0) {
    grid.innerHTML = `
      <div class="col-span-full card-surface rounded-2xl p-8 text-center flex flex-col items-center gap-3">
        <p class="font-display font-bold text-[21px] text-[#5B0F2B]">No enrolled classes match this view</p>
        <p class="text-[12px] text-[#6D4C57] max-w-md">
          Explore available Filipino Sign Language classes across Albay Province to register.
        </p>
        <a href="learner_browse.php" class="px-4 py-2 rounded-xl bg-[#5B0F2B] hover:bg-[#8D2645] text-white text-[12px] font-bold transition-colors no-underline">
          Browse Open Classes
        </a>
      </div>
    `;
    return;
  }

  grid.innerHTML = filtered
    .map((enr) => {
      const cls = getClassById(enr.class_id);
      const clsCode = formatCode('CLS', cls.class_id);
      const enrCode = formatCode('ENR', enr.enrollment_id, 4);
      const schedules = getSchedulesForClass(cls.class_id);
      const canDrop = enr.status === 'enrolled' && (cls.status === 'Open' || cls.status === 'Active' || cls.status === 'Ongoing');

      const sessionChipsHtml =
        schedules.length > 0
          ? schedules
              .map(
                (s) => `
                  <span class="px-2.5 py-1 rounded-lg bg-[#EEF4F9] border border-[#2E5D8A]/15 text-[#2E5D8A] text-[10.5px] font-bold tabular-code">
                    ${formatCode('SCH', s.schedule_id)}: ${s.session_date} • ${s.start_time}
                  </span>
                `
              )
              .join('')
          : `<span class="text-[11px] text-[#6D4C57]">No sessions scheduled yet.</span>`;

      return `
        <article class="card-surface rounded-2xl p-5 flex flex-col justify-between gap-4 min-w-0">
          <div class="flex flex-col gap-3">
            <div class="flex items-start gap-3">
              <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border border-[#5B0F2B]/10">
                ${getClassArtworkSvg(cls.class_id)}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="text-[10px] font-bold text-[#8D2645] tabular-code">${clsCode} • ${enrCode}</span>
                  <div class="flex items-center gap-1.5">
                    ${getGradePillHtml(getEnrollmentRating(enr), 'sm')}
                    ${getStatusBadgeHtml(cls.status)}
                  </div>
                </div>
                <h2 class="font-display font-bold text-[19.5px] leading-snug text-[#5B0F2B] mt-1">
                  ${escapeHtml(cls.class_title)}
                </h2>
              </div>
            </div>

            <p class="text-[12px] text-[#6D4C57] leading-relaxed">
              ${escapeHtml(cls.description || 'Filipino Sign Language course in Albay Province.')}
            </p>

            <div class="rounded-xl bg-[#F8F6F7] border border-[#5B0F2B]/[0.06] p-3 flex flex-col gap-2 text-[11.5px]">
              <div class="flex items-center justify-between gap-2">
                <span class="font-semibold text-[#6D4C57]">Venue</span>
                <span class="font-bold text-[#5B0F2B] text-right">${escapeHtml(cls.venue || 'TBA')}</span>
              </div>
              <div class="flex items-center justify-between gap-2">
                <span class="font-semibold text-[#6D4C57]">Enrolled Date</span>
                <span class="font-bold text-[#2E5D8A] tabular-code">${escapeHtml(enr.enrolled_date || '—')}</span>
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">Assigned Teachers</span>
              ${renderInstructorsPills(cls.instructor)}
            </div>

            <div class="flex flex-col gap-1.5">
              <span class="text-[9.5px] font-bold uppercase tracking-[0.08em] text-[#6D4C57]">Session Timetable (${schedules.length})</span>
              <div class="flex flex-wrap gap-1.5">${sessionChipsHtml}</div>
            </div>
          </div>

          <div class="pt-3 border-t border-[#5B0F2B]/[0.08] flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <button
                type="button"
                onclick="openClassDetailsModal(${cls.class_id})"
                class="px-3.5 py-2 rounded-xl bg-[#F8F6F7] hover:bg-[#F7ECEF] border border-[#5B0F2B]/12 text-[#5B0F2B] text-[11.5px] font-bold transition-colors cursor-pointer"
              >
                View Details
              </button>
              <a
                href="learner_grades.php?enrollment_id=${enr.enrollment_id}"
                class="px-3.5 py-2 rounded-xl bg-[#EEF4F9] hover:bg-[#2E5D8A] border border-[#2E5D8A]/15 text-[#2E5D8A] hover:text-white text-[11.5px] font-bold transition-colors no-underline"
              >
                Grades &amp; Feedback
              </a>
            </div>
            ${
              canDrop
                ? `<button type="button" onclick="dropEnrollment(${cls.class_id})" class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-600 border border-rose-200 text-rose-700 hover:text-white text-[11.5px] font-bold transition-colors cursor-pointer">Drop Class</button>`
                : ''
            }
          </div>
        </article>
      `;
    })
    .join('');
}
});

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('addLearnerProfileModal');
  const openBtn = document.getElementById('openNewLearnerProfileBtn');
  const closeBtn = document.getElementById('closeAlpModalBtn');
  const cancelBtn = document.getElementById('cancelAlpModalBtn');
  const firstInput = document.getElementById('alpFirstName');
  const lastInput = document.getElementById('alpLastName');
  const initialsBadge = document.getElementById('alpInitialsPreview');
  const muniSelect = document.getElementById('alpMunicipality');
  const brgySelect = document.getElementById('alpBarangay');

  function toggleAlpModal(show) {
    if (!modal) return;
    modal.classList.toggle('hidden', !show);
    modal.setAttribute('aria-hidden', String(!show));
  }

  openBtn?.addEventListener('click', () => toggleAlpModal(true));
  closeBtn?.addEventListener('click', () => toggleAlpModal(false));
  cancelBtn?.addEventListener('click', () => toggleAlpModal(false));
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) toggleAlpModal(false);
  });

  function updateInitials() {
    if (!initialsBadge) return;
    const f = (firstInput?.value.trim() || '')[0] || '';
    const l = (lastInput?.value.trim() || '')[0] || '';
    initialsBadge.textContent = (f + l).toUpperCase() || '+';
  }
  firstInput?.addEventListener('input', updateInitials);
  lastInput?.addEventListener('input', updateInitials);

  const allBarangays =
    (typeof learnerState !== 'undefined' && Array.isArray(learnerState.barangays))
      ? learnerState.barangays
      : [];

  if (muniSelect && brgySelect) {
    if (muniSelect.options.length <= 1 && typeof learnerState !== 'undefined' && Array.isArray(learnerState.municipalities)) {
      learnerState.municipalities.forEach((m) => {
        const opt = document.createElement('option');
        opt.value = m.municipality_id;
        opt.textContent = m.municipality_name;
        muniSelect.appendChild(opt);
      });
    }

    muniSelect.addEventListener('change', () => {
      const chosenMuniId = Number(muniSelect.value);
      if (!chosenMuniId) {
        brgySelect.innerHTML = '<option value="">Select Municipality First</option>';
        brgySelect.disabled = true;
        return;
      }

      const matches = allBarangays.filter((b) => Number(b.municipality_id) === chosenMuniId);
      brgySelect.innerHTML = '<option value="">Select Barangay...</option>';
      matches.forEach((b) => {
        const opt = document.createElement('option');
        opt.value = b.barangay_id;
        opt.textContent = b.barangay_name;
        brgySelect.appendChild(opt);
      });
      brgySelect.disabled = matches.length === 0;
    });
  }
});