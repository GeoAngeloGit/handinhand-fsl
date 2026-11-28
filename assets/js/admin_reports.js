document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Mobile sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    const searchInput = document.getElementById('provSearchInput');
    const statusFilter = document.getElementById('provStatusFilter');
    const teacherFilter = document.getElementById('provTeacherFilter');
    const grid = document.getElementById('provReportsGrid');
    const emptyFilter = document.getElementById('provEmptyFilter');
    const exportCsvBtn = document.getElementById('exportProvincialCsvBtn');

    function filterReports() {
        if (!grid) return;
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const status = statusFilter ? statusFilter.value : 'ALL';
        const teacherId = teacherFilter ? teacherFilter.value : 'ALL';
        const cards = Array.from(grid.querySelectorAll('.prov-report-card'));

        if (cards.length === 0) return;

        let visible = 0;
        cards.forEach(card => {
            const hay = card.dataset.search || '';
            const cardStatus = card.dataset.status || '';
            const cardTeacher = card.dataset.teacherId || '';

            const matchSearch = !query || hay.includes(query);
            const matchStatus = status === 'ALL' || cardStatus === status;
            const matchTeacher = teacherId === 'ALL' || cardTeacher === teacherId;

            const show = matchSearch && matchStatus && matchTeacher;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        if (emptyFilter) {
            emptyFilter.style.display = visible === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterReports);
    if (statusFilter) statusFilter.addEventListener('change', filterReports);
    if (teacherFilter) teacherFilter.addEventListener('change', filterReports);

    // ==================== REPORT INSPECTION MODAL ====================
    const modal = document.getElementById('provReportModal');
    const closeBtn = document.getElementById('closePrmBtn');
    const cancelBtn = document.getElementById('cancelPrmBtn');
    const saveReviewBtn = document.getElementById('savePrmReviewBtn');

    let activeReport = null;

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        activeReport = null;
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', e => {
            if (e.target === modal) closeModal();
        });
    }

    document.querySelectorAll('.prov-report-card').forEach(card => {
        card.addEventListener('click', () => {
            const raw = card.dataset.report;
            if (!raw || !modal) return;
            const rpt = JSON.parse(raw);
            activeReport = rpt;

            document.getElementById('prmEyebrow').textContent = `${rpt.code} • ${rpt.schedule_code}`;
            document.getElementById('prmTitle').textContent = rpt.class_title;
            document.getElementById('prmMetaLine').textContent =
                `${rpt.location_label} • ${rpt.session_date_fmt} (${rpt.session_time}) • By ${rpt.teacher_display} (${rpt.teacher_code})`;

            const badge = document.getElementById('prmStatusBadge');
            badge.textContent = rpt.status;
            badge.className = 'prov-status-pill ' + (
                rpt.status === 'Verified' ? 'rpt-badge-reviewed' :
                rpt.status === 'For Revision' ? 'rpt-badge-revision' : 'rpt-badge-review'
            );

            document.getElementById('prmSummaryText').textContent = rpt.summary;
            document.getElementById('prmAttBadge').textContent = rpt.att_summary;
            document.getElementById('prmReviewStatusSelect').value = rpt.status;
            document.getElementById('prmCoordinatorNotes').value = rpt.coordinator_note || '';

            const photoWrap = document.getElementById('prmPhotoWrap');
            const photoImg = document.getElementById('prmPhotoImg');
            if (rpt.has_photo && rpt.photo_url) {
                photoImg.src = rpt.photo_url;
                photoWrap.style.display = 'block';
            } else {
                photoWrap.style.display = 'none';
            }

            // Render Attendance List
            const attList = document.getElementById('prmAttendanceList');
            attList.innerHTML = '';
            if (!rpt.learner_logs || rpt.learner_logs.length === 0) {
                attList.innerHTML = '<div class="empty-filter-state">No attendance logs recorded for this session.</div>';
            } else {
                rpt.learner_logs.forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'modal-field-row';
                    row.innerHTML = `
                        <span class="modal-field-value" style="text-align:left;">${item.code} — ${item.name}</span>
                        <span class="dir-status-badge ${item.status === 'Present' ? 'status-active' : 'status-inactive'}">&bull; ${item.status}</span>
                    `;
                    attList.appendChild(row);
                });
            }

            // Render Grades List
            const grdList = document.getElementById('prmGradesList');
            grdList.innerHTML = '';
            if (!rpt.grade_logs || rpt.grade_logs.length === 0) {
                grdList.innerHTML = '<div class="empty-filter-state">No assessment grades recorded for this class yet.</div>';
            } else {
                rpt.grade_logs.forEach(g => {
                    const row = document.createElement('div');
                    row.className = 'modal-field-row';
                    row.innerHTML = `
                        <span class="modal-field-value" style="text-align:left;">${g.name} <small style="color:var(--text-muted);">(${g.assessment})</small></span>
                        <span class="dir-class-pill pill-tch">${g.score}</span>
                    `;
                    grdList.appendChild(row);
                });
            }

            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    // Save Coordinator Review Status
    if (saveReviewBtn) {
        saveReviewBtn.addEventListener('click', async () => {
            if (!activeReport) return;
            const review_status = document.getElementById('prmReviewStatusSelect').value;
            const coordinator_notes = document.getElementById('prmCoordinatorNotes').value.trim();

            saveReviewBtn.disabled = true;
            saveReviewBtn.textContent = 'Saving...';

            try {
                const res = await fetch('actions/admin_review_report.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        report_id: activeReport.report_id,
                        review_status,
                        coordinator_notes
                    })
                });
                const out = await res.json();
                if (out.ok) {
                    window.location.reload();
                } else {
                    alert(out.message || 'Could not update report review status.');
                }
            } catch (err) {
                alert('Network error while saving review status.');
            } finally {
                saveReviewBtn.disabled = false;
                saveReviewBtn.textContent = 'Save Review Status';
            }
        });
    }

    // ==================== EXPORT CSV ====================
    if (exportCsvBtn && grid) {
        exportCsvBtn.addEventListener('click', () => {
            const visibleCards = Array.from(grid.querySelectorAll('.prov-report-card'))
                .filter(c => c.style.display !== 'none');

            if (visibleCards.length === 0) {
                alert('No visible reports to export.');
                return;
            }

            const headers = ['Report ID', 'Schedule ID', 'Class Title', 'Location', 'Session Date', 'Teacher', 'Attendance', 'Status', 'Summary'];
            const lines = [headers.map(h => `"${h}"`).join(',')];

            visibleCards.forEach(card => {
                const r = JSON.parse(card.dataset.report || '{}');
                const row = [
                    r.code || '',
                    r.schedule_code || '',
                    r.class_title || '',
                    r.location_label || '',
                    `${r.session_date_fmt || ''} ${r.session_time || ''}`.trim(),
                    `${r.teacher_display || ''} (${r.teacher_code || ''})`,
                    r.att_summary || '',
                    r.status || '',
                    r.summary || ''
                ].map(val => `"${String(val).replace(/"/g, '""')}"`);
                lines.push(row.join(','));
            });

            const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `handinhand_provincial_reports_${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        });
    }
});