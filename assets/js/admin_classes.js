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
            const matchLevel = !level || level === 'ALL' || cardLevel === level;
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
            if (roleSelect) roleSelect.value = 'Co-Teacher';
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
    const mcAddScheduleBtn = document.getElementById('mcAddScheduleBtn');
    const mcCancelSchedEditBtn = document.getElementById('mcCancelSchedEditBtn');
    const mcOverviewView = document.getElementById('mcOverviewView');
    const mcEditClassPanel = document.getElementById('mcEditClassPanel');
    const ecTeacherRowsContainer = document.getElementById('ecTeacherRowsContainer');
    const ecTeacherRowTemplate = document.getElementById('ecTeacherRowTemplate');

    let currentClass = null;
    let editingScheduleId = null;

    function resetScheduleForm() {
        editingScheduleId = null;
        const dEl = document.getElementById('mcNewSchedDate');
        const rEl = document.getElementById('mcNewSchedRoom');
        const sEl = document.getElementById('mcNewSchedStart');
        const eEl = document.getElementById('mcNewSchedEnd');
        if (dEl) dEl.value = '';
        if (rEl) rEl.value = '';
        if (sEl) sEl.value = '09:00';
        if (eEl) eEl.value = '11:00';
        if (mcAddScheduleBtn) mcAddScheduleBtn.textContent = '+ Add Session Date';
        if (mcCancelSchedEditBtn) mcCancelSchedEditBtn.style.display = 'none';
    }

    function toggleEditClassPanel(show) {
        if (mcOverviewView) mcOverviewView.style.display = show ? 'none' : '';
        if (mcEditClassPanel) mcEditClassPanel.style.display = show ? '' : 'none';
    }

    function createEditTeacherRow(teacherId = '', role = 'Co-Teacher') {
        if (!ecTeacherRowTemplate || !ecTeacherRowsContainer) return;
        const frag = ecTeacherRowTemplate.content.cloneNode(true);
        const row = frag.querySelector('.teacher-assign-row');
        const tSel = row.querySelector('.ec-teacher-id');
        const rSel = row.querySelector('.ec-teacher-role');
        if (tSel && teacherId) tSel.value = String(teacherId);
        if (rSel && role) rSel.value = role;
        const remBtn = row.querySelector('.btn-remove-teacher-row');
        if (remBtn) {
            remBtn.addEventListener('click', () => {
                const allRows = ecTeacherRowsContainer.querySelectorAll('.teacher-assign-row');
                if (allRows.length <= 1) {
                    if (tSel) tSel.value = '';
                } else {
                    row.remove();
                }
            });
        }
        ecTeacherRowsContainer.appendChild(row);
    }

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
        resetScheduleForm();
        toggleEditClassPanel(false);
        if (mcSubMeta) mcSubMeta.textContent = `${cls.code} • ${cls.hub_label}`;
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
                            <div class="modal-field-label">${s.venue || s.room_location || 'Hub Room'}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <select class="modal-field-input" data-sched-status="${s.schedule_id}" style="width:115px;padding:3px 6px;">
                                <option value="scheduled" ${s.status === 'scheduled' ? 'selected' : ''}>Scheduled</option>
                                <option value="ongoing" ${s.status === 'ongoing' ? 'selected' : ''}>Ongoing</option>
                                <option value="rescheduled" ${s.status === 'rescheduled' ? 'selected' : ''}>Rescheduled</option>
                                <option value="cancelled" ${s.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                <option value="completed" ${s.status === 'completed' ? 'selected' : ''}>Completed</option>
                            </select>
                            <button type="button" class="btn-modal-close" data-edit-sched="${s.schedule_id}" style="padding:4px 10px;">Edit</button>
                            <button type="button" class="btn-delete-user" data-del-sched="${s.schedule_id}" style="padding:4px 10px;">Remove</button>
                        </div>
                    `;
                    row.querySelector('[data-sched-status]').addEventListener('change', async (e) => {
                        const out = await postClassAction({
                            action: 'update_schedule_status',
                            class_id: cls.class_id,
                            schedule_id: s.schedule_id,
                            status: e.target.value
                        });
                        if (out.ok) window.location.reload();
                        else alert(out.message || 'Could not update session status.');
                    });
                    row.querySelector('[data-edit-sched]').addEventListener('click', () => {
                        editingScheduleId = s.schedule_id;
                        const dEl = document.getElementById('mcNewSchedDate');
                        const rEl = document.getElementById('mcNewSchedRoom');
                        const sEl = document.getElementById('mcNewSchedStart');
                        const eEl = document.getElementById('mcNewSchedEnd');
                        if (dEl) dEl.value = s.session_date || '';
                        if (rEl) rEl.value = s.venue || s.room_location || '';
                        if (sEl) sEl.value = (s.start_time || '09:00').slice(0, 5);
                        if (eEl) eEl.value = (s.end_time || '11:00').slice(0, 5);
                        if (mcAddScheduleBtn) mcAddScheduleBtn.textContent = 'Save Schedule Changes';
                        if (mcCancelSchedEditBtn) mcCancelSchedEditBtn.style.display = 'inline-flex';
                    });
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

    // Open / Cancel / Save Edit Class Details
    document.getElementById('mcOpenEditClassBtn')?.addEventListener('click', () => {
        if (!currentClass) return;
        const tEl = document.getElementById('ecTitle');
        const dEl = document.getElementById('ecDescription');
        const cEl = document.getElementById('ecCapacity');
        const sEl = document.getElementById('ecStatus');
        if (tEl) tEl.value = currentClass.title || '';
        if (dEl) dEl.value = currentClass.description || '';
        if (cEl) cEl.value = String(currentClass.max_capacity || 20);
        if (sEl) sEl.value = currentClass.status || 'Open';
        if (ecTeacherRowsContainer) {
            ecTeacherRowsContainer.innerHTML = '';
            const assigned = Array.isArray(currentClass.assigned_teachers) ? currentClass.assigned_teachers : [];
            if (assigned.length > 0) {
                assigned.forEach(t => createEditTeacherRow(t.teacher_id, t.role));
            } else {
                createEditTeacherRow('', 'Lead Instructor');
            }
        }
        toggleEditClassPanel(true);
    });

    document.getElementById('ecAddTeacherRowBtn')?.addEventListener('click', () => {
        createEditTeacherRow('', 'Co-Teacher');
    });

    document.getElementById('ecCancelEditBtn')?.addEventListener('click', () => {
        toggleEditClassPanel(false);
    });

    document.getElementById('ecSaveClassBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        const class_title = document.getElementById('ecTitle')?.value.trim() || '';
        const description = document.getElementById('ecDescription')?.value.trim() || '';
        const max_capacity = parseInt(document.getElementById('ecCapacity')?.value || '0', 10);
        const status = document.getElementById('ecStatus')?.value || 'Open';

        const rows = ecTeacherRowsContainer ? Array.from(ecTeacherRowsContainer.querySelectorAll('.teacher-assign-row')) : [];
        const teachers = [];
        const seen = new Set();
        let leadCount = 0;

        for (const r of rows) {
            const tid = parseInt(r.querySelector('.ec-teacher-id')?.value || '0', 10);
            const role = r.querySelector('.ec-teacher-role')?.value || 'Co-Teacher';
            if (!tid) continue;
            if (seen.has(tid)) {
                alert('Each assigned teacher must be unique. Remove duplicate teacher selections.');
                return;
            }
            seen.add(tid);
            if (role === 'Lead Instructor') leadCount++;
            teachers.push({ teacher_id: tid, role });
        }

        if (!class_title || max_capacity < 1) {
            alert('Class title and a valid max capacity (>= 1) are required.');
            return;
        }
        if (teachers.length === 0 || leadCount !== 1) {
            alert('Please assign at least one teacher and exactly one Lead Instructor.');
            return;
        }

        const out = await postClassAction({
            action: 'edit_class',
            class_id: currentClass.class_id,
            class_title,
            description,
            max_capacity,
            status,
            teachers
        });
        if (out.ok) window.location.reload();
        else alert(out.message || 'Could not update class details.');
    });

    // Add or Edit Session Date
    if (mcCancelSchedEditBtn) {
        mcCancelSchedEditBtn.addEventListener('click', resetScheduleForm);
    }

    document.getElementById('mcAddScheduleBtn')?.addEventListener('click', async () => {
        if (!currentClass) return;
        const session_date = document.getElementById('mcNewSchedDate').value;
        const venue = document.getElementById('mcNewSchedRoom').value.trim();
        const start_time = document.getElementById('mcNewSchedStart').value;
        const end_time = document.getElementById('mcNewSchedEnd').value;

        if (!session_date || !start_time || !end_time) {
            alert('Please provide a session date, start time, and end time.');
            return;
        }
        if (end_time <= start_time) {
            alert('End time must be after start time.');
            return;
        }
        const payload = editingScheduleId
            ? {
                action: 'edit_schedule',
                class_id: currentClass.class_id,
                schedule_id: editingScheduleId,
                session_date,
                start_time,
                end_time,
                venue
            }
            : {
                action: 'add_schedule',
                class_id: currentClass.class_id,
                session_date,
                start_time,
                end_time,
                venue
            };
        const out = await postClassAction(payload);
        if (out.ok) window.location.reload();
        else alert(out.message || 'Could not save session schedule.');
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