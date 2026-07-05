<?php

if (!function_exists('renderTopbarNotificationStyles')) {
    function renderTopbarNotificationStyles() {
        return <<<'CSS'
#tb-notify-wrap { position: relative; display: flex; align-items: stretch; }

#tb-notify-menu {
    position: absolute;
    top: 46px;
    right: 0;
    width: 300px;
    background: #ffffff;
    border: 1px solid #cddaf0;
    box-shadow: 0 8px 20px rgba(6, 45, 92, 0.22);
    border-radius: 8px;
    display: none;
    z-index: 420;
    overflow: hidden;
}

#tb-notify-wrap.open #tb-notify-menu { display: block; }

.notify-menu-head {
    padding: 10px 12px;
    border-bottom: 1px solid #e5edf8;
    font-size: 13px;
    font-weight: 700;
    color: #16365f;
    background: #f7faff;
}

.notify-item {
    padding: 10px 12px;
    border-bottom: 1px solid #eef3fb;
}

.notify-item:last-child { border-bottom: 0; }
.notify-item.unread { background: #eef6ff; }

.notify-item-title {
    font-size: 13px;
    font-weight: 700;
    color: #0d1b3e;
}

.notify-item-msg {
    margin-top: 3px;
    font-size: 12px;
    color: #365a84;
}

.notify-item-time {
    margin-top: 5px;
    font-size: 11px;
    color: #6e89ad;
}

.notify-empty {
    padding: 16px 12px;
    font-size: 12px;
    color: #6e89ad;
    text-align: center;
}
CSS;
    }
}

if (!function_exists('renderTopbarNotificationBell')) {
    function renderTopbarNotificationBell(array $items) {
        ob_start();
        ?>
        <div id="tb-notify-wrap">
            <button class="tb-icon" id="tb-notify-toggle" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="tb-notify-menu" aria-label="Notifications">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if (!empty($items)): ?>
                    <span class="tb-badge" id="tb-notify-badge"></span>
                <?php endif; ?>
            </button>
            <div id="tb-notify-menu" role="menu" aria-labelledby="tb-notify-toggle">
                <div class="notify-menu-head">การแจ้งเตือน</div>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $notify_item): ?>
                        <?php
                        $notifyTitle = trim((string)($notify_item['title'] ?? ''));
                        $notifyMessage = trim((string)($notify_item['message'] ?? ''));
                        $notifyTime = (int)($notify_item['time'] ?? time());
                        $isUnread = (bool)($notify_item['is_unread'] ?? true);
                        ?>
                        <div class="notify-item<?php echo $isUnread ? ' unread' : ''; ?>" role="menuitem">
                            <div class="notify-item-title"><?php echo htmlspecialchars($notifyTitle !== '' ? $notifyTitle : 'แจ้งเตือน'); ?></div>
                            <div class="notify-item-msg"><?php echo htmlspecialchars($notifyMessage !== '' ? $notifyMessage : '-'); ?></div>
                            <div class="notify-item-time"><?php echo htmlspecialchars(date('d/m/Y H:i', $notifyTime)); ?> น.</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notify-empty">ยังไม่มีแจ้งเตือนใหม่</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('renderTopbarNotificationScript')) {
    function renderTopbarNotificationScript() {
        return <<<'SCRIPT'
<script>
(function () {
    'use strict';

    var tbNotifyWrap = document.getElementById('tb-notify-wrap');
    var tbNotifyToggle = document.getElementById('tb-notify-toggle');
    var tbNotifyBadge = document.getElementById('tb-notify-badge');

    if (!tbNotifyWrap || !tbNotifyToggle) {
        return;
    }

    function markNotificationsAsRead() {
        if (tbNotifyBadge) {
            tbNotifyBadge.style.display = 'none';
        }

        document.querySelectorAll('#tb-notify-menu .notify-item.unread').forEach(function (item) {
            item.classList.remove('unread');
        });
    }

    function closeNotifyMenu() {
        tbNotifyWrap.classList.remove('open');
        tbNotifyToggle.setAttribute('aria-expanded', 'false');
    }

    window.closeTopbarNotificationMenu = closeNotifyMenu;

    tbNotifyToggle.addEventListener('click', function (e) {
        e.stopPropagation();

        if (typeof window.closeTopbarSettingsMenu === 'function') {
            window.closeTopbarSettingsMenu();
        }

        var opening = !tbNotifyWrap.classList.contains('open');
        tbNotifyWrap.classList.toggle('open', opening);
        tbNotifyToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');

        if (opening) {
            markNotificationsAsRead();
        }
    });

    document.addEventListener('click', function (e) {
        if (!tbNotifyWrap.contains(e.target)) {
            closeNotifyMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeNotifyMenu();
        }
    });
})();
</script>
SCRIPT;
    }
}
