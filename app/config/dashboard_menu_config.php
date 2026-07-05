<?php

if (!function_exists('normalizeRoleKeyForMenuConfig')) {
    function normalizeRoleKeyForMenuConfig($roleKey) {
        return strtolower(str_replace([' ', '-'], '_', trim((string)$roleKey)));
    }
}

if (!function_exists('formatRoleLabelFromKey')) {
    function formatRoleLabelFromKey($roleKey) {
        $normalized = normalizeRoleKeyForMenuConfig($roleKey);
        if ($normalized === '') {
            return 'User';
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }
}

if (!function_exists('getDashboardRoleLabels')) {
    function getDashboardRoleLabels(PDO $pdo = null) {
        $fallback = [
            'system_admin' => 'System Admin',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'employee' => 'Employee',
            'sell_car' => 'Sell Car',
        ];

        if (!$pdo) {
            return $fallback;
        }

        try {
            $stmt = $pdo->query('SELECT role_key, role_name FROM roles WHERE is_active = 1 ORDER BY role_name ASC');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!$rows) {
                return $fallback;
            }

            $fromDb = [];
            foreach ($rows as $row) {
                $key = normalizeRoleKeyForMenuConfig($row['role_key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $name = trim((string)($row['role_name'] ?? ''));
                $fromDb[$key] = $name !== '' ? $name : formatRoleLabelFromKey($key);
            }

            if (!empty($fromDb)) {
                return array_replace($fallback, $fromDb);
            }
        } catch (Throwable $e) {
            // Fall back to static labels.
        }

        return $fallback;
    }
}

if (!function_exists('getRoleDashboardHomeHref')) {
    function getRoleDashboardHomeHref(PDO $pdo = null, $roleKey = '', $fallback = 'index.php') {
        if (!$pdo) {
            return (string)$fallback;
        }

        $normalizedRole = normalizeRoleKeyForMenuConfig($roleKey);
        if ($normalizedRole === '') {
            return (string)$fallback;
        }

        try {
            $stmt = $pdo->prepare('SELECT dashboard_path FROM roles WHERE role_key = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$normalizedRole]);
            $dashboardPath = trim((string)$stmt->fetchColumn());

            if ($dashboardPath === '') {
                return (string)$fallback;
            }

            $normalizedPath = str_replace('\\', '/', $dashboardPath);
            $normalizedPath = preg_replace('/[?#].*$/', '', $normalizedPath) ?? $normalizedPath;
            $normalizedPath = rtrim($normalizedPath, '/');
            $normalizedPath = preg_replace('/\.php$/i', '', $normalizedPath) ?? $normalizedPath;

            $base = basename($normalizedPath);
            if ($base === '' || $base === '.' || $base === '..') {
                return (string)$fallback;
            }

            return $base . '.php';
        } catch (Throwable $e) {
            return (string)$fallback;
        }
    }
}

if (!function_exists('getDashboardMenuRegistry')) {
    function getDashboardMenuRegistry() {
        return [
            'top_nav' => [
                'dashboard' => ['key' => 'dashboard', 'label' => 'Dashboard', 'sub' => 'Overview', 'href' => '#'],
                'sales' => ['key' => 'sales', 'label' => 'Sales', 'sub' => 'Orders & Invoices', 'href' => 'spa.php?module=sales'],
                'setup' => ['key' => 'setup', 'label' => 'Setup', 'sub' => 'System Setup', 'href' => 'company_settings.php?module=setup'],
                'users' => ['key' => 'users', 'label' => 'Users', 'sub' => 'User Management', 'href' => 'user.php?module=setup']
            ],
            'sidebar' => [
                'sales' => ['key' => 'sales', 'label' => 'Sales', 'sub' => 'Orders & Invoices', 'href' => 'spa.php?module=sales', 'color' => '#e53935', 'iconSrc' => '../../assets/images/icons/sidebar/sales.svg'],
                'branch' => ['key' => 'branch', 'label' => 'Branch Management', 'sub' => 'branch', 'href' => '../admin/page_branch_crud.php?module=branch', 'color' => '#fb8c00', 'iconSrc' => '../../assets/images/icons/sidebar/branch.png'],
                'inventory' => ['key' => 'inventory', 'label' => 'Items and Inventory', 'sub' => 'Items & Stock', 'href' => '#', 'color' => '#2e7d32', 'iconSrc' => '../../assets/images/icons/sidebar/inventory.svg'],
                'manufacturing' => ['key' => 'manufacturing', 'label' => 'Manufacturing', 'sub' => 'Work Orders', 'href' => '#', 'color' => '#00897b', 'iconSrc' => '../../assets/images/icons/sidebar/manufacturing.svg'],
                'fixed-assets' => ['key' => 'fixed-assets', 'label' => 'Fixed Assets', 'sub' => 'Assets & Depreciation', 'href' => '#', 'color' => '#1976d2', 'iconSrc' => '../../assets/images/icons/sidebar/fixed-assets.svg'],
                'dimensions' => ['key' => 'dimensions', 'label' => 'Dimensions', 'sub' => 'Budget & Analysis', 'href' => '#', 'color' => '#6a1b9a', 'iconSrc' => '../../assets/images/icons/sidebar/dimensions.svg'],
                'discord' => ['key' => 'discord', 'label' => 'Discord', 'sub' => 'Communication', 'href' => '../admin/page_setup_discord.php?module=discord', 'color' => '#7289da', 'iconSrc' => '../../assets/images/icons/sidebar/discord.png'],
                'setup' => ['key' => 'setup', 'label' => 'ตั้งค่า', 'sub' => 'System Setup', 'href' => 'company_settings.php?module=setup', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/setup.svg'],
                'user-setup' => ['key' => 'user-setup', 'label' => 'ตั้งผู้ใช้งานในระบบ', 'sub' => 'System Setup report', 'href' => 'edit_user.php?module=setup', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/user.png'],
                'customer' => ['key' => 'customer', 'label' => 'Customer Management ', 'sub' => 'Customer Management', 'href' => 'page_customer_management.php?module=customer', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/customer.png'],
                'customerlist' => ['key' => 'customerlist', 'label' => 'Customer List', 'sub' => 'Customer List', 'href' => 'page_customer_list.php?module=customer', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/customer-list.png'],
                'groupsetup' => ['key' => 'groupsetup', 'label' => 'Group Setup', 'sub' => 'Group Setup', 'href' => 'setup_group_sales.php?module=groupsetup', 'color' => '#85a5b4', 'iconSrc' => '../../assets/images/icons/sidebar/partners.png'],
                'groupsjoin' => ['key' => 'groupsjoin', 'label' => 'Join Group', 'sub' => 'Join by Invite', 'href' => 'join_group_sales.php?module=groupsjoin', 'color' => '#5b8aa6', 'iconSrc' => '../../assets/images/icons/sidebar/partners.png']
            ],
            
            'footer' => [
                'home' => ['tip' => 'Home', 'color' => '#b8d7ff', 'href' => 'index.php', 'iconSrc' => '../../assets/images/icons/sidebar/foot-home.svg'],
                'users' => ['tip' => 'Users', 'color' => '#c7f1d6', 'href' => 'user.php?module=setup', 'iconSrc' => '../../assets/images/icons/sidebar/foot-users.svg'],
                'reports' => ['tip' => 'Reports', 'color' => '#ffd7a8', 'href' => '#', 'iconSrc' => '../../assets/images/icons/sidebar/foot-reports.svg'],
                'logout' => ['tip' => 'Logout', 'color' => '#ffb8b8', 'href' => '../../auth/logout.php', 'iconSrc' => '../../assets/images/icons/sidebar/foot-logout.svg']
            ]
        ];
    }
}

if (!function_exists('getDashboardRoleMenuRules')) {
    function getDashboardRoleMenuRules() {
        return [
            'system_admin' => [
                'top_nav' => ['dashboard', 'setup', 'users'],
                'sidebar' => ['sales', 'branch', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'setup', 'user-setup'],
                'footer' => ['home', 'users', 'logout']
            ],
            'admin' => [
                'top_nav' => ['dashboard', 'setup', 'users'],
                'sidebar' => ['sales', 'branch', 'discord', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'setup', 'user-setup'],
                'footer' => ['home', 'users', 'logout']
            ],
            'manager' => [
                'top_nav' => ['dashboard', 'sales'],
                'sidebar' => ['sales', 'purchases', 'inventory'],
                'footer' => ['home', 'reports', 'logout']
            ],
            'employee' => [
                'top_nav' => ['dashboard'],
                'sidebar' => ['sales', 'customer', 'customerlist', 'groupsjoin', 'inventory'],
                'footer' => ['home', 'logout']
            ],
            'sell_car' => [
                'top_nav' => ['dashboard', 'sales'],
                'sidebar' => ['sales','customer','customerlist','groupsjoin'],
                'footer' => ['home', 'reports', 'logout']
            ],
            'sales_manager' => [
                'top_nav' => ['dashboard', 'sales'],
                'sidebar' => ['sales','customer','customerlist','groupsetup'],
                'footer' => ['home', 'reports', 'logout']
            ],
            'default' => [
                'top_nav' => ['dashboard'],
                'sidebar' => ['sales'],
                'footer' => ['home', 'logout']
            ]
        ];
    }
}

if (!function_exists('pickDashboardMenuItems')) {
    function pickDashboardMenuItems(array $registry, array $allowedKeys) {
        $items = [];

        foreach ($allowedKeys as $key) {
            if (isset($registry[$key])) {
                $items[] = $registry[$key];
            }
        }

        return $items;
    }
}

if (!function_exists('getDashboardMenuConfigByRole')) {
    function getDashboardMenuConfigByRole($roleKey, PDO $pdo = null, array $options = []) {
        $normalizedRole = normalizeRoleKeyForMenuConfig($roleKey);
        $labels = getDashboardRoleLabels($pdo);
        $registry = getDashboardMenuRegistry();
        $rulesMap = getDashboardRoleMenuRules();

        $rules = $rulesMap[$normalizedRole] ?? $rulesMap['default'];
        $roleLabel = $labels[$normalizedRole] ?? formatRoleLabelFromKey($normalizedRole);

        $homeHref = isset($options['home_href'])
            ? (string)$options['home_href']
            : getRoleDashboardHomeHref($pdo, $normalizedRole, 'index.php');

        $topNav = pickDashboardMenuItems($registry['top_nav'], (array)($rules['top_nav'] ?? []));
        $sidebar = pickDashboardMenuItems($registry['sidebar'], (array)($rules['sidebar'] ?? []));
        $footer = pickDashboardMenuItems($registry['footer'], (array)($rules['footer'] ?? []));

        foreach ($footer as &$item) {
            if (($item['tip'] ?? '') === 'Home') {
                $item['href'] = $homeHref;
            }
        }
        unset($item);

        $pageTitle = isset($options['page_title']) && trim((string)$options['page_title']) !== ''
            ? trim((string)$options['page_title'])
            : ($roleLabel . ' Dashboard');

        $portalLabel = isset($options['portal_label']) && trim((string)$options['portal_label']) !== ''
            ? trim((string)$options['portal_label'])
            : ('Office Plus ERP - ' . $roleLabel . ' Portal');

        return [
            'role_key' => $normalizedRole,
            'role_label' => $roleLabel,
            'page_title' => $pageTitle,
            'portal_label' => $portalLabel,
            'top_nav' => $topNav,
            'sidebar' => $sidebar,
            'footer' => $footer
        ];
    }
}
