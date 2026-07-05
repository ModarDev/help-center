<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

// ตรวจสอบสิทธิ์แอดมิน
if (!userHasAnyRole(['admin', 'system_admin'])) {
    header("Location: ../../auth/login");
    exit();
}

enforceCurrentUserDashboardMenuAccess('sales', ['top_nav', 'sidebar']);
?>