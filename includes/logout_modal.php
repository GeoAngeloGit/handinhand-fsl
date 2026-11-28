<?php
$logout_modal_initials = htmlspecialchars($initials ?? 'US', ENT_QUOTES, 'UTF-8');
$is_admin_portal = str_starts_with(basename($_SERVER['PHP_SELF'] ?? ''), 'admin_');
$logout_modal_eyebrow = $is_admin_portal ? 'ALBAY PROVINCE COORDINATOR' : 'ALBAY PROVINCE TEACHER';
$logout_modal_desc = $is_admin_portal
    ? 'Your active coordinator session will be locked.'
    : 'Your active educator session will be locked.';
?>
<style>
.hih-logout-backdrop {
    position: fixed;
    inset: 0;
    z-index: 120;
    background: rgba(26, 5, 13, 0.45);
    backdrop-filter: blur(2px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.hih-logout-backdrop.open {
    display: flex;
}
.hih-logout-card {
    width: 100%;
    max-width: 390px;
    background: #FFFFFF;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 24px 60px rgba(26, 5, 13, 0.28);
    display: flex;
    flex-direction: column;
    gap: 16px;
    text-align: center;
    font-family: 'Plus Jakarta Sans', sans-serif;
}
.hih-logout-avatar {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    background: #2E5D8A;
    color: #FFFFFF;
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.hih-logout-eyebrow {
    display: block;
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #8D2645;
}
.hih-logout-title {
    font-family: 'Playfair Display', serif;
    font-weight: 700;
    font-size: 22px;
    line-height: 1.2;
    color: #5B0F2B;
    margin: 4px 0 0;
}
.hih-logout-title em {
    font-style: italic;
    color: #8D2645;
}
.hih-logout-desc {
    font-size: 12px;
    color: #6D4C57;
    margin: 6px 0 0;
    line-height: 1.5;
}
.hih-logout-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 4px;
}
.hih-logout-stay {
    flex: 1;
    padding: 9px 12px;
    border-radius: 12px;
    border: 1px solid rgba(91, 15, 43, 0.16);
    background: #FFFFFF;
    color: #5B0F2B;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.15s ease;
}
.hih-logout-stay:hover {
    background: #F8F6F7;
}
.hih-logout-confirm {
    flex: 1;
    padding: 9px 12px;
    border-radius: 12px;
    background: #5B0F2B;
    color: #FFFFFF;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.15s ease;
}
.hih-logout-confirm:hover {
    background: #8D2645;
}
</style>

<div id="hihLogoutModal" class="hih-logout-backdrop" role="dialog" aria-modal="true" aria-labelledby="hihLogoutTitle" aria-hidden="true">
    <div class="hih-logout-card">
        <div class="hih-logout-avatar"><?php echo $logout_modal_initials; ?></div>
        <div>
            <span class="hih-logout-eyebrow"><?php echo htmlspecialchars($logout_modal_eyebrow, ENT_QUOTES, 'UTF-8'); ?></span>
            <h2 id="hihLogoutTitle" class="hih-logout-title">Sign Out of <em>Portal?</em></h2>
            <p class="hih-logout-desc"><?php echo htmlspecialchars($logout_modal_desc, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="hih-logout-actions">
            <button type="button" id="hihLogoutStayBtn" class="hih-logout-stay">Stay Signed In</button>
            <a href="actions/logout.php" id="hihLogoutConfirmLink" class="hih-logout-confirm">Log out</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('hihLogoutModal');
    const stayBtn = document.getElementById('hihLogoutStayBtn');
    const confirmLink = document.getElementById('hihLogoutConfirmLink');
    if (!modal) return;

    function openLogoutModal(href) {
        if (confirmLink && href) confirmLink.setAttribute('href', href);
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeLogoutModal() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a.logout-link, a[href$="logout.php"]');
        if (!link || link.id === 'hihLogoutConfirmLink') return;
        e.preventDefault();
        openLogoutModal(link.getAttribute('href') || 'actions/logout.php');
    });

    if (stayBtn) stayBtn.addEventListener('click', closeLogoutModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeLogoutModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeLogoutModal();
    });
});
</script>
