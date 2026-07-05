<?php
require_once '../../auth/config.php';
require_once __DIR__ . '/../config/dashboard_menu_config.php';

if (!isLoggedIn()) {
    header('Location: ../../auth/login');
    exit();
}

try {
    $access_pdo = getDBConnection();
    if (!userHasAnyRole(['sales_manager'])) {
        header('Location: ../../auth/login');
        exit();
    }

    enforceCurrentUserDashboardMenuAccess('customer', ['sidebar']);

    if (shouldRequireBranchSelection($access_pdo)) {
        $active_branch_id = getCurrentBranchId();
        if ($active_branch_id === '' || !setCurrentBranchContext($access_pdo, $active_branch_id)) {
            header('Location: ../../auth/branch_selector_popup.php?redirect=' . rawurlencode('../app/sales_manager/page_customer_management.php'));
            exit();
        }
    }
} catch (Throwable $e) {
    error_log('Role access check failed in sales_manager/page_customer_management.php: ' . $e->getMessage());
    header('Location: ../../auth/login');
    exit();
}

$query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$target = '../sell/page_customer_management.php';
if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target);
exit();
