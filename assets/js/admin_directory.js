document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Tab switching (Learners / Teachers / Admins)
    const tabBtns = document.querySelectorAll('.dir-tab-btn');
    const tabPanels = document.querySelectorAll('.dir-tab-panel');
    const sectionTitleEl = document.getElementById('dirSectionTitle');
    const activeSummaryEl = document.getElementById('dirActiveSummary');
    const sortSelect = document.getElementById('dirSortSelect');
    const newAdminBtn = document.getElementById('openNewAdminModalBtn');
    const searchInput = document.getElementById('dirSearchInput');
    const statusPillBtns = document.querySelectorAll('.status-pill-btn');
    const emptyStateEl = document.getElementById('dirEmptyState');

    let activeTab = 'learners';
    let activeStatus = 'ALL';

    function updateSortLabels(idLabel) {
        if (!sortSelect) return;
        sortSelect.options[0].textContent = `${idLabel} (Ascending)`;
        sortSelect.options[1].textContent = `${idLabel} (Descending)`;
    }

    function applyDirectoryFiltersAndSort() {
        const panel = document.getElementById(`panel-${activeTab}`);
        if (!panel) return;
        const stack = panel.querySelector('.dir-rows-stack');
        if (!stack) return;

        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const sortMode = sortSelect ? sortSelect.value : 'id-asc';
        const cards = Array.from(stack.querySelectorAll('.dir-row-card'));

        // Sort cards
        cards.sort((a, b) => {
            if (sortMode === 'id-asc') return (a.dataset.code || '').localeCompare(b.dataset.code || '');
            if (sortMode === 'id-desc') return (b.dataset.code || '').localeCompare(a.dataset.code || '');
            if (sortMode === 'name-asc') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            if (sortMode === 'name-desc') return (b.dataset.name || '').localeCompare(a.dataset.name || '');
            return 0;
        });
        cards.forEach(c => stack.appendChild(c));

        // Filter cards
        let visibleCount = 0;
        cards.forEach(card => {
            const code = card.dataset.code || '';
            const name = card.dataset.name || '';
            const email = card.dataset.email || '';
            const status = card.dataset.status || '';

            const matchStatus = (activeStatus === 'ALL') || (status === activeStatus);
            const matchQuery = !query || code.includes(query) || name.includes(query) || email.includes(query);

            const show = matchStatus && matchQuery;
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (emptyStateEl) {
            emptyStateEl.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeTab = btn.dataset.tab || 'learners';

            tabBtns.forEach(b => {
                const isSelected = b === btn;
                b.classList.toggle('active', isSelected);
                b.setAttribute('aria-selected', String(isSelected));
            });

            tabPanels.forEach(panel => {
                const isTarget = panel.id === `panel-${activeTab}`;
                panel.style.display = isTarget ? '' : 'none';
                if (isTarget) {
                    if (sectionTitleEl) sectionTitleEl.textContent = panel.dataset.title || '';
                    if (activeSummaryEl) activeSummaryEl.textContent = panel.dataset.summary || '';
                    updateSortLabels(panel.dataset.idLabel || 'Learner ID');
                }
            });

            // Show "+ New Admin Account" button only on Admins tab
            if (newAdminBtn) {
                newAdminBtn.style.display = (activeTab === 'admins') ? 'inline-flex' : 'none';
            }

            applyDirectoryFiltersAndSort();
        });
    });

    if (searchInput) searchInput.addEventListener('input', applyDirectoryFiltersAndSort);
    if (sortSelect) sortSelect.addEventListener('change', applyDirectoryFiltersAndSort);

    statusPillBtns.forEach(pill => {
        pill.addEventListener('click', () => {
            activeStatus = pill.dataset.status || 'ALL';
            statusPillBtns.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            applyDirectoryFiltersAndSort();
        });
    });

    // ==================== PROFILE DETAILS MODAL ====================
    const profileModal = document.getElementById('profileDetailsModal');
    const closeProfileBtn = document.getElementById('closeProfileModalBtn');
    const cancelProfileBtn = document.getElementById('modalCancelBtn');
    const modalInitials = document.getElementById('modalInitials');
    const modalCodeLine = document.getElementById('modalCodeLine');
    const modalStatusBadge = document.getElementById('modalStatusBadge');
    const modalProfileName = document.getElementById('modalProfileName');
    const modalFieldsStack = document.getElementById('modalFieldsStack');
    const modalDeleteBtn = document.getElementById('modalDeleteUserBtn');
    const modalEditSaveBtn = document.getElementById('modalEditSaveBtn');
    const modalEditBtnLabel = document.getElementById('modalEditBtnLabel');

    let isEditMode = false;
    let currentCardRef = null;
    let currentDataRef = null;

    function renderModalFields(data, editable) {
        if (!modalFieldsStack) return;
        modalFieldsStack.innerHTML = '';

        data.fields.forEach((field, idx) => {
            const row = document.createElement('div');
            row.className = 'modal-field-row';

            const labelEl = document.createElement('span');
            labelEl.className = 'modal-field-label';
            labelEl.textContent = field.label;

            row.appendChild(labelEl);

            // Keep ID and Class counts read-only even in edit mode
            const readOnlyField = idx === 0 || field.label.includes('Classes');
            if (editable && !readOnlyField) {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'modal-field-input';
                input.value = field.value;
                input.dataset.fieldIndex = String(idx);
                row.appendChild(input);
            } else {
                const valEl = document.createElement('span');
                valEl.className = 'modal-field-value';
                valEl.textContent = field.value;
                row.appendChild(valEl);
            }

            modalFieldsStack.appendChild(row);
        });
    }

    function openProfileModal(card) {
        if (!profileModal) return;
        const rawJson = card.dataset.modal;
        if (!rawJson) return;

        currentCardRef = card;
        currentDataRef = JSON.parse(rawJson);
        isEditMode = false;

        if (modalInitials) modalInitials.textContent = currentDataRef.initials || 'US';
        if (modalCodeLine) modalCodeLine.textContent = `${currentDataRef.code} • ACCOUNT ${currentDataRef.account_code}`;
        if (modalProfileName) modalProfileName.textContent = currentDataRef.full_name;

        const isActive = currentDataRef.status === 'Active';
        if (modalStatusBadge) {
            modalStatusBadge.className = `dir-status-badge ${isActive ? 'status-active' : 'status-inactive'}`;
            modalStatusBadge.innerHTML = `&bull; ${currentDataRef.status}`;
        }

        // Show "Delete User" button ONLY on Inactive profiles (matching Figma)
        if (modalDeleteBtn) {
            modalDeleteBtn.style.display = isActive ? 'none' : 'inline-flex';
        }

        if (modalEditBtnLabel) modalEditBtnLabel.textContent = 'Edit Profile';
        renderModalFields(currentDataRef, false);

        profileModal.classList.add('open');
        profileModal.setAttribute('aria-hidden', 'false');
    }

    function closeProfileModal() {
        if (!profileModal) return;
        profileModal.classList.remove('open');
        profileModal.setAttribute('aria-hidden', 'true');
        isEditMode = false;
    }

    document.querySelectorAll('.dir-row-card').forEach(card => {
        card.addEventListener('click', () => openProfileModal(card));
    });

    if (closeProfileBtn) closeProfileBtn.addEventListener('click', closeProfileModal);
    if (cancelProfileBtn) cancelProfileBtn.addEventListener('click', closeProfileModal);
    if (profileModal) {
        profileModal.addEventListener('click', (e) => {
            if (e.target === profileModal) closeProfileModal();
        });
    }

    // Toggle Edit Profile <-> Save Changes
    if (modalEditSaveBtn) {
        modalEditSaveBtn.addEventListener('click', () => {
            if (!currentDataRef) return;
            if (!isEditMode) {
                isEditMode = true;
                if (modalEditBtnLabel) modalEditBtnLabel.textContent = 'Save Changes';
                renderModalFields(currentDataRef, true);
            } else {
                const inputs = modalFieldsStack.querySelectorAll('.modal-field-input');
                inputs.forEach(inp => {
                    const idx = parseInt(inp.dataset.fieldIndex || '-1', 10);
                    if (idx >= 0 && currentDataRef.fields[idx]) {
                        currentDataRef.fields[idx].value = inp.value.trim() || currentDataRef.fields[idx].value;
                        if (currentDataRef.fields[idx].label === 'Full Name') {
                            currentDataRef.full_name = currentDataRef.fields[idx].value;
                            if (modalProfileName) modalProfileName.textContent = currentDataRef.full_name;
                        }
                    }
                });
                if (currentCardRef) {
                    currentCardRef.dataset.modal = JSON.stringify(currentDataRef);
                }
                isEditMode = false;
                if (modalEditBtnLabel) modalEditBtnLabel.textContent = 'Edit Profile';
                renderModalFields(currentDataRef, false);
            }
        });
    }

    // Delete User (Inactive profiles)
    if (modalDeleteBtn) {
        modalDeleteBtn.addEventListener('click', () => {
            if (!currentDataRef || !currentCardRef) return;
            const confirmed = window.confirm(`Remove inactive user ${currentDataRef.full_name} (${currentDataRef.code}) from directory?`);
            if (!confirmed) return;
            currentCardRef.remove();
            closeProfileModal();
        });
    }

    // ==================== + NEW ADMIN ACCOUNT MODAL ====================
    const newAdminModal = document.getElementById('newAdminModal');
    const closeNewAdminBtn = document.getElementById('closeNewAdminModalBtn');
    const cancelNewAdminBtn = document.getElementById('cancelNewAdminBtn');

    function openNewAdminModal() {
        if (!newAdminModal) return;
        newAdminModal.classList.add('open');
        newAdminModal.setAttribute('aria-hidden', 'false');
    }

    function closeNewAdminModal() {
        if (!newAdminModal) return;
        newAdminModal.classList.remove('open');
        newAdminModal.setAttribute('aria-hidden', 'true');
    }

    if (newAdminBtn) newAdminBtn.addEventListener('click', openNewAdminModal);
    if (closeNewAdminBtn) closeNewAdminBtn.addEventListener('click', closeNewAdminModal);
    if (cancelNewAdminBtn) cancelNewAdminBtn.addEventListener('click', closeNewAdminModal);
    if (newAdminModal) {
        newAdminModal.addEventListener('click', (e) => {
            if (e.target === newAdminModal) closeNewAdminModal();
        });
    }
});