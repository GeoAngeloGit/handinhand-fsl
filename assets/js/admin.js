document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Calendar rendering (Defaults to November 2026, Today = Nov 21)
    const calGrid = document.getElementById('calDaysGrid');
    const calMonthLabel = document.getElementById('calMonthLabel');
    const calTodayBtn = document.getElementById('calTodayBtn');
    const statusDateEl = document.getElementById('calStatusDate');
    const statusTitleEl = document.getElementById('calStatusTitle');
    const statusSubEl = document.getElementById('calStatusSub');

    const TODAY_YEAR = 2026;
    const TODAY_MONTH = 10; // 0-indexed: 10 = November
    const TODAY_DAY = 21;

    let selectedDay = TODAY_DAY;

    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    const shortMonths = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    function formatIso(year, monthIndex, day) {
        const m = String(monthIndex + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${year}-${m}-${d}`;
    }

    function updateStatusBox(day) {
        if (!statusDateEl || !statusTitleEl || !statusSubEl) return;
        const iso = formatIso(TODAY_YEAR, TODAY_MONTH, day);
        const isToday = (day === TODAY_DAY);
        statusDateEl.textContent = isToday
            ? `${shortMonths[TODAY_MONTH]} ${day} • TODAY`
            : `${shortMonths[TODAY_MONTH]} ${day}, ${TODAY_YEAR}`;

        if (typeof calendarEvents !== 'undefined' && calendarEvents[iso]) {
            statusTitleEl.textContent = calendarEvents[iso].title;
            statusSubEl.textContent = calendarEvents[iso].sub;
        } else {
            statusTitleEl.textContent = isToday ? 'No classes scheduled today' : 'No classes scheduled';
            statusSubEl.textContent = 'Next: Wednesday, Nov 25 at Rawis Hub';
        }
    }

    function renderCalendar() {
        if (!calGrid) return;
        calGrid.innerHTML = '';
        if (calMonthLabel) {
            calMonthLabel.textContent = `${monthNames[TODAY_MONTH]} ${TODAY_YEAR}`;
        }

        const firstWeekday = new Date(TODAY_YEAR, TODAY_MONTH, 1).getDay();
        const totalDays = new Date(TODAY_YEAR, TODAY_MONTH + 1, 0).getDate();

        for (let i = 0; i < firstWeekday; i++) {
            const blank = document.createElement('span');
            blank.className = 'cal-day empty';
            calGrid.appendChild(blank);
        }

        for (let day = 1; day <= totalDays; day++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cal-day';
            btn.textContent = day;

            const iso = formatIso(TODAY_YEAR, TODAY_MONTH, day);
            if (typeof calendarEvents !== 'undefined' && calendarEvents[iso]) {
                btn.classList.add('has-event');
            }
            if (day === selectedDay) {
                btn.classList.add('selected');
            }

            btn.addEventListener('click', () => {
                selectedDay = day;
                renderCalendar();
                updateStatusBox(day);
            });

            calGrid.appendChild(btn);
        }
    }

    if (calTodayBtn) {
        calTodayBtn.addEventListener('click', () => {
            selectedDay = TODAY_DAY;
            renderCalendar();
            updateStatusBox(TODAY_DAY);
        });
    }

    renderCalendar();
    updateStatusBox(TODAY_DAY);
});