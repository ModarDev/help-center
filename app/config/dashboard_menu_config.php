<?php

if (!function_exists('normalizeRoleKeyForMenuConfig')) {
    function normalizeRoleKeyForMenuConfig($roleKey)
    {
        return strtolower(str_replace([' ', '-'], '_', trim((string)$roleKey)));
    }
}

if (!function_exists('formatRoleLabelFromKey')) {
    function formatRoleLabelFromKey($roleKey)
    {
        $normalized = normalizeRoleKeyForMenuConfig($roleKey);
        if ($normalized === '') {
            return 'User';
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }
}

if (!function_exists('isDashboardMenuIdentifierValid')) {
    function isDashboardMenuIdentifierValid($value)
    {
        return is_string($value) && (bool)preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', trim($value));
    }
}

if (!function_exists('dashboardMenuSafeSubstr')) {
    function dashboardMenuSafeSubstr($value, $length)
    {
        $text = (string)$value;
        $maxLength = (int)$length;
        if ($maxLength < 0) {
            $maxLength = 0;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength, 'UTF-8');
        }

        return substr($text, 0, $maxLength);
    }
}

if (!function_exists('isDashboardMenuHrefSafe')) {
    function isDashboardMenuHrefSafe($href)
    {
        $value = trim((string)$href);
        if ($value === '' || $value === '#') {
            return true;
        }

        if (strpos($value, "\0") !== false) {
            return false;
        }

        // Only relative app paths are allowed in menu config.
        if (preg_match('/^(?:https?:|javascript:|data:|vbscript:|file:|\/\/)/i', $value)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('isDashboardMenuColorSafe')) {
    function isDashboardMenuColorSafe($color)
    {
        $value = trim((string)$color);
        if ($value === '') {
            return true;
        }

        return (bool)preg_match('/^#[0-9a-fA-F]{3,8}$/', $value);
    }
}

if (!function_exists('sanitizeDashboardMenuItemForSection')) {
    function sanitizeDashboardMenuItemForSection($section, $key, array $item, $strict = false, &$error = '')
    {
        $error = '';
        $sectionKey = strtolower(trim((string)$section));
        if (!in_array($sectionKey, ['top_nav', 'sidebar', 'footer'], true)) {
            $error = 'Invalid menu section: ' . $sectionKey;
            return null;
        }

        $itemKey = strtolower(trim((string)$key));
        if (!isDashboardMenuIdentifierValid($itemKey)) {
            $error = 'Invalid menu key format: ' . $itemKey;
            return null;
        }

        $normalized = $item;
        $normalized['key'] = $itemKey;

        $labelKey = ($sectionKey === 'footer') ? 'tip' : 'label';
        $label = trim((string)($normalized[$labelKey] ?? ''));
        if ($label === '') {
            if ($strict) {
                $error = 'Missing required field ' . $labelKey . ' for menu key: ' . $itemKey;
                return null;
            }
            $label = $itemKey;
        }
        $normalized[$labelKey] = dashboardMenuSafeSubstr($label, 120);

        if (isset($normalized['sub'])) {
            $normalized['sub'] = dashboardMenuSafeSubstr(trim((string)$normalized['sub']), 180);
        }

        if (isset($normalized['href'])) {
            $href = trim((string)$normalized['href']);
            if (!isDashboardMenuHrefSafe($href)) {
                if ($strict) {
                    $error = 'Unsafe href for menu key: ' . $itemKey;
                    return null;
                }
                $href = '#';
            }
            $normalized['href'] = dashboardMenuSafeSubstr($href, 300);
        }

        if (isset($normalized['iconSrc'])) {
            $iconSrc = trim((string)$normalized['iconSrc']);
            if ($iconSrc !== '' && !isDashboardMenuHrefSafe($iconSrc)) {
                if ($strict) {
                    $error = 'Unsafe iconSrc for menu key: ' . $itemKey;
                    return null;
                }
                $iconSrc = '';
            }
            $normalized['iconSrc'] = dashboardMenuSafeSubstr($iconSrc, 255);
        }

        if (isset($normalized['color'])) {
            $color = trim((string)$normalized['color']);
            if (!isDashboardMenuColorSafe($color)) {
                if ($strict) {
                    $error = 'Unsafe color value for menu key: ' . $itemKey;
                    return null;
                }
                unset($normalized['color']);
            } else {
                $normalized['color'] = $color;
            }
        }

        return $normalized;
    }
}

if (!function_exists('filterDashboardRuleKeysByAllowed')) {
    function filterDashboardRuleKeysByAllowed(array $keys, array $allowedKeys)
    {
        $allowedLookup = array_fill_keys($allowedKeys, true);
        $result = [];

        foreach ($keys as $rawKey) {
            $menuKey = strtolower(trim((string)$rawKey));
            if ($menuKey === '') {
                continue;
            }
            if (!isDashboardMenuIdentifierValid($menuKey)) {
                continue;
            }
            if (!isset($allowedLookup[$menuKey])) {
                continue;
            }
            $result[] = $menuKey;
        }

        return array_values(array_unique($result));
    }
}

if (!function_exists('getDashboardMenuAdminConfigPath')) {
    function getDashboardMenuAdminConfigPath()
    {
        return __DIR__ . '/dashboard_menu_overrides.json';
    }
}

if (!function_exists('getDashboardMenuAdminOverrides')) {
    function getDashboardMenuAdminOverrides()
    {
        $path = getDashboardMenuAdminConfigPath();
        if (!is_file($path)) {
            return [];
        }

        $size = @filesize($path);
        if (is_int($size) && $size > (2 * 1024 * 1024)) {
            error_log('Dashboard menu override file too large and was ignored: ' . $path);
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true, 128);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('saveDashboardMenuAdminOverrides')) {
    function saveDashboardMenuAdminOverrides(array $payload, &$error = '')
    {
        $error = '';

        $registry = $payload['registry'] ?? null;
        $rules = $payload['rules'] ?? null;

        if (!is_array($registry) || !is_array($rules)) {
            $error = 'Payload must include registry and rules as arrays.';
            return false;
        }

        $normalizedRegistry = [
            'top_nav' => [],
            'sidebar' => [],
            'footer' => [],
        ];

        foreach (['top_nav', 'sidebar', 'footer'] as $section) {
            if (!isset($registry[$section]) || !is_array($registry[$section])) {
                $error = 'Invalid registry section: ' . $section;
                return false;
            }

            foreach ($registry[$section] as $key => $item) {
                if (!is_string($key) || $key === '' || !is_array($item)) {
                    $error = 'Invalid registry item in section: ' . $section;
                    return false;
                }

                $itemError = '';
                $normalizedItem = sanitizeDashboardMenuItemForSection($section, $key, $item, true, $itemError);
                if (!is_array($normalizedItem)) {
                    $error = $itemError !== '' ? $itemError : ('Invalid registry item in section: ' . $section);
                    return false;
                }

                $normalizedRegistry[$section][$normalizedItem['key']] = $normalizedItem;
            }
        }

        $allowedBySection = [
            'top_nav' => array_keys($normalizedRegistry['top_nav']),
            'sidebar' => array_keys($normalizedRegistry['sidebar']),
            'footer' => array_keys($normalizedRegistry['footer']),
        ];

        $normalizedRules = [];

        foreach ($rules as $role => $sections) {
            if (!is_string($role) || !is_array($sections)) {
                $error = 'Invalid rules structure.';
                return false;
            }

            $normalizedRole = normalizeRoleKeyForMenuConfig($role);
            if (!isDashboardMenuIdentifierValid($normalizedRole)) {
                $error = 'Invalid role key in rules: ' . $role;
                return false;
            }

            $normalizedRules[$normalizedRole] = [
                'top_nav' => [],
                'sidebar' => [],
                'footer' => [],
            ];

            foreach (['top_nav', 'sidebar', 'footer'] as $section) {
                if (!isset($sections[$section]) || !is_array($sections[$section])) {
                    $error = 'Invalid rules section for role ' . $role . ': ' . $section;
                    return false;
                }

                $rawKeys = array_values(array_filter((array)$sections[$section], 'is_string'));
                foreach ($rawKeys as $rawMenuKey) {
                    $normalizedMenuKey = strtolower(trim((string)$rawMenuKey));
                    if ($normalizedMenuKey === '' || !isDashboardMenuIdentifierValid($normalizedMenuKey) || !in_array($normalizedMenuKey, (array)$allowedBySection[$section], true)) {
                        $error = 'Rules for role ' . $role . ' contain unknown or unsafe keys in section ' . $section;
                        return false;
                    }
                }

                $filteredKeys = filterDashboardRuleKeysByAllowed((array)$sections[$section], (array)$allowedBySection[$section]);
                if (!is_array($filteredKeys)) {
                    $error = 'Rules for role ' . $role . ' contain unknown or unsafe keys in section ' . $section;
                    return false;
                }

                $normalizedRules[$normalizedRole][$section] = $filteredKeys;
            }
        }

        $json = json_encode([
            'schema_version' => 1,
            'updated_at' => gmdate('c'),
            'registry' => $normalizedRegistry,
            'rules' => $normalizedRules,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            $error = 'Unable to encode payload as JSON.';
            return false;
        }

        $path = getDashboardMenuAdminConfigPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            $error = 'Unable to create override directory: ' . $dir;
            return false;
        }

        $suffix = uniqid('', true);
        if (function_exists('random_bytes')) {
            try {
                $suffix = bin2hex(random_bytes(8));
            } catch (Throwable $e) {
                $suffix = uniqid('', true);
            }
        }
        $tmpPath = $path . '.tmp.' . $suffix;

        $written = @file_put_contents($tmpPath, $json, LOCK_EX);
        if ($written === false) {
            $error = 'Unable to write override temp file: ' . $tmpPath;
            return false;
        }

        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            $error = 'Unable to replace override file: ' . $path;
            return false;
        }

        @chmod($path, 0600);

        return true;
    }
}

if (!function_exists('getDashboardMenuExportPayload')) {
    function getDashboardMenuExportPayload($mode = 'effective')
    {
        $normalizedMode = strtolower(trim((string)$mode));
        if ($normalizedMode === 'default') {
            return [
                'schema_version' => 1,
                'exported_at' => gmdate('c'),
                'mode' => 'default',
                'registry' => getDashboardMenuBaseRegistry(),
                'rules' => getDashboardMenuBaseRoleRules(),
            ];
        }

        return [
            'schema_version' => 1,
            'exported_at' => gmdate('c'),
            'mode' => 'effective',
            'registry' => getDashboardMenuRegistry(),
            'rules' => getDashboardRoleMenuRules(),
        ];
    }
}

if (!function_exists('ensureDashboardMenuAuditLogTable')) {
    function ensureDashboardMenuAuditLogTable(PDO $pdo)
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS dashboard_menu_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(40) NOT NULL,
                actor_user_id VARCHAR(80) NOT NULL,
                actor_role VARCHAR(80) NOT NULL,
                status VARCHAR(20) NOT NULL,
                summary VARCHAR(255) NOT NULL,
                payload_json LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_dashboard_menu_audit_created_at (created_at),
                INDEX idx_dashboard_menu_audit_actor (actor_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('logDashboardMenuAuditEvent')) {
    function logDashboardMenuAuditEvent(PDO $pdo, $action, $status, $summary, $payload = null)
    {
        try {
            ensureDashboardMenuAuditLogTable($pdo);

            $jsonPayload = null;
            if (is_array($payload)) {
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($encoded)) {
                    $jsonPayload = $encoded;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO dashboard_menu_audit_logs (action, actor_user_id, actor_role, status, summary, payload_json) VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                substr(trim((string)$action), 0, 40),
                substr(trim((string)($_SESSION['user_id'] ?? 'unknown')), 0, 80),
                substr(trim((string)($_SESSION['user_role'] ?? 'unknown')), 0, 80),
                substr(trim((string)$status), 0, 20),
                substr(trim((string)$summary), 0, 255),
                $jsonPayload,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('Dashboard menu audit log failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getDashboardMenuAuditLogs')) {
    function getDashboardMenuAuditLogs(PDO $pdo, $limit = 25)
    {
        $safeLimit = (int)$limit;
        if ($safeLimit < 1) {
            $safeLimit = 1;
        }
        if ($safeLimit > 100) {
            $safeLimit = 100;
        }

        try {
            ensureDashboardMenuAuditLogTable($pdo);
            $stmt = $pdo->query(
                'SELECT id, action, actor_user_id, actor_role, status, summary, created_at FROM dashboard_menu_audit_logs ORDER BY id DESC LIMIT ' . $safeLimit
            );
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            error_log('Dashboard menu audit read failed: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('clearDashboardMenuAdminOverrides')) {
    function clearDashboardMenuAdminOverrides(&$error = '')
    {
        $error = '';
        $path = getDashboardMenuAdminConfigPath();

        if (!is_file($path)) {
            return true;
        }

        if (!@unlink($path)) {
            $error = 'Unable to remove override file: ' . $path;
            return false;
        }

        return true;
    }
}

if (!function_exists('getDashboardRoleLabels')) {
    function getDashboardRoleLabels(PDO $pdo = null)
    {
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
    function getRoleDashboardHomeHref(PDO $pdo = null, $roleKey = '', $fallback = 'index.php')
    {
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

if (!function_exists('getDashboardMenuBaseRegistry')) {
    function getDashboardMenuBaseRegistry()
    {
        return [
            'top_nav' => [
                'dashboard' => ['key' => 'dashboard', 'label' => 'Dashboard', 'sub' => 'Overview', 'href' => '#'],
                'sales' => ['key' => 'sales', 'label' => 'Sales', 'sub' => 'Orders & Invoices', 'href' => 'spa.php?module=sales'],
                'setup' => ['key' => 'setup', 'label' => 'Setup', 'sub' => 'System Setup', 'href' => 'company_settings.php?module=setup'],
                'menu-config' => ['key' => 'menu-config', 'label' => 'Menu Config', 'sub' => 'Role Menu Setup', 'href' => 'page_dashboard_menu_config.php?module=menuconfig'],
                'users' => ['key' => 'users', 'label' => 'Users', 'sub' => 'User Management', 'href' => 'user.php?module=setup'],
            ],
            'sidebar' => [
                'sales' => ['key' => 'sales', 'label' => 'Sales', 'sub' => 'Orders & Invoices', 'href' => 'spa.php?module=sales', 'color' => '#e53935', 'iconSrc' => '../../assets/images/icons/sidebar/sales.svg'],
                'purchases' => ['key' => 'purchases', 'label' => 'Purchases', 'sub' => 'Suppliers & Bills', 'href' => '#', 'color' => '#7b1fa2', 'iconSrc' => '../../assets/images/icons/sidebar/inventory.svg'],
                'branch' => ['key' => 'branch', 'label' => 'Branch Management', 'sub' => 'branch', 'href' => '../admin/page_branch_crud.php?module=branch', 'color' => '#fb8c00', 'iconSrc' => '../../assets/images/icons/sidebar/branch.png'],
                'inventory' => ['key' => 'inventory', 'label' => 'Items and Inventory', 'sub' => 'Items & Stock', 'href' => '#', 'color' => '#2e7d32', 'iconSrc' => '../../assets/images/icons/sidebar/inventory.svg'],
                'manufacturing' => ['key' => 'manufacturing', 'label' => 'Manufacturing', 'sub' => 'Work Orders', 'href' => '#', 'color' => '#00897b', 'iconSrc' => '../../assets/images/icons/sidebar/manufacturing.svg'],
                'fixed-assets' => ['key' => 'fixed-assets', 'label' => 'Fixed Assets', 'sub' => 'Assets & Depreciation', 'href' => '#', 'color' => '#1976d2', 'iconSrc' => '../../assets/images/icons/sidebar/fixed-assets.svg'],
                'dimensions' => ['key' => 'dimensions', 'label' => 'Dimensions', 'sub' => 'Budget & Analysis', 'href' => '#', 'color' => '#6a1b9a', 'iconSrc' => '../../assets/images/icons/sidebar/dimensions.svg'],
                'discord' => ['key' => 'discord', 'label' => 'Discord', 'sub' => 'Communication', 'href' => '../admin/page_setup_discord.php?module=discord', 'color' => '#7289da', 'iconSrc' => '../../assets/images/icons/sidebar/discord.png'],
                'menu-config' => ['key' => 'menu-config', 'label' => 'Menu Config', 'sub' => 'Role Menu Setup', 'href' => 'page_dashboard_menu_config.php?module=menuconfig', 'color' => '#3949ab', 'iconSrc' => '../../assets/images/icons/sidebar/setup.svg'],
                'setup' => ['key' => 'setup', 'label' => 'ตั้งค่า', 'sub' => 'System Setup', 'href' => 'company_settings.php?module=setup', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/setup.svg'],
                'user-setup' => ['key' => 'user-setup', 'label' => 'ตั้งผู้ใช้งานในระบบ', 'sub' => 'System Setup report', 'href' => 'edit_user.php?module=setup', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/user.png'],
                'customer' => ['key' => 'customer', 'label' => 'Customer Management', 'sub' => 'Customer Management', 'href' => 'page_customer_management.php?module=customer', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/customer.png'],
                'customerlist' => ['key' => 'customerlist', 'label' => 'Customer List', 'sub' => 'Customer List', 'href' => 'page_customer_list.php?module=customer', 'color' => '#455a64', 'iconSrc' => '../../assets/images/icons/sidebar/customer-list.png'],
                'groupsetup' => ['key' => 'groupsetup', 'label' => 'Group Setup', 'sub' => 'Group Setup', 'href' => 'setup_group_sales.php?module=groupsetup', 'color' => '#85a5b4', 'iconSrc' => '../../assets/images/icons/sidebar/partners.png'],
                'groupsjoin' => ['key' => 'groupsjoin', 'label' => 'Join Group', 'sub' => 'Join by Invite', 'href' => 'join_group_sales.php?module=groupsjoin', 'color' => '#5b8aa6', 'iconSrc' => '../../assets/images/icons/sidebar/partners.png'],
            ],
            'footer' => [
                'home' => ['tip' => 'Home', 'color' => '#b8d7ff', 'href' => 'index.php', 'iconSrc' => '../../assets/images/icons/sidebar/foot-home.svg'],
                'users' => ['tip' => 'Users', 'color' => '#c7f1d6', 'href' => 'user.php?module=setup', 'iconSrc' => '../../assets/images/icons/sidebar/foot-users.svg'],
                'reports' => ['tip' => 'Reports', 'color' => '#ffd7a8', 'href' => '#', 'iconSrc' => '../../assets/images/icons/sidebar/foot-reports.svg'],
                'logout' => ['tip' => 'Logout', 'color' => '#ffb8b8', 'href' => '../../auth/logout.php', 'iconSrc' => '../../assets/images/icons/sidebar/foot-logout.svg'],
            ],
        ];
    }
}

if (!function_exists('getDashboardMenuRegistry')) {
    function getDashboardMenuRegistry()
    {
        $registry = getDashboardMenuBaseRegistry();
        $overrides = getDashboardMenuAdminOverrides();

        if (!isset($overrides['registry']) || !is_array($overrides['registry'])) {
            return $registry;
        }

        foreach (['top_nav', 'sidebar', 'footer'] as $section) {
            if (!isset($overrides['registry'][$section]) || !is_array($overrides['registry'][$section])) {
                continue;
            }

            foreach ($overrides['registry'][$section] as $key => $item) {
                if (!is_string($key) || trim($key) === '' || !is_array($item)) {
                    continue;
                }

                $baseItem = [];
                if (isset($registry[$section][$key]) && is_array($registry[$section][$key])) {
                    $baseItem = $registry[$section][$key];
                }

                $candidate = array_merge($baseItem, $item);

                $itemError = '';
                $normalizedItem = sanitizeDashboardMenuItemForSection($section, $key, $candidate, false, $itemError);
                if (!is_array($normalizedItem)) {
                    continue;
                }

                $registry[$section][$normalizedItem['key']] = $normalizedItem;
            }
        }

        return $registry;
    }
}

if (!function_exists('getDashboardMenuBaseRoleRules')) {
    function getDashboardMenuBaseRoleRules()
    {
        return [
            'system_admin' => [
                'top_nav' => ['dashboard', 'setup', 'menu-config', 'users'],
                'sidebar' => ['sales', 'branch', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'setup', 'menu-config', 'user-setup'],
                'footer' => ['home', 'users', 'logout'],
            ],
            'admin' => [
                'top_nav' => ['dashboard', 'setup', 'menu-config', 'users'],
                'sidebar' => ['sales', 'branch', 'discord', 'inventory', 'manufacturing', 'fixed-assets', 'dimensions', 'setup', 'menu-config', 'user-setup'],
                'footer' => ['home', 'users', 'logout'],
            ],
            'manager' => [
                'top_nav' => ['dashboard', 'sales'],
                'sidebar' => ['sales', 'purchases', 'inventory'],
                'footer' => ['home', 'reports', 'logout'],
            ],
            'employee' => [
                'top_nav' => ['dashboard'],
                'sidebar' => ['sales', 'customer', 'customerlist', 'groupsjoin', 'inventory'],
                'footer' => ['home', 'logout'],
            ],
            'sell_car' => [
                'top_nav' => ['dashboard', 'sales'],
                'sidebar' => ['sales', 'customer', 'customerlist', 'groupsjoin'],
                'footer' => ['home', 'reports', 'logout'],
            ],
            'sales_manager' => [
                'top_nav' => ['dashboard', 'sales'],
                'sidebar' => ['sales', 'customer', 'customerlist', 'groupsetup'],
                'footer' => ['home', 'reports', 'logout'],
            ],
            'default' => [
                'top_nav' => ['dashboard'],
                'sidebar' => ['sales'],
                'footer' => ['home', 'logout'],
            ],
        ];
    }
}

if (!function_exists('getDashboardRoleMenuRules')) {
    function getDashboardRoleMenuRules()
    {
        $rules = getDashboardMenuBaseRoleRules();
        $overrides = getDashboardMenuAdminOverrides();
        $registry = getDashboardMenuRegistry();
        $allowedBySection = [
            'top_nav' => array_keys((array)($registry['top_nav'] ?? [])),
            'sidebar' => array_keys((array)($registry['sidebar'] ?? [])),
            'footer' => array_keys((array)($registry['footer'] ?? [])),
        ];

        if (!isset($overrides['rules']) || !is_array($overrides['rules'])) {
            return $rules;
        }

        foreach ($overrides['rules'] as $role => $sections) {
            if (!is_string($role) || trim($role) === '' || !is_array($sections)) {
                continue;
            }

            $normalizedRole = normalizeRoleKeyForMenuConfig($role);
            if (!isDashboardMenuIdentifierValid($normalizedRole)) {
                continue;
            }

            $rules[$normalizedRole] = [
                'top_nav' => filterDashboardRuleKeysByAllowed((array)($sections['top_nav'] ?? []), $allowedBySection['top_nav']),
                'sidebar' => filterDashboardRuleKeysByAllowed((array)($sections['sidebar'] ?? []), $allowedBySection['sidebar']),
                'footer' => filterDashboardRuleKeysByAllowed((array)($sections['footer'] ?? []), $allowedBySection['footer']),
            ];
        }

        return $rules;
    }
}

if (!function_exists('getDashboardAllowedMenuKeysByRole')) {
    function getDashboardAllowedMenuKeysByRole($roleKey, $section = 'sidebar')
    {
        $normalizedRole = normalizeRoleKeyForMenuConfig($roleKey);
        $normalizedSection = strtolower(trim((string)$section));
        if (!in_array($normalizedSection, ['top_nav', 'sidebar', 'footer'], true)) {
            return [];
        }

        $rulesMap = getDashboardRoleMenuRules();
        $rules = $rulesMap[$normalizedRole] ?? $rulesMap['default'] ?? [];
        $keys = (array)($rules[$normalizedSection] ?? []);

        $safeKeys = [];
        foreach ($keys as $key) {
            if (is_string($key) && trim($key) !== '') {
                $safeKeys[] = trim($key);
            }
        }

        return array_values(array_unique($safeKeys));
    }
}

if (!function_exists('isDashboardMenuKeyAllowedForRole')) {
    function isDashboardMenuKeyAllowedForRole($roleKey, $section, $menuKey)
    {
        $allowed = getDashboardAllowedMenuKeysByRole($roleKey, $section);
        return in_array(trim((string)$menuKey), $allowed, true);
    }
}

if (!function_exists('canRoleAccessAnyDashboardMenuSection')) {
    function canRoleAccessAnyDashboardMenuSection($roleKey, $menuKey, array $sections = ['sidebar', 'top_nav', 'footer'])
    {
        $normalizedRole = normalizeRoleKeyForMenuConfig($roleKey);
        $normalizedMenuKey = trim((string)$menuKey);
        if ($normalizedRole === '' || $normalizedMenuKey === '') {
            return false;
        }

        foreach ($sections as $section) {
            $sectionKey = strtolower(trim((string)$section));
            if (!in_array($sectionKey, ['top_nav', 'sidebar', 'footer'], true)) {
                continue;
            }

            if (isDashboardMenuKeyAllowedForRole($normalizedRole, $sectionKey, $normalizedMenuKey)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('currentUserCanAccessDashboardMenu')) {
    function currentUserCanAccessDashboardMenu($menuKey, array $sections = ['sidebar', 'top_nav', 'footer'])
    {
        $currentRole = (string)($_SESSION['user_role'] ?? '');
        return canRoleAccessAnyDashboardMenuSection($currentRole, $menuKey, $sections);
    }
}

if (!function_exists('currentUserCanAccessAnyDashboardMenu')) {
    function currentUserCanAccessAnyDashboardMenu(array $menuKeys, array $sections = ['sidebar', 'top_nav', 'footer'])
    {
        foreach ($menuKeys as $menuKey) {
            if (currentUserCanAccessDashboardMenu($menuKey, $sections)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('ensureDashboardMenuDeniedAccessTable')) {
    function ensureDashboardMenuDeniedAccessTable(PDO $pdo)
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS dashboard_menu_denied_access_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_user_id VARCHAR(80) NOT NULL,
                actor_role VARCHAR(80) NOT NULL,
                menu_keys VARCHAR(255) NOT NULL,
                sections VARCHAR(120) NOT NULL,
                request_uri VARCHAR(255) NOT NULL,
                ip_address VARCHAR(64) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_dashboard_menu_denied_created_at (created_at),
                INDEX idx_dashboard_menu_denied_actor (actor_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('getDashboardMenuRequestIpAddress')) {
    function getDashboardMenuRequestIpAddress()
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $remoteAddr !== '' ? substr($remoteAddr, 0, 64) : 'unknown';
    }
}

if (!function_exists('logDashboardMenuDeniedAccess')) {
    function logDashboardMenuDeniedAccess(PDO $pdo, array $menuKeys, array $sections)
    {
        try {
            ensureDashboardMenuDeniedAccessTable($pdo);

            $menuKeysCsv = substr(implode(',', array_slice(array_values(array_unique(array_map(function ($v) {
                return trim((string)$v);
            }, $menuKeys))), 0, 10)), 0, 255);
            $sectionsCsv = substr(implode(',', array_slice(array_values(array_unique(array_map(function ($v) {
                return strtolower(trim((string)$v));
            }, $sections))), 0, 5)), 0, 120);

            $stmt = $pdo->prepare(
                'INSERT INTO dashboard_menu_denied_access_logs (actor_user_id, actor_role, menu_keys, sections, request_uri, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                substr(trim((string)($_SESSION['user_id'] ?? 'unknown')), 0, 80),
                substr(trim((string)($_SESSION['user_role'] ?? 'unknown')), 0, 80),
                $menuKeysCsv,
                $sectionsCsv,
                substr(trim((string)($_SERVER['REQUEST_URI'] ?? 'unknown')), 0, 255),
                getDashboardMenuRequestIpAddress(),
                substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')), 0, 255),
            ]);
        } catch (Throwable $e) {
            error_log('Dashboard denied access log failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('enforceCurrentUserDashboardMenuAccess')) {
    function enforceCurrentUserDashboardMenuAccess($menuKey, array $sections = ['sidebar', 'top_nav', 'footer'], $redirect = '../../auth/login')
    {
        if (currentUserCanAccessDashboardMenu($menuKey, $sections)) {
            return;
        }

        if (function_exists('getDBConnection')) {
            try {
                $pdo = getDBConnection();
                if ($pdo instanceof PDO) {
                    logDashboardMenuDeniedAccess($pdo, [(string)$menuKey], $sections);
                }
            } catch (Throwable $e) {
                error_log('Dashboard access guard logging failed: ' . $e->getMessage());
            }
        }

        header('Location: ' . $redirect);
        exit();
    }
}

if (!function_exists('enforceCurrentUserDashboardMenuAccessAny')) {
    function enforceCurrentUserDashboardMenuAccessAny(array $menuKeys, array $sections = ['sidebar', 'top_nav', 'footer'], $redirect = '../../auth/login')
    {
        if (currentUserCanAccessAnyDashboardMenu($menuKeys, $sections)) {
            return;
        }

        if (function_exists('getDBConnection')) {
            try {
                $pdo = getDBConnection();
                if ($pdo instanceof PDO) {
                    logDashboardMenuDeniedAccess($pdo, $menuKeys, $sections);
                }
            } catch (Throwable $e) {
                error_log('Dashboard access-any guard logging failed: ' . $e->getMessage());
            }
        }

        header('Location: ' . $redirect);
        exit();
    }
}

if (!function_exists('pickDashboardMenuItems')) {
    function pickDashboardMenuItems(array $registry, array $allowedKeys)
    {
        $items = [];

        foreach ($allowedKeys as $key) {
            if (isset($registry[$key])) {
                $items[] = $registry[$key];
            }
        }

        return $items;
    }
}

if (!function_exists('getDashboardMenuValidationIssues')) {
    function getDashboardMenuValidationIssues(array $registry = null, array $rulesMap = null)
    {
        $registry = $registry ?? getDashboardMenuRegistry();
        $rulesMap = $rulesMap ?? getDashboardRoleMenuRules();

        $issues = [];
        foreach (['top_nav', 'sidebar', 'footer'] as $section) {
            $registryKeys = array_keys((array)($registry[$section] ?? []));

            foreach ($rulesMap as $role => $ruleSections) {
                foreach ((array)($ruleSections[$section] ?? []) as $menuKey) {
                    if (!in_array($menuKey, $registryKeys, true)) {
                        $issues[] = $section . ' missing key: ' . $menuKey . ' in role ' . $role;
                    }
                }
            }
        }

        return array_values(array_unique($issues));
    }
}

if (!function_exists('getDashboardMenuConfigByRole')) {
    function getDashboardMenuConfigByRole($roleKey, PDO $pdo = null, array $options = [])
    {
        $normalizedRole = normalizeRoleKeyForMenuConfig($roleKey);
        $labels = getDashboardRoleLabels($pdo);
        $registry = getDashboardMenuRegistry();
        $rulesMap = getDashboardRoleMenuRules();

        static $loggedValidation = false;
        if (!$loggedValidation) {
            $issues = getDashboardMenuValidationIssues($registry, $rulesMap);
            if (!empty($issues)) {
                error_log('Dashboard menu config issues: ' . implode(' | ', $issues));
            }
            $loggedValidation = true;
        }

        $rules = $rulesMap[$normalizedRole] ?? $rulesMap['default'];
        $roleLabel = $labels[$normalizedRole] ?? formatRoleLabelFromKey($normalizedRole);

        $homeHref = isset($options['home_href'])
            ? (string)$options['home_href']
            : getRoleDashboardHomeHref($pdo, $normalizedRole, 'index.php');

        $topNav = pickDashboardMenuItems((array)$registry['top_nav'], (array)($rules['top_nav'] ?? []));
        $sidebar = pickDashboardMenuItems((array)$registry['sidebar'], (array)($rules['sidebar'] ?? []));
        $footer = pickDashboardMenuItems((array)$registry['footer'], (array)($rules['footer'] ?? []));

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
            'footer' => $footer,
        ];
    }
}