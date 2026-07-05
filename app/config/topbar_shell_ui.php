<?php
require_once __DIR__ . '/topbar_notification_ui.php';

if (!function_exists('renderSharedTopbarStyles')) {
    function renderSharedTopbarStyles() {
        $styles = <<<'CSS'
/* ═══════════════════════════════════
   TOP NAV BAR
═══════════════════════════════════ */
#topbar {
    height: 54px; min-height: 54px;
    background: #005a9e;
    display: flex; align-items: stretch;
    flex-direction: row;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.16);
    z-index: 200;
}

.tb-waffle {
    width: 54px; display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.8); cursor: pointer; flex-shrink: 0;
    transition: background var(--transition);
}
.tb-waffle:hover { background: rgba(255,255,255,0.12); color: #fff; }
.tb-waffle svg { width: 20px; height: 20px; }

#tb-brand {
    display: flex; align-items: center; gap: 10px;
    padding: 0 20px 0 8px;
    border-right: 1px solid rgba(255,255,255,0.12);
    flex-shrink: 0;
}
.brand-logo {
    width: 30px; height: 30px; background: transparent;
    display: flex; align-items: center; justify-content: center;
    border-radius: 4px; overflow: hidden;
}
.brand-logo img {
    width: 100%; height: 100%; object-fit: contain; display: block;
}
.brand-logo-fallback {
    font-size: 13px; font-weight: 900; color: #fff; letter-spacing: -0.5px;
}
.brand-name  { font-size: 16px; font-weight: 700; color: #ffffff; white-space: nowrap; }
.brand-sep   { width: 1px; height: 16px; background: rgba(255,255,255,0.3); }
.brand-sub   { font-size: 13px; color: rgba(255,255,255,0.7); white-space: nowrap; }

#tb-nav {
    display: flex; align-items: stretch; padding: 0 8px;
    flex-shrink: 0;
}
.tb-nav-item {
    display: flex; align-items: center; padding: 0 14px;
    font-size: 14px; color: rgba(255,255,255,0.75);
    cursor: pointer; white-space: nowrap;
    border-bottom: 3px solid transparent;
    transition: color var(--transition), border-color var(--transition);
}
.tb-nav-item:hover { color: #fff; border-bottom-color: rgba(255,255,255,0.4); }
.tb-nav-item.active { color: #fff; border-bottom-color: #ffffff; font-weight: 600; }

#tb-right { margin-left: auto; display: flex; align-items: stretch; flex-shrink: 0; }
#tb-settings-wrap { position: relative; display: flex; align-items: stretch; }
.tb-icon {
    width: 46px; display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.75); cursor: pointer;
    transition: background var(--transition);
    position: relative;
}
.tb-icon:hover { background: rgba(255,255,255,0.1); color: #fff; }
.tb-icon svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.tb-badge {
    position: absolute; top: 10px; right: 8px;
    width: 8px; height: 8px; background: #ef5350;
    border-radius: 50%; border: 1.5px solid var(--blue-dark);
}

#tb-settings-menu {
    position: absolute;
    top: 46px;
    right: 0;
    min-width: 190px;
    background: #ffffff;
    border: 1px solid #cddaf0;
    box-shadow: 0 8px 20px rgba(6, 45, 92, 0.22);
    border-radius: 8px;
    padding: 6px;
    display: none;
    z-index: 420;
}
#tb-settings-wrap.open #tb-settings-menu { display: block; }

.settings-menu-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 10px;
    border-radius: 6px;
    color: #0d1b3e;
    font-size: 13px;
    font-weight: 600;
    border: 0;
    background: transparent;
    text-align: left;
}
.settings-menu-item:hover { background: #deecf9; }
.settings-menu-item svg {
    width: 15px;
    height: 15px;
    fill: none;
    stroke: #005a9e;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

#tb-avatar-wrap {
    display: flex; align-items: center; padding: 0 14px; gap: 10px;
    border-left: 1px solid rgba(255,255,255,0.1); cursor: pointer;
}
#tb-avatar-wrap:hover { background: rgba(255,255,255,0.08); }
.avatar-ring {
    width: 34px; height: 34px; background: #2b88d8;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 14px; font-weight: 700;
    border-radius: 50%;
}
.avatar-ring.has-image,
.prof-avatar.has-image {
    padding: 0;
    overflow: hidden;
    background: #d9e6f8;
    color: transparent;
}
.avatar-ring img,
.prof-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.avatar-name { font-size: 13px; color: rgba(255,255,255,0.85); font-weight: 600; }
CSS;

        return $styles . "\n" . renderTopbarNotificationStyles();
    }
}

if (!function_exists('renderSharedTopbar')) {
    function renderSharedTopbar(array $config = []) {
        $navLogoSrc = trim((string)($config['nav_logo_src'] ?? ''));
        $brandName = trim((string)($config['brand_name'] ?? 'APM GROUP'));
        $brandSub = trim((string)($config['brand_sub'] ?? 'ERP System'));
        $displayName = trim((string)($config['display_name'] ?? 'User'));
        $profileImageSrc = trim((string)($config['profile_image_src'] ?? ''));
        $profileAvatar = trim((string)($config['profile_avatar'] ?? 'U'));
        $notifications = $config['notifications'] ?? [];
        if (!is_array($notifications)) {
            $notifications = [];
        }

        ob_start();
        ?>
<div id="topbar">
    <div class="tb-waffle">
        <svg viewBox="0 0 20 20" fill="currentColor">
            <rect x="1" y="1" width="5" height="5" rx="1"/><rect x="8" y="1" width="5" height="5" rx="1"/><rect x="15" y="1" width="4" height="5" rx="1"/>
            <rect x="1" y="8" width="5" height="5" rx="1"/><rect x="8" y="8" width="5" height="5" rx="1"/><rect x="15" y="8" width="4" height="5" rx="1"/>
            <rect x="1" y="15" width="5" height="4" rx="1"/><rect x="8" y="15" width="5" height="4" rx="1"/><rect x="15" y="15" width="4" height="4" rx="1"/>
        </svg>
    </div>
    <div id="tb-brand">
        <div class="brand-logo">
            <?php if ($navLogoSrc !== ''): ?>
                <img src="<?php echo htmlspecialchars($navLogoSrc); ?>" alt="Office Plus Logo">
            <?php else: ?>
                <span class="brand-logo-fallback">OP</span>
            <?php endif; ?>
        </div>
        <span class="brand-name"><?php echo htmlspecialchars($brandName); ?></span>
        <span class="brand-sep"></span>
        <span class="brand-sub"><?php echo htmlspecialchars($brandSub); ?></span>
    </div>
    <div id="tb-nav"></div>
    <div id="tb-right">
        <div class="tb-icon">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="22" y2="22"/></svg>
        </div>
        <?php echo renderTopbarNotificationBell($notifications); ?>
        <div id="tb-settings-wrap">
            <button class="tb-icon" id="tb-settings-toggle" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="tb-settings-menu">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </button>
            <div id="tb-settings-menu" role="menu" aria-labelledby="tb-settings-toggle">
                <button class="settings-menu-item" id="menu-change-profile-image" type="button" role="menuitem">
                    <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h3l1.4-2h5.2L16 5h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span>เปลี่ยนรูปโปรไฟล์</span>
                </button>
            </div>
        </div>
        <div id="tb-avatar-wrap">
            <div class="avatar-ring<?php echo $profileImageSrc !== '' ? ' has-image' : ''; ?>" id="tb-avatar-ring">
                <?php if ($profileImageSrc !== ''): ?>
                    <img src="<?php echo htmlspecialchars($profileImageSrc); ?>" alt="Profile picture">
                <?php else: ?>
                    <?php echo htmlspecialchars($profileAvatar); ?>
                <?php endif; ?>
            </div>
            <span class="avatar-name"><?php echo htmlspecialchars($displayName); ?></span>
        </div>
    </div>
</div>
<?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('renderSharedTopbarScript')) {
    function renderSharedTopbarScript() {
        return <<<'SCRIPT'
<script>
(function () {
    'use strict';

    window.closeTopbarSettingsMenu = window.closeTopbarSettingsMenu || function () {};

    var tbSettingsWrap = document.getElementById('tb-settings-wrap');
    var tbSettingsToggle = document.getElementById('tb-settings-toggle');

    if (!tbSettingsWrap || !tbSettingsToggle) {
        return;
    }

    function closeSettingsMenu() {
        tbSettingsWrap.classList.remove('open');
        tbSettingsToggle.setAttribute('aria-expanded', 'false');
    }

    window.closeTopbarSettingsMenu = closeSettingsMenu;

    tbSettingsToggle.addEventListener('click', function (e) {
        e.stopPropagation();

        if (typeof window.closeTopbarNotificationMenu === 'function') {
            window.closeTopbarNotificationMenu();
        }

        var opening = !tbSettingsWrap.classList.contains('open');
        tbSettingsWrap.classList.toggle('open', opening);
        tbSettingsToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!tbSettingsWrap.contains(e.target)) {
            closeSettingsMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSettingsMenu();
        }
    });
})();
</script>
SCRIPT;
    }
}
