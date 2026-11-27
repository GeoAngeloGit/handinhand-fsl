document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Mobile sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ==================== FILTER & SEARCH ====================
    const searchInput = document.getElementById('classSearchInput');
    const levelFilter = document.getElementById('classLevelFilter');
    const statusPills = document.querySelectorAll('.status-pill-btn');
    const rowsStack = document.getElementById('classesRowsStack');
    const emptyFilter = document.getElementById('classesEmptyFilter');
    const countLabel = document.getElementById('classCountLabel');

    let activeStatus = 'ALL';

    function filterClasses() {
        if (!rowsStack) return;
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const level = levelFilter ? levelFilter.value : 'ALL';
        const cards = Array.from(rowsStack.querySelectorAll('.dir-row-card'));

        if (cards.length === 0) return;

        let visible = 0;
        cards.forEach(card => {
            const hay = card.dataset.search || '';
            const cardLevel = card.dataset.level || '';
            const cardStatus = card.dataset.status || '';

            const matchSearch = !query || hay.includes(query);
            const matchLevel = level === 'ALL' || cardLevel === level;
            const matchStatus = activeStatus === 'ALL' || cardStatus === activeStatus;

            const show = matchSearch && matchLevel && matchStatus;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (countLabel) {
            countLabel.textContent = `Showing ${visible} class${visible === 1 ? '' : 'es'}`;
        }
        if (emptyFilter) {
            emptyFilter.style.display = visible === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterClasses);
    if (levelFilter) levelFilter.addEventListener('change', filterClasses);

    statusPills.forEach(pill => {
        pill.addEventListener('click', () => {
            activeStatus = pill.dataset.status || 'ALL';
            statusPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            filterClasses();
        });
    });

    // ==================== CREATE CLASS MODAL & REPEATABLE TEACHERS ====================
    const createModal = document.getElementById('createClassModal');
    const openCreateBtn = document.getElementById('openCreateClassModalBtn');
    const closeCreateBtn = document.getElementById('closeCreateClassBtn');
    const cancelCreateBtn = document.getElementById('cancelCreateClassBtn');

    function toggleCreateModal(show) {
        if (!createModal) return;
        createModal.classList.toggle('open', show);
        createModal.setAttribute('aria-hidden', String(!show));
    }

    if (openCreateBtn) openCreateBtn.addEventListener('click', () => toggleCreateModal(true));
    if (closeCreateBtn) closeCreateBtn.addEventListener('click', () => toggleCreateModal(false));
    if (cancelCreateBtn) cancelCreateBtn.addEventListener('click', () => toggleCreateModal(false));
    if (createModal) {
        createModal.addEventListener('click', e => {
            if (e.target === createModal) toggleCreateModal(false);
        });
    }

    // Repeatable Teacher Rows
    const teacherRowsContainer = document.getElementById('teacherRowsContainer');
    const addTeacherRowBtn = document.getElementById('addTeacherRowBtn');
    const createClassForm = document.getElementById('createClassForm');

    function bindRowRemove(row) {
        const removeBtn = row.querySelector('.btn-remove-teacher-row');
        if (!removeBtn || !teacherRowsContainer) return;
        removeBtn.addEventListener('click', () => {
            const allRows = teacherRowsContainer.querySelectorAll('.teacher-assign-row');
            if (allRows.length <= 1) {
                row.querySelector('select[name="teacher_ids[]"]').value = '';
                return;
            }
            row.remove();
        });
    }

    if (teacherRowsContainer) {
        teacherRowsContainer.querySelectorAll('.teacher-assign-row').forEach(bindRowRemove);
    }

    if (addTeacherRowBtn && teacherRowsContainer) {
        addTeacherRowBtn.addEventListener('click', () => {
            const templateRow = teacherRowsContainer.querySelector('.teacher-assign-row');
            if (!templateRow) return;
            const clone = templateRow.cloneNode(true);
            const teacherSelect = clone.querySelector('select[name="teacher_ids[]"]');
            const roleSelect = clone.querySelector('select[name="teacher_roles[]"]');
            if (teacherSelect) teacherSelect.value = '';
            if (roleSelect) roleSelect.value = 'Co-teacher';
            bindRowRemove(clone);
            teacherRowsContainer.appendChild(clone);
        });
    }

    if (createClassForm && teacherRowsContainer) {
        createClassForm.addEventListener('submit', e => {
            const teacherIds = Array.from(teacherRowsContainer.querySelectorAll('select[name="teacher_ids[]"]'))
                .map(s => s.value)
                .filter(Boolean);
            const roles = Array.from(teacherRowsContainer.querySelectorAll('select[name="teacher_roles[]"]'))
                .map(s => s.value);

            if (new Set(teacherIds).size !== teacherIds.length) {
                e.preventDefault();
                alert('Each assigned teacher must be unique. Remove duplicate teacher selections.');
                return;
            }

            const leadCount = roles.filter(r => r === 'Lead Instructor').length;
            if (leadCount !== 1) {
                e.preventDefault();
                alert('Please assign exactly one Lead Instructor for this class.');
            }
        });
    }

    // ==================== MANAGE CLASS DETAILS MODAL ====================
    const manageModal = document.getElementById('manageClassModal');
    const closeManageBtn = document.getElementById('closeManageClassBtn');
    const closeFooterBtn = document.getElementById('mcCloseFooterBtn');

    const mcSubMeta = document.getElementById('mcSubMeta');
    const mcStatusBadge = document.getElementById('mcStatusBadge');
    const mcTitle = document.getElementById('mcTitle');
    const mcHubLabel = document.getElementById('mcHubLabel');
    const mcLeadTeacher = document.getElementById('mcLeadTeacher');
    const mcCapacityLabel = document.getElementById('mcCapacityLabel');
    const mcDescLabel = document.getElementById('mcDescLabel');
    const mcStatusSelect = document.getElementById('mcStatusSelect');

    const mcSchedulesList = document.getElementById('mcSchedulesList');
    const mcTeachersList = document.getElementById('mcTeachersList');
    const mcLearnersList = document.getElementById('mcLearnersList');

    let currentClass = null;

    async function postClassAction(payload) {
        const res = await fetch('actions/admin_manage_class.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, ...payload })
        });
        return res.json();
    }

    function renderManageModal(cls) {
        currentClass = cls;
        if (mcSubMeta) mcSubMeta.textContent = `${cls.code} • ${cls.skill_level.toUpperCase()} LEVEL`;
        if (mcTitle) mcTitle.textContent = cls.title;
        if (mcStatusBadge) {
            mcStatusBadge.innerHTML = `&bull; ${cls.status}`;
            mcStatusBadge.className = `dir-status-badge ${cls.status === 'Open' ? 'status-active' : 'status-inactive'}`;
        }
        if (mcHubLabel) mcHubLabel.textContent = cls.hub_label;
        if (mcLeadTeacher) mcLeadTeacher.textContent = cls.lead_teacher;
        if (mcCapacityLabel) mcCapacityLabel.textContent = `${cls.enrolled_count} / ${cls.max_capacity} Seats`;
        if (mcDescLabel) mcDescLabel.textContent = cls.description || 'No syllabus description provided.';
        if (mcStatusSelect) mcStatusSelect.value = cls.status;

        // Render Schedules
        if (mcSchedulesList) {
            mcSchedulesList.innerHTML = '';
            if (!cls.schedules || cls.schedules.length === 0) {
                mcSchedulesList.innerHTML = '<div class="empty-filter-state">No sessions scheduled yet.</div>';
            } else {
                cls.schedules.forEach(s => {
                    const row = document.createElement('div');
                    row.className = 'modal-field-row';
                    row.innerHTML = `
                        <div>
                            <div class="modal-field-value" style="text-align:left;">${s.date_label} (${s.time_label})</div>
                            <div class="modal-field-label">${s.room_location || 'Hub Room'}</div>
                        </div>
                        <button type="button" class="btn-delete-user" data-del-sched="${s.schedule_id}" style="padding:4px 10px;">Remove</button>
                    `;
                    row.querySelector('[data-del-sched]').addEventListener('click', async () => {
                        const out = await postClassAction({
                            action: 'remove_schedule',
                            class_id: cls.class_id,
                            schedule_id: s.schedule_id
                        });
                        if (out.ok) window.location.reload();
                        else alert(out.message || 'Could not remove session.');
                    });
                    mcSchedulesList.appendChild(row);
                });
            }
        }

        // Render Assigned Teachers
        if (mcTeachersList) {
            mcTeachersList.innerHTML = '';
            if (!cls.assigned_teachers || cls.assigned_teachers.length === 0) {
                mcTeachersList.innerHTML = '<div class="empty-filter-state">No educators assigned.</div>';
            } else {
                cls.assigned_teachers.forEach(t => {
                    const row = document.createElement('div');
                    row.className = 'modal-field-row';
                    row.innerHTML = `
                        <span class="modal-field-value" style="text-align:left;">${t.full_name} <small style="color:var(--text-muted);">(${t.role})</small></span>
                        <button type="button" class="btn-delete-user" data-rem-tch="${t.teacher_id}" style="padding:4px 10px;">Unassign</button>
                    `;
                    row.querySelector('[data-rem-tch]').addEventListener('click', async () => {
                        const out = await postClassAction({
                            action: 'unassign_teacher',
                            class_id: cls.class_id,
                            teacher_id: t.teacher_id
                        });
                        if (out.ok) window.location.reload();
                        else alert(out.message || 'Could not unassign educator.');
                    });
                    mcTeachersList.appendChild(row);
                });
            }
        }

        // Render Enrolled Learners
        if (mcLearnersList) {
            mcLearnersList.innerHTML = '';
            if (!cls.enrolled_learners || cls.enrolled_learners.length === 0) {
                mcLearnersList.innerHTML = '<div class="empty-filter-state">No learners enrolled yet.</div>';
            } else {
                cls.enrolled_learners.forEach(l => {
                    const row = document.createElement('div');
                    row.className = 'modal-field-row';
                    row.innerHTML = `
                        <span class="modal-field-value" style="text-align:left;">${l.code} — ${l.full_name}</span>
                        <button type="button" class="btn-delete-user" data-drop-lrn="${l.learner_id}" style="padding:4px 10px;">Remove</button>
                    `;
                    row.querySelector('[data-drop-lrn]').addEventListener('click', async () => {
                        const out = await postClassAction({
                            action: 'remove_learner',
                            class_id: cls.class_id,
                            learner_id: l.learner_id
                        });
                        if (out.ok) window.location.reload();
                        else alert(out.message || 'Could not remove learner.');
                    });
                    mcLearnersList.appendChild(row);
                });
            }
        }
    }

    document.querySelectorAll('#classesRowsStack .dir-row-card').forEach(card => {
        card.addEventListener('click', () => {
            const raw = card.dataset.class;
            if (!raw) return;
            renderManageModal(JSON.parse(raw));
            manageModal.classList.add('open');
            manageModal.setAttribute('aria-hidden', 'false');
        });
    });

    function closeManageModal() {
        if (!manageModal) return;
        manageModal.classList.remove('open');
        manageModal.setAttribute('aria-hidden', 'true');
    }

    if (closeManageBtn) closeManageBtn.addEventListener('click', closeManageModal);
    if (closeFooterBtn) closeFooterBtn.addEventListener('click', closeManageModal);
    if (manageModal) {
        manageModal.addEventListener('click', e => {
            if (e.target === manageModal) closeManageModal();
        });
    }

    // Modal sub-tab switcher
    document.querySelectorAll('.mc-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.mctab;
            document.querySelectorAll('.mc-tab-btn').forEach(b => b.classList.toggle('active', b === btn));
            document.querySelectorAll('.mc-pane').forEach(p => {
                p.style.display = (p.id === `mcPane-${target}`) ? '' : 'none';
            });
        });
    });

    // Update Class Status
    document.getElementById('mcSaveStatusBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        const out = await postClassAction({
            action: 'update_status',
            class_id: currentClass.class_id,
            status: mcStatusSelect.value
        });
        if (out.ok) window.location.reload();
        else alert(out.message || 'Failed to update status.');
    });

    // Delete Class
    document.getElementById('mcDeleteClassBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        if (!confirm(`Delete class "${currentClass.title}" (${currentClass.code})? This removes its schedules and enrollments.`)) return;
        const out = await postClassAction({
            action: 'delete_class',
            class_id: currentClass.class_id
        });
        if (out.ok) window.location.reload();
        else alert(out.message || 'Could not delete class.');
    });

    // Add Session Date
    document.getElementById('mcAddScheduleBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        const session_date = document.getElementById('mcNewSchedDate').value;
        const room_location = document.getElementById('mcNewSchedRoom').value.trim();
        const start_time = document.getElementById('mcNewSchedStart').value;
        const end_time = document.getElementById('mcNewSchedEnd').value;

        if (!session_date || !start_time) {
            alert('Please provide a session date and start time.');
            return;
        }
        const out = await postClassAction({
            action: 'add_schedule',
            class_id: currentClass.class_id,
            session_date,
            start_time,
            end_time,
            room_location
        });
        if (out.ok) window.location.reload();
        else alert(out.message || 'Could not add session date.');
    });

    // Assign Teacher
    document.getElementById('mcAssignTeacherBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        const teacher_id = parseInt(document.getElementById('mcAssignTeacherSelect').value || '0', 10);
        const role = document.getElementById('mcAssignTeacherRole').value;
        if (!teacher_id) return alert('Select an educator first.');

        const out = await postClassAction({
            action: 'assign_teacher',
            class_id: currentClass.class_id,
            teacher_id,
            role
        });
        if (out.ok) window.location.reload();
        else alert(out.message || 'Could not assign educator.');
    });

    // Enroll Learner
    document.getElementById('mcEnrollLearnerBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        const learner_id = parseInt(document.getElementById('mcEnrollLearnerSelect').value || '0', 10);
        if (!learner_id) return alert('Select a learner first.');

        const out = await postClassAction({
            action: 'enroll_learner',
            class_id: currentClass.class_id,
            learner_id
        });
        if (out.ok) window.location.reload();
        else alert(out.message || 'Could not enroll learner.');
    });
});