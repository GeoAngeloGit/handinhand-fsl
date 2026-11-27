document.addEventListener('DOMContentLoaded', () => {
    // ==================== 1. GLOBAL MOBILE SIDEBAR TOGGLE ====================
    const sidebar = document.getElementById('teacherSidebar');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ==================== 2. TEACHER CLASSES (teacher_classes.php) ====================
    const filterPills = document.querySelectorAll('.cls-filter-pill');
    const sortBtns = document.querySelectorAll('.sort-dir-btn');
    const viewBtns = document.querySelectorAll('.view-btn');
    const activeSection = document.getElementById('activeClassesSection');
    const activeGrid = document.getElementById('activeCardsGrid');
    const allGrid = document.getElementById('allClassesGrid');
    const timetableStack = document.getElementById('timetableCardsStack');
    const timetableHint = document.getElementById('timetableSortHint');
    const allSummary = document.getElementById('allClassesSummary');

    if (allGrid || activeGrid) {
        let currentStatus = 'ALL';
        let currentSort = 'earliest';

        function updateClassesDisplay() {
            if (activeSection) {
                activeSection.style.display = (currentStatus === 'ALL' || currentStatus === 'Active') ? '' : 'none';
            }

            const sortComparator = (a, b) => {
                const da = a.dataset.date || '';
                const db = b.dataset.date || '';
                return currentSort === 'earliest' ? da.localeCompare(db) : db.localeCompare(da);
            };

            if (allGrid) {
                const allCards = Array.from(allGrid.querySelectorAll('.all-class-card'));
                allCards.sort(sortComparator);
                let visible = 0;
                allCards.forEach(card => {
                    allGrid.appendChild(card);
                    const match = (currentStatus === 'ALL') || (card.dataset.status === currentStatus);
                    card.style.display = match ? '' : 'none';
                    if (match) visible++;
                });
                if (allSummary) {
                    const sortLabel = currentSort === 'earliest' ? 'Earliest' : 'Latest';
                    allSummary.textContent = `Sorted by ${sortLabel} • ${visible} Total`;
                }
            }

            if (activeGrid) {
                const actCards = Array.from(activeGrid.querySelectorAll('.class-feature-card'));
                actCards.sort(sortComparator);
                actCards.forEach(card => activeGrid.appendChild(card));
            }

            if (timetableStack) {
                const ttCards = Array.from(timetableStack.querySelectorAll('.timetable-item-card'));
                ttCards.sort(sortComparator);
                ttCards.forEach(card => timetableStack.appendChild(card));
                if (timetableHint) {
                    timetableHint.textContent = currentSort === 'earliest' ? 'Earliest First' : 'Latest First';
                }
            }
        }

        filterPills.forEach(pill => {
            pill.addEventListener('click', () => {
                currentStatus = pill.dataset.status || 'ALL';
                filterPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                updateClassesDisplay();
            });
        });

        sortBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                currentSort = btn.dataset.sort || 'earliest';
                sortBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                updateClassesDisplay();
            });
        });

        viewBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.view || 'grid';
                viewBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (activeGrid) activeGrid.classList.toggle('list-layout', mode === 'list');
                if (allGrid) allGrid.classList.toggle('list-layout', mode === 'list');
            });
        });

        // Class Details Modal
        const clsModal = document.getElementById('classDetailsModal');
        const closeClsBtn = document.getElementById('closeClassModalBtn');
        const cancelClsBtn = document.getElementById('cancelClassModalBtn');
        const mThumb = document.getElementById('clsModalThumb');
        const mCode = document.getElementById('clsModalCode');
        const mStatus = document.getElementById('clsModalStatus');
        const mTitle = document.getElementById('clsModalTitle');
        const mLoc = document.getElementById('clsModalLoc');
        const mDesc = document.getElementById('clsModalDesc');
        const mCap = document.getElementById('clsModalCap');
        const mSessionCnt = document.getElementById('clsModalSessionCount');
        const mTeachers = document.getElementById('clsModalTeachers');
        const mSlots = document.getElementById('clsModalSlots');
        const mRosterLink = document.getElementById('clsModalRosterLink');

        function openClassModal(card) {
            if (!clsModal || !card.dataset.modal) return;
            const data = JSON.parse(card.dataset.modal);

            if (mThumb) mThumb.className = `class-art-thumb cls-modal-thumb ${data.theme || 'thumb-maroon'}`;
            if (mCode) mCode.textContent = data.code;
            if (mTitle) mTitle.textContent = data.title;
            if (mLoc) mLoc.textContent = data.location;
            if (mDesc) mDesc.textContent = data.description;
            if (mCap) mCap.textContent = `${data.enrolled} / ${data.capacity} Learners`;
            if (mRosterLink) mRosterLink.href = `teacher_learners.php?class_id=${data.raw_id}`;

            const slotsList = Array.isArray(data.slots) ? data.slots : [];
            if (mSessionCnt) mSessionCnt.textContent = `${slotsList.length} Scheduled`;

            if (mStatus) {
                mStatus.textContent = data.status;
                mStatus.className = 'class-status-pill ' + (
                    data.status === 'Completed' ? 'status-completed' :
                    data.status === 'Re-scheduled' ? 'status-rescheduled' : 'status-active'
                );
            }

            if (mTeachers) {
                mTeachers.innerHTML = '';
                const teachers = Array.isArray(data.instructors) ? data.instructors : [];
                teachers.forEach((inst, idx) => {
                    const chip = document.createElement('span');
                    chip.className = 'teacher-chip';
                    chip.innerHTML = `<span class="chip-avatar chip-color-${idx % 4}">${inst.initials || 'TM'}</span><span>${inst.label || inst}</span>`;
                    mTeachers.appendChild(chip);
                });
            }

            if (mSlots) {
                mSlots.innerHTML = '';
                if (slotsList.length === 0) {
                    mSlots.innerHTML = '<span class="cls-slot-pill">No scheduled timetable slots yet</span>';
                } else {
                    slotsList.forEach(slotText => {
                        const pill = document.createElement('span');
                        pill.className = 'cls-slot-pill';
                        pill.textContent = slotText;
                        mSlots.appendChild(pill);
                    });
                }
            }

            clsModal.classList.add('open');
            clsModal.setAttribute('aria-hidden', 'false');
        }

        function closeClassModal() {
            if (!clsModal) return;
            clsModal.classList.remove('open');
            clsModal.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('.class-feature-card, .all-class-card').forEach(card => {
            card.addEventListener('click', () => openClassModal(card));
        });

        if (closeClsBtn) closeClsBtn.addEventListener('click', closeClassModal);
        if (cancelClsBtn) cancelClsBtn.addEventListener('click', closeClassModal);
        if (clsModal) {
            clsModal.addEventListener('click', (e) => {
                if (e.target === clsModal) closeClassModal();
            });
        }

        // Auto-open target class modal when redirected via ?class_id=
        const urlParams = new URLSearchParams(window.location.search);
        const targetClassId = urlParams.get('class_id');
        if (targetClassId) {
            const targetCard = document.getElementById(`class-${targetClassId}`) ||
                document.querySelector(`.class-feature-card[data-code="CLS-${String(targetClassId).padStart(3, '0')}"]`);
            if (targetCard) {
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => openClassModal(targetCard), 250);
            }
        }
    }

    // ==================== 3. TEACHER LEARNER LISTS (teacher_learners.php) ====================
    const lrnRowsStack = document.getElementById('lrnRowsStack');
    if (lrnRowsStack) {
        const classSelect = document.getElementById('classFilterSelect');
        const prevClassBtn = document.getElementById('prevClassBtn');
        const nextClassBtn = document.getElementById('nextClassBtn');
        const dirTitleEl = document.getElementById('lrnDirectoryTitle');
        const activeSummaryEl = document.getElementById('lrnActiveSummary');
        const searchInput = document.getElementById('lrnSearchInput');
        const sortSelect = document.getElementById('lrnSortSelect');
        const statusBtns = document.querySelectorAll('.lrn-status-btn');
        const emptyState = document.getElementById('lrnEmptyFilterState');

        let activeLrnStatus = 'ALL';

        function filterAndSortLearners() {
            const selectedClass = classSelect ? classSelect.value : 'ALL';
            const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const sortMode = sortSelect ? sortSelect.value : 'id-asc';

            if (classSelect && dirTitleEl) {
                const opt = classSelect.options[classSelect.selectedIndex];
                const titleLabel = opt ? (opt.dataset.title || 'All Assigned Classes') : 'All Assigned Classes';
                dirTitleEl.textContent = `Learners Directory (${titleLabel})`;
            }

            const cards = Array.from(lrnRowsStack.querySelectorAll('.lrn-row-card'));
            if (cards.length === 0) return;

            cards.sort((a, b) => {
                if (sortMode === 'id-asc') return (a.dataset.code || '').localeCompare(b.dataset.code || '');
                if (sortMode === 'id-desc') return (b.dataset.code || '').localeCompare(a.dataset.code || '');
                if (sortMode === 'name-asc') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                if (sortMode === 'name-desc') return (b.dataset.name || '').localeCompare(a.dataset.name || '');
                return 0;
            });

            let classActiveCount = 0;
            let classInactiveCount = 0;
            let visibleCount = 0;

            cards.forEach(card => {
                lrnRowsStack.appendChild(card);

                const code = card.dataset.code || '';
                const name = card.dataset.name || '';
                const email = card.dataset.email || '';
                const status = card.dataset.status || 'Active';
                const classIds = (card.dataset.classes || '').split(',').filter(Boolean);

                const matchClass = (selectedClass === 'ALL') || classIds.includes(selectedClass);
                if (matchClass) {
                    if (status === 'Active') classActiveCount++;
                    else classInactiveCount++;
                }

                const matchStatus = (activeLrnStatus === 'ALL') || (status === activeLrnStatus);
                const matchQuery = !query || code.includes(query) || name.includes(query) || email.includes(query);

                const show = matchClass && matchStatus && matchQuery;
                card.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (activeSummaryEl) {
                activeSummaryEl.textContent = `${classActiveCount} Active • ${classInactiveCount} Inactive`;
            }
            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        if (classSelect) classSelect.addEventListener('change', filterAndSortLearners);
        if (searchInput) searchInput.addEventListener('input', filterAndSortLearners);
        if (sortSelect) sortSelect.addEventListener('change', filterAndSortLearners);

        if (prevClassBtn && classSelect) {
            prevClassBtn.addEventListener('click', () => {
                const len = classSelect.options.length;
                classSelect.selectedIndex = (classSelect.selectedIndex - 1 + len) % len;
                filterAndSortLearners();
            });
        }

        if (nextClassBtn && classSelect) {
            nextClassBtn.addEventListener('click', () => {
                const len = classSelect.options.length;
                classSelect.selectedIndex = (classSelect.selectedIndex + 1) % len;
                filterAndSortLearners();
            });
        }

        statusBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                activeLrnStatus = btn.dataset.status || 'ALL';
                statusBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filterAndSortLearners();
            });
        });

        filterAndSortLearners();

        // Learner Profile Modal
        const lrnModal = document.getElementById('learnerProfileModal');
        const closeLrnBtn = document.getElementById('closeLrnModalBtn');
        const cancelLrnBtn = document.getElementById('cancelLrnModalBtn');

        function openLearnerModal(card) {
            if (!lrnModal || !card.dataset.modal) return;
            const data = JSON.parse(card.dataset.modal);

            const setTxt = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.textContent = val;
            };

            setTxt('lrnModalInitials', data.initials || 'LR');
            setTxt('lrnModalCode', `${data.code} • ${data.account_code}`);
            setTxt('lrnModalName', data.full_name);
            setTxt('lrnModalAssignedClass', data.assigned_class);
            setTxt('lrnModalComm', data.comm);
            setTxt('lrnModalLoc', data.location);
            setTxt('lrnModalContact', data.contact);
            setTxt('lrnModalEmail', data.email);
            setTxt('lrnModalGuardian', data.guardian);
            setTxt('lrnModalAttendance', data.attendance);
            setTxt('lrnModalRating', data.rating);
            setTxt('lrnModalRemarks', data.remarks);

            const mViewClassBtn = document.getElementById('lrnModalViewClassBtn');
            if (mViewClassBtn) {
                mViewClassBtn.href = data.primary_class_id > 0
                    ? `teacher_classes.php?class_id=${data.primary_class_id}#class-${data.primary_class_id}`
                    : 'teacher_classes.php';
            }

            const mStatus = document.getElementById('lrnModalStatus');
            if (mStatus) {
                const isActive = data.status === 'Active';
                mStatus.textContent = `• ${data.status}`;
                mStatus.className = `class-status-pill ${isActive ? 'status-active' : 'status-inactive'}`;
            }

            lrnModal.classList.add('open');
            lrnModal.setAttribute('aria-hidden', 'false');
        }

        function closeLearnerModal() {
            if (!lrnModal) return;
            lrnModal.classList.remove('open');
            lrnModal.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('.lrn-row-card').forEach(card => {
            card.addEventListener('click', () => openLearnerModal(card));
        });

        if (closeLrnBtn) closeLrnBtn.addEventListener('click', closeLearnerModal);
        if (cancelLrnBtn) cancelLrnBtn.addEventListener('click', closeLearnerModal);
        if (lrnModal) {
            lrnModal.addEventListener('click', (e) => {
                if (e.target === lrnModal) closeLearnerModal();
            });
        }
    }

    // ==================== 4. TEACHER SESSION REPORTS (teacher_reports.php) ====================
    const reportsGrid = document.getElementById('reportsCardsGrid');
    const makeModal = document.getElementById('makeReportModal');
    if (reportsGrid || makeModal) {
        const rptFilterPills = document.querySelectorAll('.rpt-filter-pill');
        const reportCards = document.querySelectorAll('.rpt-item-card');

        rptFilterPills.forEach(pill => {
            pill.addEventListener('click', () => {
                const target = pill.dataset.filter || 'ALL';
                rptFilterPills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');

                reportCards.forEach(card => {
                    const status = card.dataset.status || 'Submitted';
                    card.style.display = (target === 'ALL' || status === target) ? '' : 'none';
                });
            });
        });

        const openMakeBtn = document.getElementById('openMakeReportModalBtn');
        const closeMakeBtn = document.getElementById('closeMakeReportBtn');
        const cancelMakeBtn = document.getElementById('cancelMakeReportBtn');
        const schedSelect = document.getElementById('reportScheduleSelect');
        const enrolledBadge = document.getElementById('enrolledStudentsBadge');
        const studentsContainer = document.getElementById('reportStudentsContainer');
        const attLiveCounters = document.getElementById('attLiveCounters');
        const markAllBtn = document.getElementById('markAllPresentBtn');
        const photoInput = document.getElementById('sessionPhotoInput');
        const photoNameEl = document.getElementById('selectedPhotoName');
        const useSampleBtn = document.getElementById('useSamplePhotoBtn');
        const useSampleInput = document.getElementById('useSamplePhotoInput');
        const reportForm = document.getElementById('sessionReportForm');
        const reportActionInput = document.getElementById('reportActionInput');
        const summaryNotes = document.getElementById('sessionSummaryNotes');
        const saveDraftBtn = document.getElementById('saveDraftBtn');
        const submitReportBtn = document.getElementById('submitReportBtn');

        function updateAttendanceCounters() {
            if (!studentsContainer || !attLiveCounters) return;
            const selects = studentsContainer.querySelectorAll('.att-status-select');
            let present = 0, late = 0, absent = 0;

            selects.forEach(sel => {
                if (sel.value === 'Present') present++;
                else if (sel.value === 'Late') late++;
                else if (sel.value === 'Absent') absent++;
            });

            attLiveCounters.textContent = `Present: ${present} • Late: ${late} • Absent: ${absent}`;
        }

        function renderStudentsForSchedule() {
            if (!schedSelect || !studentsContainer) return;
            const opt = schedSelect.options[schedSelect.selectedIndex];
            const classId = opt ? (opt.dataset.classId || '0') : '0';
            const rosters = window.ROSTERS_BY_CLASS || {};
            const students = rosters[classId] || [];

            if (enrolledBadge) {
                enrolledBadge.textContent = `${students.length} Enrolled Students`;
            }

            studentsContainer.innerHTML = '';
            if (students.length === 0) {
                studentsContainer.innerHTML = '<div class="empty-panel-note">No learners enrolled in this class yet.</div>';
                updateAttendanceCounters();
                return;
            }

            students.forEach(stu => {
                const card = document.createElement('div');
                card.className = 'rpt-student-row-card';
                card.innerHTML = `
                    <div class="rpt-stu-top">
                        <div class="rpt-stu-info">
                            <strong>${stu.full_name}</strong>
                            <span>${stu.code} &bull; ${stu.hearing}</span>
                        </div>
                        <div class="rpt-stu-selects">
                            <select name="attendance[${stu.learner_id}]" class="rpt-stu-select att-status-select">
                                <option value="Present" selected>Present</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                            </select>
                            <select name="grade[${stu.learner_id}]" class="rpt-stu-select grade-select">
                                <option value="Satisfactory" selected>Satisfactory</option>
                                <option value="Needs Practice">Needs Practice</option>
                                <option value="Unsatisfactory">Unsatisfactory</option>
                            </select>
                        </div>
                    </div>
                    <input type="text" name="remarks[${stu.learner_id}]" class="rpt-stu-remark-input"
                           placeholder="Add individual observation note...">
                `;
                studentsContainer.appendChild(card);
            });

            studentsContainer.querySelectorAll('.att-status-select').forEach(sel => {
                sel.addEventListener('change', updateAttendanceCounters);
            });

            updateAttendanceCounters();
        }

        if (schedSelect) {
            schedSelect.addEventListener('change', renderStudentsForSchedule);
            renderStudentsForSchedule();
        }

        if (markAllBtn && studentsContainer) {
            markAllBtn.addEventListener('click', () => {
                studentsContainer.querySelectorAll('.att-status-select').forEach(sel => {
                    sel.value = 'Present';
                });
                updateAttendanceCounters();
            });
        }

        if (photoInput) {
            photoInput.addEventListener('change', () => {
                if (photoInput.files && photoInput.files.length > 0) {
                    if (photoNameEl) photoNameEl.textContent = photoInput.files[0].name;
                    if (useSampleInput) useSampleInput.value = '0';
                }
            });
        }

        if (useSampleBtn) {
            useSampleBtn.addEventListener('click', () => {
                if (useSampleInput) useSampleInput.value = '1';
                if (photoNameEl) photoNameEl.textContent = 'sample_session_proof.jpg (Sample Attached)';
            });
        }

        if (saveDraftBtn && reportForm) {
            saveDraftBtn.addEventListener('click', () => {
                if (reportActionInput) reportActionInput.value = 'draft';
                reportForm.submit();
            });
        }

        if (submitReportBtn && reportForm) {
            submitReportBtn.addEventListener('click', () => {
                const notesVal = summaryNotes ? summaryNotes.value.trim() : '';
                const hasFile = (photoInput && photoInput.files && photoInput.files.length > 0) ||
                                (useSampleInput && useSampleInput.value === '1');

                if (!notesVal) {
                    alert('Please enter Session Summary Notes before final submission (or click "Save as Draft").');
                    if (summaryNotes) summaryNotes.focus();
                    return;
                }
                if (!hasFile) {
                    alert('Session Photo Proof is required before submitting. Browse a photo or click "+ Use Sample Session Photo".');
                    return;
                }

                if (reportActionInput) reportActionInput.value = 'submitted';
                reportForm.submit();
            });
        }

        function openMakeModal() {
            if (!makeModal) return;
            makeModal.classList.add('open');
            makeModal.setAttribute('aria-hidden', 'false');
        }

        function closeMakeModal() {
            if (!makeModal) return;
            makeModal.classList.remove('open');
            makeModal.setAttribute('aria-hidden', 'true');
        }

        if (openMakeBtn) openMakeBtn.addEventListener('click', openMakeModal);
        if (closeMakeBtn) closeMakeBtn.addEventListener('click', closeMakeModal);
        if (cancelMakeBtn) cancelMakeBtn.addEventListener('click', closeMakeModal);
        if (makeModal) {
            makeModal.addEventListener('click', (e) => {
                if (e.target === makeModal) closeMakeModal();
            });
        }

        // View Report Details Modal
        const viewModal = document.getElementById('viewReportModal');
        const closeViewBtn = document.getElementById('closeViewRptBtn');
        const cancelViewBtn = document.getElementById('cancelViewRptBtn');

        function openViewReportModal(card) {
            if (!viewModal || !card.dataset.modal) return;
            const data = JSON.parse(card.dataset.modal);

            document.getElementById('viewRptCodes').textContent = `${data.report_code} • ${data.sched_code}`;
            document.getElementById('viewRptTitle').textContent = data.title;
            document.getElementById('viewRptMeta').textContent = data.meta_line;
            document.getElementById('viewRptNotes').textContent = data.notes;
            document.getElementById('viewRptAttendance').textContent = data.attendance;
            document.getElementById('viewRptPhotoStatus').textContent = data.has_photo ? 'Photo Attached' : 'No Photo Yet';
            document.getElementById('viewRptTimestamp').textContent = data.timestamp;

            const stEl = document.getElementById('viewRptStatus');
            if (stEl) {
                stEl.textContent = data.status;
                stEl.className = `class-status-pill ${data.status === 'Submitted' ? 'status-active' : 'status-rescheduled'}`;
            }

            viewModal.classList.add('open');
            viewModal.setAttribute('aria-hidden', 'false');
        }

        function closeViewReportModal() {
            if (!viewModal) return;
            viewModal.classList.remove('open');
            viewModal.setAttribute('aria-hidden', 'true');
        }

        reportCards.forEach(card => {
            card.addEventListener('click', () => openViewReportModal(card));
        });

        
        document.querySelectorAll('.btn-revise-report').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (schedSelect) {
                    schedSelect.value = btn.dataset.scheduleId;
                    renderStudentsForSchedule();
                }
                openMakeModal();
            });
        });

        if (closeViewBtn) closeViewBtn.addEventListener('click', closeViewReportModal);
        if (cancelViewBtn) cancelViewBtn.addEventListener('click', closeViewReportModal);
        if (viewModal) {
            viewModal.addEventListener('click', (e) => {
                if (e.target === viewModal) closeViewReportModal();
            });
        }
    }
});