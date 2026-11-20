// Switch between Overview, Browse Classes, and Profile tabs
function switchTab(targetId) {
    document.querySelectorAll('.nav-item').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-target') === targetId);
    });

    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === targetId);
    });

    // Close mobile sidebar if open
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('open');
}

document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', () => {
        switchTab(btn.getAttribute('data-target'));
    });
});

// Modal Controls
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('open');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('open');
}

function openEnrollModal(classId, classTitle) {
    document.getElementById('modalClassId').value = classId;
    document.getElementById('modalClassTitle').textContent = classTitle;
    openModal('enrollModal');
}

// Mobile Hamburger Toggle
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
if (hamburgerBtn && sidebar) {
    hamburgerBtn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
    });
}