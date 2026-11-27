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

        if (cards.length === 0) {
            if (emptyStateEl) emptyStateEl.style.display = 'none';
            return;
        }

        cards.sort((a, b) => {
            if (sortMode === 'id-asc') return (a.dataset.code || '').localeCompare(b.dataset.code || '');
            if (sortMode === 'id-desc') return (b.dataset.code || '').localeCompare(a.dataset.code || '');
            if (sortMode === 'name-asc') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            if (sortMode === 'name-desc') return (b.dataset.name || '').localeCompare(a.dataset.name || '');
            return 0;
        });
        cards.forEach(c => stack.appendChild(c));

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

            const readOnlyField = idx === 0 || field.label === 'Enrolled Classes' || field.label === 'Date Added';
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

        const isBerryTheme = currentDataRef.type === 'teacher' || currentDataRef.type === 'admin';
        if (modalInitials) {
            modalInitials.textContent = currentDataRef.initials || 'US';
            modalInitials.classList.toggle('theme-berry', isBerryTheme);
        }
        if (modalCodeLine) {
            modalCodeLine.textContent = `${currentDataRef.code} • ACCOUNT ${currentDataRef.account_code}`;
            if (modalCodeLine.parentElement) {
                modalCodeLine.parentElement.classList.toggle('theme-berry', isBerryTheme);
            }
        }
        if (modalProfileName) modalProfileName.textContent = currentDataRef.full_name;

        const isActive = currentDataRef.status === 'Active';
        if (modalStatusBadge) {
            modalStatusBadge.className = `dir-status-badge ${isActive ? 'status-active' : 'status-inactive'}`;
            modalStatusBadge.innerHTML = `&bull; ${currentDataRef.status}`;
        }

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

    const sidebarCoordCard = document.getElementById('sidebarCoordCard');
    if (sidebarCoordCard) {
        sidebarCoordCard.addEventListener('click', (e) => {
            if (e.target.closest('.logout-link')) return;
            const myAdminRow = document.querySelector('#panel-admins .dir-row-card');
            if (myAdminRow) {
                openProfileModal(myAdminRow);
            }
        });
    }

    if (closeProfileBtn) closeProfileBtn.addEventListener('click', closeProfileModal);
    if (cancelProfileBtn) cancelProfileBtn.addEventListener('click', closeProfileModal);
    if (profileModal) {
        profileModal.addEventListener('click', (e) => {
            if (e.target === profileModal) closeProfileModal();
        });
    }

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
                }
            });

            const fields = {};
            currentDataRef.fields.forEach(f => { fields[f.label] = f.value; });

            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            modalEditSaveBtn.disabled = true;

            fetch('actions/admin_update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: tokenMeta ? tokenMeta.content : '',
                    type: currentDataRef.type,
                    code: currentDataRef.code,
                    fields: fields
                })
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    window.location.reload();
                } else {
                    alert(res.message || 'Could not save changes.');
                    modalEditSaveBtn.disabled = false;
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                modalEditSaveBtn.disabled = false;
                    });
                }
            });
        }

// ==================== DELETE USER (PERSISTED TO MYSQL) ====================
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!activeUser || !activeCard) return;

            const confirmed = confirm(
                `Delete ${activeUser.full_name} (${activeUser.display_code})?\n\nIf this user has historical class or grade records, their account will be set to Inactive instead.`
            );
            if (!confirmed) return;

            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting...';

            try {
                const res = await fetch('actions/admin_delete_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        account_id: activeUser.account_id,
                        role_type: activeUser.role_type
                    })
                });
                const data = await res.json();

                if (!res.ok || !data.ok) {
                    alert(data.message || 'Could not delete user.');
                    return;
                }

                closeModal();
                window.location.reload();
            } catch (err) {
                alert('Network error while deleting user.');
            } finally {
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Delete User';
            }
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