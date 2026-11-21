document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Collapsible filter card toggle
    const filterCard = document.getElementById('filterPanelCard');
    const filterToggleBtn = document.getElementById('filterToggleBtn');
    const hideFiltersBtn = document.getElementById('hideFiltersBtn');
    const filterChevron = document.getElementById('filterChevron');

    function setFilterVisibility(visible) {
        if (!filterCard) return;
        filterCard.classList.toggle('collapsed', !visible);
        if (filterToggleBtn) filterToggleBtn.setAttribute('aria-expanded', String(visible));
        if (filterChevron) filterChevron.innerHTML = visible ? '&#9662;' : '&#9656;';
    }

    if (filterToggleBtn) {
        filterToggleBtn.addEventListener('click', () => {
            const isCollapsed = filterCard.classList.contains('collapsed');
            setFilterVisibility(isCollapsed);
        });
    }

    if (hideFiltersBtn) {
        hideFiltersBtn.addEventListener('click', () => setFilterVisibility(false));
    }

    // Filtering controls
    const roleSelect = document.getElementById('roleFilterSelect');
    const searchInput = document.getElementById('accountSearchInput');
    const dateInput = document.getElementById('createdDateInput');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const qdatePills = document.querySelectorAll('.qdate-pill');
    const rowsStack = document.getElementById('registryRowsStack');
    const emptyState = document.getElementById('emptyFilterState');
    const counterEl = document.getElementById('accountsCounter');
    const tableSection = document.querySelector('.registry-table-section');
    const totalAccounts = tableSection ? parseInt(tableSection.dataset.totalCount || '0', 10) : 0;

    let activeQuickDate = 'ALL';

    function applyFilters() {
        if (!rowsStack) return;
        const selectedRole = roleSelect ? roleSelect.value : 'ALL';
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const exactDate = dateInput ? dateInput.value : '';

        const cards = Array.from(rowsStack.querySelectorAll('.account-row-card'));
        let visibleCount = 0;

        cards.forEach(card => {
            const role = card.dataset.role || '';
            const account = card.dataset.account || '';
            const username = card.dataset.username || '';
            const email = card.dataset.email || '';
            const date = card.dataset.date || '';

            const matchRole = (selectedRole === 'ALL') || (role === selectedRole);
            const matchQuery = !query || account.includes(query) || username.includes(query) || email.includes(query);
            const matchExactDate = !exactDate || (date === exactDate);
            const matchQuickDate = (activeQuickDate === 'ALL') || date.startsWith(activeQuickDate);

            const isVisible = matchRole && matchQuery && matchExactDate && matchQuickDate;
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
        if (counterEl) {
            const denominator = Math.max(totalAccounts, cards.length);
            const numerator = (selectedRole === 'ALL' && !query && !exactDate && activeQuickDate === 'ALL')
                ? denominator
                : visibleCount;
            counterEl.innerHTML = `<strong>${numerator}</strong> of ${denominator} accounts`;
        }
    }

    if (roleSelect) roleSelect.addEventListener('change', applyFilters);
    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (dateInput) {
        dateInput.addEventListener('change', () => {
            activeQuickDate = 'ALL';
            qdatePills.forEach(p => p.classList.toggle('active', p.dataset.qdate === 'ALL'));
            applyFilters();
        });
    }

    qdatePills.forEach(pill => {
        pill.addEventListener('click', () => {
            activeQuickDate = pill.dataset.qdate || 'ALL';
            qdatePills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            if (dateInput) dateInput.value = '';
            applyFilters();
        });
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (roleSelect) roleSelect.value = 'ALL';
            if (searchInput) searchInput.value = '';
            if (dateInput) dateInput.value = '';
            activeQuickDate = 'ALL';
            qdatePills.forEach(p => p.classList.toggle('active', p.dataset.qdate === 'ALL'));
            applyFilters();
        });
    }

    // Column sorting
    let sortAsc = true;
    document.querySelectorAll('.sort-col-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!rowsStack) return;
            const key = btn.dataset.sort;
            const cards = Array.from(rowsStack.querySelectorAll('.account-row-card'));
            sortAsc = !sortAsc;

            cards.sort((a, b) => {
                const valA = (a.dataset[key] || '').toLowerCase();
                const valB = (b.dataset[key] || '').toLowerCase();
                return sortAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
            });

            cards.forEach(card => rowsStack.appendChild(card));
        });
    });

    // Click-to-copy Account ID
    document.querySelectorAll('.copy-id-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.dataset.copy;
            if (!text || !navigator.clipboard) return;
            navigator.clipboard.writeText(text);
            btn.style.color = '#2E5D8A';
            setTimeout(() => { btn.style.color = ''; }, 900);
        });
    });
});