<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';
require_once __DIR__ . '/../config/topbar_shell_ui.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!canCurrentUserAccessDashboard($access_pdo, '../app/admin/menuadmin')) {
        header("Location: ../../auth/login");
        exit();
    }

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/admin/menuadmin'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in menuadmin.php: ' . $e->getMessage());
    header("Location: ../../auth/login");
    exit();
}

$admin_display_name = 'admin';
$nav_logo_src = '';

$profile_user_id = (string)($_SESSION['user_id'] ?? '');
$profile_first_name = (string)($_SESSION['first_name'] ?? '');
$profile_last_name = (string)($_SESSION['last_name'] ?? '');
$profile_full_name = trim($profile_first_name . ' ' . $profile_last_name);
$profile_position = (string)($_SESSION['position'] ?? 'System Administrator');
$profile_role = (string)($_SESSION['user_role'] ?? 'admin');
$profile_image_src = '';

$default_logo_file = __DIR__ . '/../../assets/images/logo/logo.png';
if (is_file($default_logo_file)) {
    $nav_logo_src = '../../assets/images/logo/logo.png';
}

try {
    $pdo = getDBConnection();
    $has_profile_image_column = false;

    $column_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($column_stmt && $column_stmt->fetch()) {
        $has_profile_image_column = true;
    }

    if (!empty($_SESSION['user_id'])) {
        $select_fields = 'first_name, last_name, user_id, position, user_role';
        if ($has_profile_image_column) {
            $select_fields .= ', profile_image';
        }

        $stmt = $pdo->prepare("SELECT " . $select_fields . " FROM users WHERE user_id = :user_id LIMIT 1");
        $stmt->execute(['user_id' => (string)$_SESSION['user_id']]);
        $user_row = $stmt->fetch();

        if ($user_row) {
            $profile_user_id = (string)($user_row['user_id'] ?? $profile_user_id);
            $profile_first_name = (string)($user_row['first_name'] ?? $profile_first_name);
            $profile_last_name = (string)($user_row['last_name'] ?? $profile_last_name);

            $full_name = trim((string)($user_row['first_name'] ?? '') . ' ' . (string)($user_row['last_name'] ?? ''));
            if ($full_name !== '') {
                $admin_display_name = $full_name;
                $profile_full_name = $full_name;
            } elseif (!empty($user_row['user_id'])) {
                $admin_display_name = (string)$user_row['user_id'];
                if ($profile_full_name === '') {
                    $profile_full_name = (string)$user_row['user_id'];
                }
            }

            if (!empty($user_row['position'])) {
                $profile_position = (string)$user_row['position'];
            }
            if (!empty($user_row['user_role'])) {
                $profile_role = (string)$user_row['user_role'];
            }

            if ($has_profile_image_column && !empty($user_row['profile_image'])) {
                $candidate_path = ltrim((string)$user_row['profile_image'], '/');
                $candidate_file = __DIR__ . '/../../' . $candidate_path;
                if ($candidate_path !== '' && is_file($candidate_file)) {
                    $profile_image_src = '../../' . $candidate_path;
                }
            }
        }
    }

    $logo_stmt = $pdo->query("SELECT header_logo_path FROM company_settings WHERE id = 1 LIMIT 1");
    $logo_row = $logo_stmt ? $logo_stmt->fetch() : false;
    $header_logo_path = (string)($logo_row['header_logo_path'] ?? '');

    if ($header_logo_path !== '') {
        $header_logo_file = __DIR__ . '/../../' . ltrim($header_logo_path, '/');
        if (is_file($header_logo_file)) {
            $nav_logo_src = '../../' . ltrim($header_logo_path, '/');
        }
    }
} catch (PDOException $e) {
    error_log('Failed to load admin display name: ' . $e->getMessage());
}

if ($profile_full_name === '') {
    $profile_full_name = $admin_display_name;
}

$role_labels = getDashboardRoleLabels(isset($pdo) && $pdo instanceof PDO ? $pdo : null);
$profile_role_label = $role_labels[$profile_role] ?? ucfirst($profile_role !== '' ? $profile_role : 'User');
$active_branch_id_display = (string)($_SESSION['active_branch_id'] ?? '');
if ($active_branch_id_display === '') {
    $active_branch_id_display = '-';
}

$topbar_notification_items = consumeTopbarNotifications();

$menu_role_config = getDashboardMenuConfigByRole(
    $profile_role,
    isset($pdo) && $pdo instanceof PDO ? $pdo : null,
    [
        'home_href' => 'menuadmin.php',
        'page_title' => 'Admin Dashboard',
        'portal_label' => 'Office Plus ERP - Admin Portal'
    ]
);

$dashboard_page_title = (string)($menu_role_config['page_title'] ?? 'Dashboard');
$dashboard_portal_label = (string)($menu_role_config['portal_label'] ?? 'Office Plus ERP - Portal');

$top_nav_items_json = json_encode($menu_role_config['top_nav'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($top_nav_items_json === false) {
    $top_nav_items_json = '[]';
}

$sidebar_tiles_json = json_encode($menu_role_config['sidebar'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($sidebar_tiles_json === false) {
    $sidebar_tiles_json = '[]';
}

$sidebar_footer_buttons_json = json_encode($menu_role_config['footer'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($sidebar_footer_buttons_json === false) {
    $sidebar_footer_buttons_json = '[]';
}

$profile_avatar = 'A';
$avatar_source = $profile_first_name !== '' ? $profile_first_name : $profile_full_name;
if ($avatar_source !== '') {
    if (function_exists('mb_substr')) {
        $profile_avatar = mb_substr($avatar_source, 0, 1, 'UTF-8');
        if (function_exists('mb_strtoupper')) {
            $profile_avatar = mb_strtoupper($profile_avatar, 'UTF-8');
        }
    } else {
        $profile_avatar = strtoupper(substr($avatar_source, 0, 1));
    }
}

?> 

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Office Plus — Dashboard</title>
<style>
/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; width: 100%; overflow: hidden; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 15px;
    color: #1a1a2e;
    background: #ffffff;
}
a { text-decoration: none; color: inherit; }
button { font-family: inherit; cursor: pointer; border: none; background: none; }

/* ── CSS VARIABLES ── */
:root {
    --blue:       #0078d4;
    --blue-dark:  #005a9e;
    --blue-mid:   #2b88d8;
    --blue-light: #deecf9;
    --blue-hover: #c7e0f4;
    --white:      #ffffff;
    --surface:    #f5f8ff;
    --border:     #dde5f0;
    --text-main:  #0d1b3e;
    --text-sub:   #546e96;
    --text-muted: #8ea3c0;
    --shadow-sm:  0 1px 4px rgba(21,101,192,0.10), 0 0 1px rgba(21,101,192,0.06);
    --shadow-md:  0 4px 16px rgba(21,101,192,0.13), 0 1px 4px rgba(0,0,0,0.06);
    --transition: 0.18s cubic-bezier(0.4,0,0.2,1);
}

/* ── APP SHELL ── */
#app { display: flex; flex-direction: column; height: 100vh; width: 100vw; overflow: hidden; }

<?php echo renderSharedTopbarStyles(); ?>

/* ═══════════════════════════════════
   BODY ROW
═══════════════════════════════════ */
#body-row { display: flex; flex: 1; overflow: hidden; position: relative; }

/* ── SIDEBAR FLYOUT ── */
#sb-overlay {
    position: fixed; inset: 0;
    display: block;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    background: rgba(0,0,0,0.28);
    z-index: 150;
    transition: opacity 0.18s ease, visibility 0s linear 0.18s;
}
#sb-overlay.open {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transition: opacity 0.18s ease;
}

#sidebar {
    position: fixed; top: 54px; left: 0; bottom: 276px;
    width: 254px;
    background: #005a9e;
    display: flex; flex-direction: column;
    flex-shrink: 0;
    overflow-y: auto; overflow-x: hidden;
    z-index: 160;
    transform: translateX(-100%);
    transition: transform 0.22s cubic-bezier(0.4,0,0.2,1);
    box-shadow: 4px 0 20px rgba(0,0,0,0.25);
}
#sidebar.open { transform: translateX(0); }
#sidebar::-webkit-scrollbar { width: 4px; }
#sidebar::-webkit-scrollbar-track { background: transparent; }
#sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.25); }
.sb-header {
    height: 32px; min-height: 32px;
    display: flex; align-items: center; padding: 0 10px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    font-size: 10px; font-weight: 800; letter-spacing: 1px;
    text-transform: uppercase; color: rgba(255,255,255,0.55);
    flex-shrink: 0;
}
.sb-tile-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 4px; padding: 7px;
    flex: 1;
}
.sb-tile {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 9px 4px 8px; gap: 5px;
    cursor: pointer; min-height: 60px;
    background: var(--tile-color, #2b88d8);
    border: 2px solid transparent;
    transition: filter 0.15s ease, transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    position: relative; overflow: hidden;
}
.sb-tile:hover { filter: brightness(1.12); transform: scale(1.03); }
.sb-tile:active { transform: scale(0.97); }
.sb-tile.active { border-color: #ffffff; box-shadow: 0 0 0 2px rgba(255,255,255,0.35) inset; filter: brightness(1.08); }
.sb-tile-icon { width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; }
.sb-tile-icon img { width: 18px; height: 18px; display: block; object-fit: contain; }
.sb-tile-name { font-size: 12px; color: #ffffff; font-weight: 700; text-align: center; line-height: 1.2; }
.sb-footer {
    padding: 6px 7px;
    border-top: 1px solid rgba(255,255,255,0.12);
    display: flex; gap: 4px; flex-shrink: 0;
}
.sb-foot-btn {
    flex: 1; height: 28px; display: flex; align-items: center; justify-content: center;
    color: var(--foot-color, rgba(255,255,255,0.55)); cursor: pointer;
    transition: background var(--transition), color var(--transition);
    position: relative;
}
.sb-foot-btn:hover { background: rgba(255,255,255,0.12); color: #fff; }
.sb-foot-icon { width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; }
.sb-foot-icon img { width: 14px; height: 14px; display: block; object-fit: contain; }
.sb-foot-btn::after {
    content: attr(data-tip); position: absolute; bottom: 38px; left: 50%; transform: translateX(-50%);
    background: #005a9e; color: #fff; font-size: 11px; white-space: nowrap; padding: 3px 8px;
    pointer-events: none; opacity: 0; transition: opacity 0.12s; z-index: 400;
}
.sb-foot-btn:hover::after { opacity: 1; }

/* ── MAIN ── */
#main { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #ffffff; }

/* ── PAGE HEADER ── */
#page-header {
    background: #ffffff;
    border-bottom: 1.5px solid var(--border);
    padding: 0 28px;
    height: 58px; min-height: 58px;
    display: flex; align-items: center;
    flex-shrink: 0;
    gap: 14px;
}
#page-title { font-size: 22px; font-weight: 700; color: var(--text-main); letter-spacing: -0.3px; }
#page-sub { font-size: 14px; color: var(--text-sub); margin-left: 4px; }
#page-updated { margin-left: 12px; font-size: 13px; color: var(--text-muted); white-space: nowrap; }

/* ── SCROLL AREA ── */
#dash-scroll {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    padding: 24px 28px 32px;
    background: #f0f4fb;
}
#dash-scroll::-webkit-scrollbar { width: 6px; }
#dash-scroll::-webkit-scrollbar-track { background: transparent; }
#dash-scroll::-webkit-scrollbar-thumb { background: #bcd0ed; border-radius: 3px; }
#dash-scroll::-webkit-scrollbar-thumb:hover { background: var(--blue); }

/* ── GRID ROWS ── */
.d-row { display: flex; gap: 18px; margin-bottom: 20px; align-items: stretch; }

/* ═══════════════════════════════════
   CARDS
═══════════════════════════════════ */
.card {
    background: #ffffff;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
    transition: box-shadow var(--transition);
}
.card:hover { box-shadow: var(--shadow-md); }

.card-head {
    display: flex; align-items: center;
    padding: 13px 18px 11px;
    border-bottom: 1.5px solid var(--border);
    flex-shrink: 0; gap: 8px;
}
.card-head-icon {
    width: 26px; height: 26px; background: var(--blue-light);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.card-head-icon svg { width: 14px; height: 14px; fill: none; stroke: var(--blue); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.card-title { font-size: 15px; font-weight: 700; color: var(--text-main); flex: 1; }
.card-tag {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px; color: var(--blue); background: var(--blue-light);
    padding: 2px 8px;
}
.card-action-btn {
    font-size: 13px; color: var(--blue); padding: 4px 12px;
    border: 1px solid var(--blue); background: #fff;
    transition: background var(--transition);
}
.card-action-btn:hover { background: var(--blue-light); }

.card-body { padding: 16px 18px 18px; flex: 1; }

/* ── PROFILE CARD ── */
#card-profile { width: 280px; min-width: 280px; flex-shrink: 0; }

.prof-avatar {
    width: 56px; height: 56px; background: #0078d4;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 22px; font-weight: 700; flex-shrink: 0;
}
.prof-head { display: flex; align-items: center; gap: 14px; margin-bottom: 16px; }
.prof-name { font-size: 16px; font-weight: 700; color: var(--text-main); }
.prof-role { font-size: 13px; color: var(--blue); font-weight: 600; margin-top: 2px; }
.prof-status { display: flex; align-items: center; gap: 5px; font-size: 12px; color: #43a047; margin-top: 3px; }
.prof-status::before { content: ''; width: 7px; height: 7px; background: #43a047; border-radius: 50%; }

.prof-divider { height: 1px; background: var(--border); margin: 14px 0; }

.prof-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 7px 0; border-bottom: 1px solid #f0f4fb;
    font-size: 14px;
}
.prof-row:last-of-type { border-bottom: 0; }
.prof-lbl { color: var(--text-sub); }
.prof-val { color: var(--text-main); font-weight: 600; }

.prof-btns { display: flex; gap: 10px; margin-top: 16px; }
.pbtn {
    flex: 1; height: 36px;
    border: 1.5px solid var(--blue); background: #fff;
    color: var(--blue); font-size: 13.5px; font-weight: 600;
    display: flex; align-items: center; justify-content: center;
    transition: background var(--transition), color var(--transition);
}
.pbtn:hover { background: var(--blue); color: #fff; }
.pbtn.danger { border-color: #e53935; color: #e53935; }
.pbtn.danger:hover { background: #e53935; color: #fff; }

/* ── KPI CARDS ── */
.kpi-card { flex: 1; min-width: 0; }
.kpi-inner { padding: 18px 20px 16px; height: 100%; display: flex; flex-direction: column; }
.kpi-lbl { font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted); font-weight: 700; margin-bottom: 10px; }
.kpi-val { font-size: 32px; font-weight: 800; color: var(--text-main); line-height: 1; }
.kpi-val.accent { font-size: 20px; color: var(--blue); }
.kpi-val.ok { font-size: 20px; color: #43a047; }
.kpi-sub { font-size: 13px; color: var(--text-sub); margin-top: 6px; }
.kpi-track { height: 4px; background: #e3eaf5; margin-top: 14px; border-radius: 2px; overflow: hidden; }
.kpi-fill { height: 4px; background: var(--blue); border-radius: 2px; transition: width 0.6s ease; }

/* ── MODULE TILES ── */
#card-modules { width: 100%; }
#module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
}
.mod-tile {
    height: 80px; padding: 0 14px;
    border: 1.5px solid var(--border);
    background: #f8faff;
    display: flex; flex-direction: column;
    align-items: flex-start; justify-content: center; gap: 4px;
    cursor: pointer;
    transition: background var(--transition), border-color var(--transition), box-shadow var(--transition), transform var(--transition);
    position: relative; overflow: hidden;
}
.mod-tile::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: var(--blue);
    transform: scaleY(0); transform-origin: bottom;
    transition: transform var(--transition);
}
.mod-tile:hover { background: var(--blue-light); border-color: var(--blue-mid); box-shadow: 0 2px 8px rgba(21,101,192,0.13); }
.mod-tile:hover::before { transform: scaleY(1); }
.mod-tile.active { background: var(--blue); border-color: var(--blue-dark); box-shadow: 0 4px 12px rgba(21,101,192,0.25); }
.mod-tile.active::before { background: rgba(255,255,255,0.5); transform: scaleY(1); }
.mod-tile.active .mt-name { color: #fff; }
.mod-tile.active .mt-label { color: rgba(255,255,255,0.7); }
.mt-name { font-size: 15px; font-weight: 700; color: var(--text-main); line-height: 1.2; }
.mt-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }

/* ── SUBMENU CARD ── */
#card-submenu { width: 100%; display: none; }
#card-submenu.visible { display: flex; }

.sub-sec { border-bottom: 1.5px solid var(--surface); }
.sub-sec:last-child { border-bottom: 0; }
.sub-sec-hd {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 18px 10px;
}
.sub-sec-dot {
    width: 8px; height: 8px; background: var(--blue); flex-shrink: 0;
    border-radius: 2px;
}
.sub-sec-title {
    font-size: 13px; font-weight: 800;
    text-transform: uppercase; letter-spacing: 0.6px;
    color: var(--blue);
}
.sub-items { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 18px 16px; }

.sub-btn {
    height: 38px; padding: 0 18px;
    border: 1.5px solid var(--border);
    background: #f8faff;
    color: var(--text-main); font-size: 14px; font-weight: 500;
    display: flex; align-items: center; gap: 6px;
    white-space: nowrap;
    transition: background var(--transition), border-color var(--transition), color var(--transition), transform var(--transition), box-shadow var(--transition);
    position: relative; overflow: hidden;
}
.sub-btn::after {
    content: '';
    position: absolute; left: 0; top: 0; width: 0; height: 100%;
    background: rgba(21,101,192,0.06);
    transition: width 0.2s ease;
}
.sub-btn:hover { background: var(--blue-light); border-color: var(--blue); color: var(--blue-dark); transform: translateY(-1px); box-shadow: 0 2px 8px rgba(21,101,192,0.12); }
.sub-btn:hover::after { width: 100%; }
.sub-btn:active { transform: translateY(0); }
.sub-btn.off { color: var(--text-muted); border-color: #eaecf0; background: #fafbfd; cursor: default; }
.sub-btn.off:hover { background: #fafbfd; border-color: #eaecf0; color: var(--text-muted); transform: none; box-shadow: none; }

/* ── IFRAME PANEL ── */
#detail-panel { display: none; flex-direction: column; flex: 1; overflow: hidden; }
#detail-bar {
    height: 50px; min-height: 50px;
    background: #fff; border-bottom: 1.5px solid var(--border);
    display: flex; align-items: center; padding: 0 24px; gap: 14px;
    flex-shrink: 0;
}
#detail-label { font-size: 16px; font-weight: 700; color: var(--text-main); flex: 1; }
#btn-back-detail {
    height: 34px; padding: 0 18px;
    border: 1.5px solid var(--blue); background: #fff;
    color: var(--blue); font-size: 14px; font-weight: 600;
    display: flex; align-items: center; gap: 6px;
    transition: background var(--transition), color var(--transition);
}
#btn-back-detail:hover { background: var(--blue); color: #fff; }
#detail-iframe { flex: 1; width: 100%; border: 0; display: block; }

/* ── STATUS BAR ── */
#status-bar {
    height: 26px; min-height: 26px;
    background: #005a9e;
    display: flex; align-items: center; padding: 0 18px;
    flex-shrink: 0;
}
#status-bar .sb-time { font-size: 12px; color: rgba(255,255,255,0.65); }
#status-bar .sb-right { margin-left: auto; font-size: 12px; color: rgba(255,255,255,0.4); }

/* ── ANIMATIONS ── */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.anim-in    { animation: fadeInUp 0.28s cubic-bezier(0.4,0,0.2,1) both; }
.anim-fadein { animation: fadeIn 0.2s ease both; }

/* stagger for tiles */
.mod-tile:nth-child(1)  { animation-delay: .00s; }
.mod-tile:nth-child(2)  { animation-delay: .03s; }
.mod-tile:nth-child(3)  { animation-delay: .06s; }
.mod-tile:nth-child(4)  { animation-delay: .09s; }
.mod-tile:nth-child(5)  { animation-delay: .12s; }
.mod-tile:nth-child(6)  { animation-delay: .15s; }
.mod-tile:nth-child(7)  { animation-delay: .18s; }
.mod-tile:nth-child(8)  { animation-delay: .21s; }

/* ── BREADCRUMB ── */
#breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-size: 13px; color: var(--text-sub);
    margin-left: auto;
    padding-right: 4px;
}
#breadcrumb span { color: var(--text-muted); }
#breadcrumb a { color: var(--blue); font-weight: 600; }
#breadcrumb a:hover { text-decoration: underline; }

/* ── TOOLTIP ── */
[data-tip] { position: relative; }

/* ── PROFILE MODAL ── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(5, 24, 48, 0.52);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 450;
    padding: 18px;
}
.modal-overlay.open { display: flex; }

.modal-card {
    width: 100%;
    max-width: 420px;
    background: #ffffff;
    border: 1px solid #d1def1;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 14px 36px rgba(8, 36, 76, 0.3);
    animation: fadeInUp 0.2s ease both;
}

.modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #e6eef8;
}
.modal-title {
    font-size: 16px;
    font-weight: 700;
    color: #0d1b3e;
}
.modal-close {
    width: 30px;
    height: 30px;
    border-radius: 6px;
    color: #55759c;
    font-size: 20px;
    line-height: 1;
}
.modal-close:hover { background: #f0f5fc; color: #0d1b3e; }

.modal-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.modal-avatar-preview {
    width: 94px;
    height: 94px;
    border-radius: 50%;
    margin: 2px auto 6px;
    background: #2b88d8;
    color: #ffffff;
    font-size: 34px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.modal-avatar-preview.has-image {
    background: #d9e6f8;
    color: transparent;
}
.modal-avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.modal-file {
    width: 100%;
    border: 1px solid #c9d8ed;
    border-radius: 8px;
    padding: 9px 10px;
    background: #f8fbff;
    font-size: 13px;
}

.modal-help {
    font-size: 12px;
    color: #5b769b;
}

.modal-feedback {
    min-height: 19px;
    font-size: 12px;
    font-weight: 600;
}
.modal-feedback.error { color: #d32f2f; }
.modal-feedback.success { color: #2e7d32; }

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-btn {
    height: 36px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid #9cb7db;
    font-size: 13px;
    font-weight: 600;
    color: #20558e;
    background: #ffffff;
}
.modal-btn:hover { background: #f0f6ff; }
.modal-btn.primary {
    border-color: #0078d4;
    background: #0078d4;
    color: #ffffff;
}
.modal-btn.primary:hover { background: #0065b4; }
.modal-btn[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>
</head>
<body>
<div id="app">

<!-- ══════════════════════════════════
     TOP NAV BAR
══════════════════════════════════ -->
<?php echo renderSharedTopbar([
    'nav_logo_src' => $nav_logo_src,
    'brand_name' => 'APM GROUP',
    'brand_sub' => 'ERP System',
    'display_name' => $admin_display_name,
    'profile_image_src' => $profile_image_src,
    'profile_avatar' => $profile_avatar,
    'notifications' => $topbar_notification_items
]); ?>

<div id="sb-overlay"></div>

<!-- ══════════════════════════════════
     BODY
══════════════════════════════════ -->
<div id="body-row">

    <!-- SIDEBAR (flyout drawer) -->
    <div id="sidebar">
        <div class="sb-header">Modules</div>
        <div class="sb-tile-grid" id="sb-tile-grid"></div>
        <div class="sb-footer" id="sb-footer"></div>
    </div>

    <!-- MAIN CONTENT -->
    <div id="main">
        <div id="page-header">
            <span id="page-title"><?php echo htmlspecialchars($dashboard_page_title); ?></span>
            <span id="page-sub" class="page-sub"></span>
            <div id="breadcrumb">
                <a href="menuadmin.php">Dashboard</a>
                <span>/</span>
                <span id="js-bc">Overview</span>
            </div>
            <span id="page-updated">Updated <span id="js-updated"></span></span>
        </div>

        <!-- DASHBOARD -->
        <div id="dash-scroll">

            <!-- ROW 1: Profile + KPI -->
            <div class="d-row">

                <!-- Profile Card -->
                <div class="card anim-in" id="card-profile" style="animation-delay:0s;">
                    <div class="card-head">
                        <div class="card-head-icon">
                            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <span class="card-title">Profile</span>
                        <span class="card-tag"><?php echo htmlspecialchars($profile_role_label); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="prof-head">
                            <div class="prof-avatar<?php echo $profile_image_src !== '' ? ' has-image' : ''; ?>" id="profile-card-avatar">
                                <?php if ($profile_image_src !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($profile_image_src); ?>" alt="Profile picture">
                                <?php else: ?>
                                    <?php echo htmlspecialchars($profile_avatar); ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="prof-name"><?php echo htmlspecialchars($profile_full_name); ?></div>
                                <div class="prof-role"><?php echo htmlspecialchars($profile_position !== '' ? $profile_position : $profile_role_label); ?></div>
                                <div class="prof-status">Online</div>
                            </div>
                        </div>
                        <div class="prof-divider"></div>
                        <div class="prof-row"><span class="prof-lbl">User ID</span><span class="prof-val"><?php echo htmlspecialchars($profile_user_id !== '' ? $profile_user_id : '-'); ?></span></div>
                        <div class="prof-row"><span class="prof-lbl">Role</span><span class="prof-val"><?php echo htmlspecialchars($profile_role_label); ?></span></div>
                        <div class="prof-row"><span class="prof-lbl">Branch ID</span><span class="prof-val"><?php echo htmlspecialchars($active_branch_id_display); ?></span></div>
                        <div class="prof-row"><span class="prof-lbl">Login Date</span><span class="prof-val" id="login-date"></span></div>
                        <div class="prof-row"><span class="prof-lbl">Time</span><span class="prof-val" id="login-time"></span></div>
                        <div class="prof-btns">
                            <a href="../../auth/login" class="pbtn">Change Password</a>
                            <a href="../../auth/logout.php" class="pbtn danger">Logout</a>
                        </div>
                    </div>
                </div>

                <!-- KPI System -->
                <div class="card kpi-card anim-in" style="animation-delay:.05s;">
                    <div class="card-head">
                        <div class="card-head-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
                        <span class="card-title">System</span>
                    </div>
                    <div class="kpi-inner">
                        <div class="kpi-lbl">Status</div>
                        <div class="kpi-val ok">Online</div>
                        <div class="kpi-sub">Office Plus ERP</div>
                        <div class="kpi-track"><div class="kpi-fill" style="width:100%;background:#43a047;"></div></div>
                    </div>
                </div>

                <!-- KPI Modules -->
                <div class="card kpi-card anim-in" style="animation-delay:.08s;">
                    <div class="card-head">
                        <div class="card-head-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></div>
                        <span class="card-title">Modules</span>
                    </div>
                    <div class="kpi-inner">
                        <div class="kpi-lbl">Active</div>
                        <div class="kpi-val">8</div>
                        <div class="kpi-sub">Available modules</div>
                        <div class="kpi-track"><div class="kpi-fill" style="width:100%;"></div></div>
                    </div>
                </div>

                <!-- KPI Session -->
                <div class="card kpi-card anim-in" style="animation-delay:.11s;">
                    <div class="card-head">
                        <div class="card-head-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <span class="card-title">Session</span>
                    </div>
                    <div class="kpi-inner">
                        <div class="kpi-lbl">Status</div>
                        <div class="kpi-val ok">Active</div>
                        <div class="kpi-sub" id="kpi-date"></div>
                        <div class="kpi-track"><div class="kpi-fill" style="width:75%;"></div></div>
                    </div>
                </div>

                <!-- KPI Access -->
                <div class="card kpi-card anim-in" style="animation-delay:.14s;">
                    <div class="card-head">
                        <div class="card-head-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                        <span class="card-title">Access</span>
                    </div>
                    <div class="kpi-inner">
                        <div class="kpi-lbl">Level</div>
                        <div class="kpi-val accent">Full</div>
                        <div class="kpi-sub">All permissions</div>
                        <div class="kpi-track"><div class="kpi-fill" style="width:100%;"></div></div>
                    </div>
                </div>

            </div><!-- /ROW 1 -->

            <!-- ROW 2: Module Tiles (hidden — modules now in sidebar) -->
            <div class="d-row" id="row-modules-placeholder" style="display:none;">
                <div class="card" id="card-modules" style="width:100%;"></div>
            </div>

            <!-- ROW 3: Sub-Menu (hidden) -->
            <div class="d-row" id="row-submenu" style="display:none;">
                <div class="card" id="card-submenu" style="width:100%;">
                    <div class="card-head">
                        <div class="card-head-icon" id="submenu-icon">
                            <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/></svg>
                        </div>
                        <span class="card-title" id="submenu-title">Module Menu</span>
                        <button class="card-action-btn" id="btn-back-tiles">Back to Modules</button>
                    </div>
                    <div id="submenu-body"></div>
                </div>
            </div>

        </div><!-- /#dash-scroll -->

        <!-- IFRAME DETAIL -->
        <div id="detail-panel">
            <div id="detail-bar">
                <span id="detail-label">Detail</span>
                <button id="btn-back-detail">
                    <svg style="width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:2;vertical-align:middle;margin-right:5px;" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back
                </button>
            </div>
            <iframe id="detail-iframe" src="about:blank" name="detail-iframe"></iframe>
        </div>

    </div><!-- /#main -->
</div><!-- /#body-row -->

<!-- STATUS BAR -->
<div id="status-bar">
    <span class="sb-time" id="js-datetime"></span>
    <span class="sb-right"><?php echo htmlspecialchars($dashboard_portal_label); ?></span>
</div>

</div><!-- /#app -->

<div class="modal-overlay" id="profile-photo-modal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="profile-photo-modal-title">
        <div class="modal-head">
            <h3 class="modal-title" id="profile-photo-modal-title">เปลี่ยนรูปโปรไฟล์</h3>
            <button class="modal-close" id="btn-close-profile-modal" type="button" aria-label="Close">&times;</button>
        </div>
        <form class="modal-body" id="profile-photo-form" enctype="multipart/form-data">
            <div class="modal-avatar-preview<?php echo $profile_image_src !== '' ? ' has-image' : ''; ?>" id="profile-photo-preview">
                <?php if ($profile_image_src !== ''): ?>
                    <img src="<?php echo htmlspecialchars($profile_image_src); ?>" alt="Profile picture preview">
                <?php else: ?>
                    <?php echo htmlspecialchars($profile_avatar); ?>
                <?php endif; ?>
            </div>
            <input class="modal-file" type="file" id="profile-photo-input" name="profile_photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" required>
            <p class="modal-help">รองรับไฟล์ JPG, PNG, GIF, WebP ขนาดไม่เกิน 5MB</p>
            <p class="modal-feedback" id="profile-photo-feedback"></p>
            <div class="modal-actions">
                <button class="modal-btn" id="btn-cancel-profile-modal" type="button">ยกเลิก</button>
                <button class="modal-btn primary" id="btn-save-profile-photo" type="submit">บันทึกรูป</button>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/ajax.js"></script>
<?php echo renderTopbarNotificationScript(); ?>
<?php echo renderSharedTopbarScript(); ?>
<script>
(function () {
    'use strict';

    /* ── UTILS ── */
    function pad(n) { return String(n).padStart(2, '0'); }
    function fmtDT(d) {
        var mm = pad(d.getMonth()+1), dd = pad(d.getDate()), yyyy = d.getFullYear();
        var h = d.getHours(), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
        return mm + '/' + dd + '/' + yyyy + ' | ' + pad(h) + ':' + pad(d.getMinutes()) + ' ' + ap;
    }
    function fmtDate(d) { return pad(d.getMonth()+1) + '/' + pad(d.getDate()) + '/' + d.getFullYear(); }
    function fmtTime(d) {
        var h = d.getHours(), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
        return pad(h) + ':' + pad(d.getMinutes()) + ' ' + ap;
    }
    function fmtUpdated(d) {
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var h = d.getHours(), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + ' at ' + pad(h) + ':' + pad(d.getMinutes()) + ' ' + ap;
    }
    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function ajaxPostMultipart(url, formData) {
        if (!window.AppAjax || typeof window.AppAjax.postMultipart !== 'function') {
            return Promise.reject(new Error('ไม่พบระบบ AppAjax กลาง'));
        }
        return window.AppAjax.postMultipart(url, formData);
    }

    /* ── CLOCK ── */
    function refreshClock() {
        var now = new Date();
        document.getElementById('js-datetime').textContent = fmtDT(now);
        document.getElementById('js-updated').textContent = fmtUpdated(now);
        document.getElementById('login-date').textContent = fmtDate(now);
        document.getElementById('login-time').textContent = fmtTime(now);
        document.getElementById('kpi-date').textContent = fmtDate(now);
    }
    refreshClock();
    setInterval(refreshClock, 1000);

    /* menu config loaded from shared role-based PHP settings */
    var topNavItems = <?php echo $top_nav_items_json; ?>;
    var sidebarTiles = <?php echo $sidebar_tiles_json; ?>;
    var sidebarFooterButtons = <?php echo $sidebar_footer_buttons_json; ?>;

    /* ── ELEMENTS ── */
    var sbTileGrid     = document.getElementById('sb-tile-grid');
    var sbFooter       = document.getElementById('sb-footer');
    var tbNav          = document.getElementById('tb-nav');
    var dashScroll     = document.getElementById('dash-scroll');
    var detailPanel    = document.getElementById('detail-panel');
    var detailLabel    = document.getElementById('detail-label');
    var detailIframe   = document.getElementById('detail-iframe');
    var btnBackDetail  = document.getElementById('btn-back-detail');
    var pageSub        = document.getElementById('page-sub');
    var jsBc           = document.getElementById('js-bc');
    var menuChangeProfileImage = document.getElementById('menu-change-profile-image');
    var profilePhotoModal = document.getElementById('profile-photo-modal');
    var profilePhotoForm = document.getElementById('profile-photo-form');
    var profilePhotoInput = document.getElementById('profile-photo-input');
    var profilePhotoPreview = document.getElementById('profile-photo-preview');
    var profilePhotoFeedback = document.getElementById('profile-photo-feedback');
    var btnCloseProfileModal = document.getElementById('btn-close-profile-modal');
    var btnCancelProfileModal = document.getElementById('btn-cancel-profile-modal');
    var btnSaveProfilePhoto = document.getElementById('btn-save-profile-photo');
    var tbAvatarRing = document.getElementById('tb-avatar-ring');
    var profileCardAvatar = document.getElementById('profile-card-avatar');
    var defaultProfileInitial = <?php echo json_encode($profile_avatar); ?>;
    var currentProfileImageSrc = <?php echo json_encode($profile_image_src); ?>;
    var isUploadingProfileImage = false;

    function normalizeProfileImagePath(path) {
        var cleanPath = String(path || '').trim();
        if (!cleanPath) { return ''; }
        if (/^(?:https?:)?\/\//i.test(cleanPath) || cleanPath.indexOf('data:image/') === 0 || cleanPath.charAt(0) === '/') {
            return cleanPath;
        }
        if (cleanPath.indexOf('../../') === 0) {
            return cleanPath;
        }
        return '../../' + cleanPath.replace(/^\/+/, '');
    }

    function setAvatarContent(container, imageSrc) {
        if (!container) { return; }

        container.innerHTML = '';
        if (imageSrc) {
            container.classList.add('has-image');
            var img = document.createElement('img');
            img.src = imageSrc;
            img.alt = 'Profile picture';
            container.appendChild(img);
            return;
        }

        container.classList.remove('has-image');
        container.textContent = defaultProfileInitial;
    }

    function updateAvatarViews(imageSrc) {
        currentProfileImageSrc = imageSrc || '';
        setAvatarContent(tbAvatarRing, currentProfileImageSrc);
        setAvatarContent(profileCardAvatar, currentProfileImageSrc);
    }

    function setProfilePhotoFeedback(message, type) {
        if (!profilePhotoFeedback) { return; }
        profilePhotoFeedback.textContent = message || '';
        profilePhotoFeedback.classList.remove('error', 'success');
        if (type) {
            profilePhotoFeedback.classList.add(type);
        }
    }

    function openProfilePhotoModal() {
        if (!profilePhotoModal) { return; }
        profilePhotoModal.classList.add('open');
        profilePhotoModal.setAttribute('aria-hidden', 'false');
        if (profilePhotoInput) {
            profilePhotoInput.value = '';
        }
        setAvatarContent(profilePhotoPreview, currentProfileImageSrc);
        setProfilePhotoFeedback('', '');
    }

    function closeProfilePhotoModal() {
        if (!profilePhotoModal) { return; }
        profilePhotoModal.classList.remove('open');
        profilePhotoModal.setAttribute('aria-hidden', 'true');
        if (profilePhotoInput) {
            profilePhotoInput.value = '';
        }
        setProfilePhotoFeedback('', '');
    }

    if (menuChangeProfileImage) {
        menuChangeProfileImage.addEventListener('click', function () {
            if (typeof window.closeTopbarSettingsMenu === 'function') {
                window.closeTopbarSettingsMenu();
            }
            openProfilePhotoModal();
        });
    }

    if (btnCloseProfileModal) {
        btnCloseProfileModal.addEventListener('click', closeProfilePhotoModal);
    }
    if (btnCancelProfileModal) {
        btnCancelProfileModal.addEventListener('click', closeProfilePhotoModal);
    }
    if (profilePhotoModal) {
        profilePhotoModal.addEventListener('click', function (e) {
            if (e.target === profilePhotoModal) {
                closeProfilePhotoModal();
            }
        });
    }

    if (profilePhotoInput) {
        profilePhotoInput.addEventListener('change', function () {
            var file = profilePhotoInput.files && profilePhotoInput.files[0] ? profilePhotoInput.files[0] : null;
            if (!file) {
                setAvatarContent(profilePhotoPreview, currentProfileImageSrc);
                return;
            }
            if (file.type.indexOf('image/') !== 0) {
                profilePhotoInput.value = '';
                setAvatarContent(profilePhotoPreview, currentProfileImageSrc);
                setProfilePhotoFeedback('กรุณาเลือกไฟล์รูปภาพเท่านั้น', 'error');
                return;
            }

            var reader = new FileReader();
            reader.onload = function (ev) {
                setAvatarContent(profilePhotoPreview, String(ev.target && ev.target.result ? ev.target.result : ''));
                setProfilePhotoFeedback('', '');
            };
            reader.readAsDataURL(file);
        });
    }

    if (profilePhotoForm) {
        profilePhotoForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isUploadingProfileImage) { return; }

            var file = profilePhotoInput && profilePhotoInput.files && profilePhotoInput.files[0] ? profilePhotoInput.files[0] : null;
            if (!file) {
                setProfilePhotoFeedback('กรุณาเลือกไฟล์รูปก่อนบันทึก', 'error');
                return;
            }

            var formData = new FormData();
            formData.append('profile_photo', file);

            isUploadingProfileImage = true;
            btnSaveProfilePhoto.disabled = true;
            setProfilePhotoFeedback('กำลังอัปโหลดรูปโปรไฟล์...', '');

            ajaxPostMultipart('../../auth/upload_profile.php', formData)
                .then(function (data) {
                    if (!data || !data.success) {
                        throw new Error(data && data.message ? data.message : 'ไม่สามารถอัปโหลดรูปโปรไฟล์ได้');
                    }

                    var nextImageSrc = normalizeProfileImagePath(data.image_path || data.image_url || '');
                    if (!nextImageSrc) {
                        throw new Error('ไม่พบ path ของรูปโปรไฟล์ใหม่');
                    }

                    nextImageSrc += (nextImageSrc.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
                    updateAvatarViews(nextImageSrc);
                    setAvatarContent(profilePhotoPreview, nextImageSrc);
                    setProfilePhotoFeedback('บันทึกรูปโปรไฟล์สำเร็จ', 'success');

                    setTimeout(function () {
                        closeProfilePhotoModal();
                    }, 700);
                })
                .catch(function (error) {
                    setProfilePhotoFeedback(error && error.message ? error.message : 'เกิดข้อผิดพลาดขณะอัปโหลดรูป', 'error');
                })
                .finally(function () {
                    isUploadingProfileImage = false;
                    btnSaveProfilePhoto.disabled = false;
                });
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (profilePhotoModal && profilePhotoModal.classList.contains('open')) {
                closeProfilePhotoModal();
            }
        }
    });

    updateAvatarViews(currentProfileImageSrc);

    function clearActiveTiles() {
        document.querySelectorAll('.sb-tile').forEach(function (t) { t.classList.remove('active'); });
    }

    function setActiveTopNav(key) {
        document.querySelectorAll('#tb-nav .tb-nav-item').forEach(function (item) {
            item.classList.toggle('active', item.getAttribute('data-key') === key);
        });
    }

    function renderTopNav() {
        var navHtml = '';
        topNavItems.forEach(function (item, idx) {
            navHtml += '<a class="tb-nav-item' + (idx === 0 ? ' active' : '') + '" href="' + esc(item.href || '#') + '" data-key="' + esc(item.key || '') + '" data-label="' + esc(item.label || 'Detail') + '" data-sub="' + esc(item.sub || '') + '" data-href="' + esc(item.href || '#') + '">' + esc(item.label || 'Menu') + '</a>';
        });
        tbNav.innerHTML = navHtml;
    }

    function bindTopNavEvents() {
        document.querySelectorAll('#tb-nav .tb-nav-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                var key = this.getAttribute('data-key') || '';
                var label = this.getAttribute('data-label') || 'Detail';
                var sub = this.getAttribute('data-sub') || '';
                var href = this.getAttribute('data-href') || '#';

                setActiveTopNav(key);
                clearActiveTiles();
                pageSub.textContent = sub ? '— ' + sub : '';
                jsBc.textContent = label;

                if (href && href !== '#') {
                    showDetail(href, label);
                } else {
                    showTiles();
                }
            });
        });
    }

    function renderSidebarMenus() {
        var tileHtml = '';
        sidebarTiles.forEach(function (tile) {
            tileHtml += '<div class="sb-tile" data-module="' + esc(tile.key) + '" data-label="' + esc(tile.label || tile.key) + '" data-sub="' + esc(tile.sub || '') + '" data-href="' + esc(tile.href || '') + '" style="--tile-color:' + esc(tile.color || '#2b88d8') + ';">';
            tileHtml += '<div class="sb-tile-icon"><img src="' + esc(tile.iconSrc || '') + '" alt="" loading="lazy"></div>';
            tileHtml += '<span class="sb-tile-name">' + esc(tile.label || tile.key) + '</span>';
            tileHtml += '</div>';
        });
        sbTileGrid.innerHTML = tileHtml;

        var footHtml = '';
        sidebarFooterButtons.forEach(function (btn) {
            footHtml += '<a class="sb-foot-btn" href="' + esc(btn.href || '#') + '" data-tip="' + esc(btn.tip) + '" style="--foot-color:' + esc(btn.color || 'rgba(255,255,255,0.55)') + ';">';
            footHtml += '<div class="sb-foot-icon"><img src="' + esc(btn.iconSrc || '') + '" alt="" loading="lazy"></div>';
            footHtml += '</a>';
        });
        sbFooter.innerHTML = footHtml;
    }

    function bindSidebarTileEvents() {
        document.querySelectorAll('.sb-tile').forEach(function (tile) {
            tile.addEventListener('click', function () {
                var href = this.getAttribute('data-href');
                var label = this.getAttribute('data-label') || 'Detail';
                var sub = this.getAttribute('data-sub') || '';
                clearActiveTiles();
                setActiveTopNav('');
                this.classList.add('active');
                pageSub.textContent = sub ? '— ' + sub : '';
                jsBc.textContent = label;
                if (href && href !== '#') {
                    showDetail(href, label);
                } else {
                    showDash();
                }
                closeSidebar();
            });
            /* ripple */
            tile.addEventListener('mousedown', function (e) {
                var r = document.createElement('span');
                var rect = this.getBoundingClientRect();
                r.style.cssText = 'position:absolute;width:4px;height:4px;background:rgba(255,255,255,0.35);border-radius:50%;left:' +
                    (e.clientX - rect.left - 2) + 'px;top:' + (e.clientY - rect.top - 2) + 'px;pointer-events:none;transition:transform 0.45s ease,opacity 0.45s ease;';
                this.appendChild(r);
                requestAnimationFrame(function () {
                    r.style.transform = 'scale(28)'; r.style.opacity = '0';
                });
                setTimeout(function () { r.remove(); }, 500);
            });
        });
    }

    /* ── VIEWS ── */
    function showDash() {
        dashScroll.style.display = '';
        detailPanel.style.display = 'none';
        detailIframe.src = 'about:blank';
    }

    function showDetail(url, lbl) {
        detailLabel.textContent = lbl || 'Detail';
        detailIframe.src = url;
        dashScroll.style.display = 'none';
        detailPanel.style.display = 'flex';
    }

    /* ── MODULE TILES ── */
    function showTiles() {
        pageSub.textContent = '';
        jsBc.textContent = 'Overview';
        showDash();
        clearActiveTiles();
        setActiveTopNav('dashboard');
    }

    renderTopNav();
    bindTopNavEvents();
    renderSidebarMenus();
    bindSidebarTileEvents();

    /* ── SIDEBAR TOGGLE ── */
    var sidebar    = document.getElementById('sidebar');
    var sbOverlay  = document.getElementById('sb-overlay');
    var waffleBtn  = document.querySelector('.tb-waffle');

    function openSidebar() {
        sidebar.classList.add('open');
        sbOverlay.classList.add('open');
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        sbOverlay.classList.remove('open');
        clearActiveTiles();
    }

    waffleBtn.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) { closeSidebar(); }
        else { openSidebar(); }
    });
    sbOverlay.addEventListener('click', closeSidebar);
    btnBackDetail.addEventListener('click', function () {
        showTiles();
    });


    setTimeout(function () {
        document.querySelectorAll('.kpi-fill').forEach(function (el) {
            var w = el.style.width; el.style.width = '0';
            setTimeout(function () { el.style.width = w; }, 60);
        });
    }, 100);

})();
</script>
</body>
</html>

