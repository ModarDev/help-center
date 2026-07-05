<?php
// การตั้งค่าฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'office_login_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// การตั้งค่าระบบ
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');

if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

define('SITE_URL', $scheme . '://' . $host . $basePath);
define('SESSION_TIMEOUT', 3600); // 1 ชั่วโมง

// เส้นทางหลังล็อกอินสำหรับแต่ละสิทธิ์
define('SYSTEM_ADMIN_DASHBOARD', '../app/SYSTEM/index');
define('ADMIN_DASHBOARD', '../app/admin/menuadmin');
define('EMPLOYEE_DASHBOARD', '../app/employee/menuemployee');
define('MANAGER_DASHBOARD', '../app/manager/menumanager');
define('SELL_CAR_DASHBOARD', '../app/sell/pagesell');
define('SALES_MANAGER_DASHBOARD', '../app/sales_manager/page_sell_manager');

// Security settings
define('CSRF_TOKEN_EXPIRE', 1800); // 30 นาที
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 นาที

// การเชื่อมต่อฐานข้อมูล
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch(PDOException $e) {
        error_log("Connection failed: " . $e->getMessage());
        die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
    }
}

function getDefaultDashboardByRole($role) {
    $normalizedRole = strtolower(str_replace([' ', '-'], '_', trim((string)$role)));

    switch ($normalizedRole) {
        case 'system_admin':
            return SYSTEM_ADMIN_DASHBOARD;
        case 'admin':
            return ADMIN_DASHBOARD;
        case 'manager':
            return MANAGER_DASHBOARD;
        case 'sell_car':
            return SELL_CAR_DASHBOARD;
        case 'sales_manager':
            return SALES_MANAGER_DASHBOARD;
        case 'employee':
            return EMPLOYEE_DASHBOARD;
        default:
            return EMPLOYEE_DASHBOARD;
    }
}

function hasRolesTable(PDO $pdo) {
    static $checked = false;
    static $exists = false;

    if ($checked) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'roles'");
        $exists = (bool)($stmt && $stmt->fetch());
    } catch (Throwable $e) {
        $exists = false;
    }

    $checked = true;
    return $exists;
}

function getRoleOptions(PDO $pdo, $onlyActive = true) {
    $fallback = [
        'system_admin' => 'System Admin',
        'admin' => 'System Administrator',
        'manager' => 'Manager',
        'sales_manager' => 'Sales Manager',
        'sell_car' => 'Sell Car',
        'employee' => 'Employee',
    ];

    if (!hasRolesTable($pdo)) {
        return $fallback;
    }

    try {
        $sql = 'SELECT role_key, role_name FROM roles';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY role_name ASC';

        $stmt = $pdo->query($sql);
        $rows = $stmt ? $stmt->fetchAll() : [];

        $roles = [];
        foreach ($rows as $row) {
            $key = (string)($row['role_key'] ?? '');
            $name = trim((string)($row['role_name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $roles[$key] = $name !== '' ? $name : ucfirst($key);
        }

        if (!empty($roles)) {
            return $roles;
        }
    } catch (Throwable $e) {
        // Fallback to default role options when roles table is unavailable.
    }

    return $fallback;
}

function getDashboardByRole(PDO $pdo, $role) {
    $role = (string)$role;
    if ($role === '') {
        return EMPLOYEE_DASHBOARD;
    }

    if (hasRolesTable($pdo)) {
        try {
            $stmt = $pdo->prepare('SELECT dashboard_path FROM roles WHERE role_key = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$role]);
            $dashboardPath = trim((string)$stmt->fetchColumn());
            if ($dashboardPath !== '') {
                return $dashboardPath;
            }
        } catch (Throwable $e) {
            // Ignore and use fallback mapping below.
        }
    }

    return getDefaultDashboardByRole($role);
}

function normalizeAppPath($path) {
    $normalized = str_replace('\\', '/', trim((string)$path));
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/[?#].*$/', '', $normalized) ?? $normalized;
    $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;
    $normalized = rtrim($normalized, '/');
    $normalized = preg_replace('/\.php$/i', '', $normalized) ?? $normalized;

    return $normalized;
}

function canAccessDashboardByRole(PDO $pdo, $role, $targetPath) {
    $target = normalizeAppPath($targetPath);
    if ($target === '') {
        return false;
    }

    $expected = normalizeAppPath(getDashboardByRole($pdo, (string)$role));
    return $expected !== '' && $expected === $target;
}

function canCurrentUserAccessDashboard(PDO $pdo, $targetPath) {
    return canAccessDashboardByRole($pdo, (string)($_SESSION['user_role'] ?? ''), $targetPath);
}

function userHasAnyRole($allowedRoles) {
    if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
        return false;
    }

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    return in_array((string)$_SESSION['user_role'], $roles, true);
}

function hasBranchesTable(PDO $pdo) {
    static $checked = false;
    static $exists = false;

    if ($checked) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'branches'");
        $exists = (bool)($stmt && $stmt->fetch());
    } catch (Throwable $e) {
        $exists = false;
    }

    $checked = true;
    return $exists;
}

function shouldRequireBranchSelection(PDO $pdo) {
    if (!hasBranchesTable($pdo)) {
        return false;
    }

    try {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM branches')->fetchColumn();
        return $count > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function getAvailableBranches(PDO $pdo) {
    if (!hasBranchesTable($pdo)) {
        return [];
    }

    try {
        $stmt = $pdo->query('SELECT branch_id, company_name, branch_no, province, data_year FROM branches ORDER BY company_name ASC, branch_id ASC');
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        return [];
    }
}

function hasUserBranchAccessTable(PDO $pdo) {
    static $checked = false;
    static $exists = false;

    if ($checked) {
        return $exists;
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_branch_access'");
        $exists = (bool)($stmt && $stmt->fetch());
    } catch (Throwable $e) {
        $exists = false;
    }

    $checked = true;
    return $exists;
}

function ensureUserBranchAccessTable(PDO $pdo) {
    if (hasUserBranchAccessTable($pdo)) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_branch_access (
            user_id VARCHAR(50) NOT NULL,
            branch_id VARCHAR(30) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, branch_id),
            INDEX idx_uba_branch (branch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function getUserAllowedBranchIds(PDO $pdo, $userId) {
    $userId = trim((string)$userId);
    if ($userId === '' || !hasBranchesTable($pdo)) {
        return [];
    }

    ensureUserBranchAccessTable($pdo);

    try {
        $stmt = $pdo->prepare('SELECT branch_id FROM user_branch_access WHERE user_id = ? ORDER BY branch_id ASC');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $ids = [];
        foreach ($rows as $row) {
            $id = trim((string)$row);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    } catch (Throwable $e) {
        return [];
    }
}

function setUserAllowedBranchIds(PDO $pdo, $userId, array $branchIds) {
    $userId = trim((string)$userId);
    if ($userId === '' || !hasBranchesTable($pdo)) {
        return;
    }

    ensureUserBranchAccessTable($pdo);

    $cleanIds = [];
    foreach ($branchIds as $branchId) {
        $id = trim((string)$branchId);
        if ($id !== '') {
            $cleanIds[] = $id;
        }
    }
    $cleanIds = array_values(array_unique($cleanIds));

    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $deleteStmt = $pdo->prepare('DELETE FROM user_branch_access WHERE user_id = ?');
        $deleteStmt->execute([$userId]);

        if (!empty($cleanIds)) {
            $insertStmt = $pdo->prepare('INSERT INTO user_branch_access (user_id, branch_id) VALUES (?, ?)');
            foreach ($cleanIds as $branchId) {
                $insertStmt->execute([$userId, $branchId]);
            }
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function userCanAccessBranch(PDO $pdo, $userId, $branchId) {
    $branchId = trim((string)$branchId);
    if ($branchId === '') {
        return false;
    }

    if (!hasBranchesTable($pdo)) {
        return true;
    }

    $allowed = getUserAllowedBranchIds($pdo, (string)$userId);
    if (empty($allowed)) {
        // Empty mapping means no restriction (backward compatible).
        return true;
    }

    return in_array($branchId, $allowed, true);
}

function getAvailableBranchesForUser(PDO $pdo, $userId) {
    $allBranches = getAvailableBranches($pdo);
    if (empty($allBranches)) {
        return [];
    }

    $allowed = getUserAllowedBranchIds($pdo, (string)$userId);
    if (empty($allowed)) {
        return $allBranches;
    }

    $allowedMap = array_fill_keys($allowed, true);
    $filtered = [];
    foreach ($allBranches as $row) {
        $branchId = trim((string)($row['branch_id'] ?? ''));
        if ($branchId !== '' && isset($allowedMap[$branchId])) {
            $filtered[] = $row;
        }
    }

    return $filtered;
}

function clearCurrentBranchContext() {
    unset($_SESSION['active_branch_id'], $_SESSION['active_branch_name'], $_SESSION['active_branch_no'], $_SESSION['active_branch_year']);
}

function getCurrentBranchId() {
    return trim((string)($_SESSION['active_branch_id'] ?? ''));
}

function hasCurrentBranchSelection() {
    return getCurrentBranchId() !== '';
}

function setCurrentBranchContext(PDO $pdo, $branchId) {
    $branchId = trim((string)$branchId);
    if ($branchId === '' || !hasBranchesTable($pdo)) {
        return false;
    }

    $currentUserId = (string)($_SESSION['user_id'] ?? '');
    if (!userCanAccessBranch($pdo, $currentUserId, $branchId)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare('SELECT branch_id, company_name, branch_no, data_year FROM branches WHERE branch_id = ? LIMIT 1');
        $stmt->execute([$branchId]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $_SESSION['active_branch_id'] = (string)$row['branch_id'];
        $_SESSION['active_branch_name'] = (string)($row['company_name'] ?? '');
        $_SESSION['active_branch_no'] = (string)($row['branch_no'] ?? '');
        $_SESSION['active_branch_year'] = (string)($row['data_year'] ?? '');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function getSupportedDiscordWebhookKeys() {
    return ['login', 'logout', 'user_setup', 'sales_followup'];
}

function normalizeDiscordWebhookKey($webhookKey) {
    $normalized = strtolower(trim((string)$webhookKey));
    return in_array($normalized, getSupportedDiscordWebhookKeys(), true) ? $normalized : '';
}

function ensureDiscordWebhookSettingsTable(PDO $pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS discord_webhook_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            webhook_key VARCHAR(50) NOT NULL UNIQUE,
            webhook_url TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(50) DEFAULT NULL,
            updated_by VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function getDiscordWebhookSettings(PDO $pdo) {
    $settings = [
        'login' => '',
        'logout' => '',
        'user_setup' => '',
        'sales_followup' => ''
    ];

    try {
        ensureDiscordWebhookSettingsTable($pdo);

        $stmt = $pdo->query('SELECT webhook_key, webhook_url, is_active FROM discord_webhook_settings');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($rows as $row) {
            $key = normalizeDiscordWebhookKey($row['webhook_key'] ?? '');
            if ($key === '') {
                continue;
            }

            $url = trim((string)($row['webhook_url'] ?? ''));
            $isActive = (int)($row['is_active'] ?? 0) === 1;
            if ($isActive && $url !== '') {
                $settings[$key] = $url;
            }
        }
    } catch (Throwable $e) {
        // Return default empty settings.
    }

    return $settings;
}

function getDiscordWebhookUrl(PDO $pdo, $webhookKey) {
    $key = normalizeDiscordWebhookKey($webhookKey);
    if ($key === '') {
        return '';
    }

    $settings = getDiscordWebhookSettings($pdo);
    return trim((string)($settings[$key] ?? ''));
}

function setDiscordWebhookUrl(PDO $pdo, $webhookKey, $webhookUrl, $updatedBy = '') {
    $key = normalizeDiscordWebhookKey($webhookKey);
    if ($key === '') {
        return false;
    }

    $url = trim((string)$webhookUrl);
    $userId = trim((string)$updatedBy);
    if ($userId === '') {
        $userId = null;
    }

    ensureDiscordWebhookSettingsTable($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO discord_webhook_settings (webhook_key, webhook_url, is_active, created_by, updated_by)
         VALUES (:webhook_key, :webhook_url, :is_active, :created_by, :updated_by)
         ON DUPLICATE KEY UPDATE
            webhook_url = VALUES(webhook_url),
            is_active = VALUES(is_active),
            updated_by = VALUES(updated_by),
            updated_at = CURRENT_TIMESTAMP'
    );

    return $stmt->execute([
        'webhook_key' => $key,
        'webhook_url' => $url,
        'is_active' => $url !== '' ? 1 : 0,
        'created_by' => $userId,
        'updated_by' => $userId
    ]);
}

function getDeviceTypeFromUserAgent($userAgent) {
    $ua = strtolower(trim((string)$userAgent));
    if ($ua === '') {
        return 'Unknown';
    }

    if (preg_match('/ipad|tablet|kindle|playbook/', $ua)) {
        return 'Tablet';
    }

    if (preg_match('/mobile|iphone|android|blackberry|iemobile|opera mini/', $ua)) {
        return 'Mobile';
    }

    if (preg_match('/curl|postman|insomnia/', $ua)) {
        return 'API Client';
    }

    if (preg_match('/windows|macintosh|linux|x11/', $ua)) {
        return 'Desktop';
    }

    return 'Unknown';
}

function sendDiscordWebhookMessage($webhookUrl, array $payload) {
    $url = trim((string)$webhookUrl);
    if ($url === '') {
        return false;
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_CONNECTTIMEOUT => 3
        ]);

        $result = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $httpCode >= 200 && $httpCode < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $json,
            'timeout' => 4,
            'ignore_errors' => true
        ]
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return false;
    }

    if (!empty($http_response_header) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $matches)) {
        $httpCode = (int)$matches[1];
        return $httpCode >= 200 && $httpCode < 300;
    }

    return true;
}

function sendAccessAuditToDiscord(PDO $pdo, $webhookKey, array $eventData) {
    $key = normalizeDiscordWebhookKey($webhookKey);
    if ($key === '') {
        return false;
    }

    $webhookUrl = getDiscordWebhookUrl($pdo, $key);
    if ($webhookUrl === '') {
        return false;
    }

    $name = trim((string)($eventData['name'] ?? '-'));
    $role = trim((string)($eventData['role'] ?? '-'));
    $ip = trim((string)($eventData['ip'] ?? '-'));
    $defaultAction = 'Action';
    if ($key === 'login') {
        $defaultAction = 'Login';
    }
    if ($key === 'logout') {
        $defaultAction = 'Logout';
    }
    if ($key === 'user_setup') {
        $defaultAction = 'User Setup';
    }
    if ($key === 'sales_followup') {
        $defaultAction = 'Sales Follow-up Digest';
    }

    $action = trim((string)($eventData['action'] ?? $defaultAction));
    $status = trim((string)($eventData['status'] ?? 'Success'));
    $deviceType = trim((string)($eventData['device_type'] ?? 'Unknown'));

    $startedAtRaw = trim((string)($eventData['started_at'] ?? ''));
    $completedAtRaw = trim((string)($eventData['completed_at'] ?? ''));

    $startedAtTs = strtotime($startedAtRaw);
    if ($startedAtTs === false) {
        $startedAtTs = time();
    }

    $completedAtTs = strtotime($completedAtRaw);
    if ($completedAtTs === false) {
        $completedAtTs = $startedAtTs;
    }

    $durationSeconds = (float)($eventData['duration_seconds'] ?? 0);
    if ($durationSeconds < 0) {
        $durationSeconds = 0;
    }

    $title = 'แจ้งเตือนการทำรายการ';
    $color = 3447003;
    if ($key === 'login') {
        $title = 'แจ้งเตือนการ Login';
        $color = 3066993;
    }
    if ($key === 'logout') {
        $title = 'แจ้งเตือนการ Logout';
        $color = 15105570;
    }
    if ($key === 'user_setup') {
        $title = 'แจ้งเตือนการตั้งค่า User';
        $color = 10181046;
    }
    if ($key === 'sales_followup') {
        $title = 'แจ้งเตือนงาน Follow-up ค้าง';
        $color = 15105570;
    }

    $payload = [
        'username' => 'Office Plus Audit Bot',
        'embeds' => [[
            'title' => $title,
            'color' => $color,
            'fields' => [
                ['name' => 'ชื่อ', 'value' => $name !== '' ? $name : '-', 'inline' => true],
                ['name' => 'role', 'value' => $role !== '' ? $role : '-', 'inline' => true],
                ['name' => 'วันที่ทำรายการ', 'value' => date('d/m/Y', $startedAtTs), 'inline' => true],
                ['name' => 'เวลาที่ทำรายการ', 'value' => date('H:i:s', $startedAtTs), 'inline' => true],
                ['name' => 'วันที่ทำรายการสำเร็จ', 'value' => date('d/m/Y', $completedAtTs), 'inline' => true],
                ['name' => 'เวลาที่ทำรายการสำเร็จ', 'value' => date('H:i:s', $completedAtTs), 'inline' => true],
                ['name' => 'เวลาทำรายเฉลี่ย', 'value' => number_format($durationSeconds, 3, '.', '') . ' วินาที', 'inline' => true],
                ['name' => 'ประเภทอุปกรณ์', 'value' => $deviceType !== '' ? $deviceType : '-', 'inline' => true],
                ['name' => 'ip', 'value' => $ip !== '' ? $ip : '-', 'inline' => true],
                ['name' => 'รายการที่กระทำ', 'value' => $action !== '' ? $action : '-', 'inline' => true],
                ['name' => 'สถานะของรายการ', 'value' => $status !== '' ? $status : '-', 'inline' => true]
            ],
            'timestamp' => gmdate('c')
        ]]
    ];

    return sendDiscordWebhookMessage($webhookUrl, $payload);
}

function addTopbarNotification($title, $message, $timestamp = null) {
    $title = trim((string)$title);
    $message = trim((string)$message);
    $time = is_numeric($timestamp) ? (int)$timestamp : time();

    if (!isset($_SESSION['topbar_notifications']) || !is_array($_SESSION['topbar_notifications'])) {
        $_SESSION['topbar_notifications'] = [];
    }

    $_SESSION['topbar_notifications'][] = [
        'title' => $title !== '' ? $title : 'แจ้งเตือน',
        'message' => $message !== '' ? $message : '-',
        'time' => $time > 0 ? $time : time(),
        'is_unread' => true
    ];
}

function consumeTopbarNotifications() {
    $notifications = [];

    $rawNotifications = $_SESSION['topbar_notifications'] ?? [];
    unset($_SESSION['topbar_notifications']);

    if (is_array($rawNotifications)) {
        foreach ($rawNotifications as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string)($item['title'] ?? ''));
            $message = trim((string)($item['message'] ?? ''));
            $time = (int)($item['time'] ?? time());

            $notifications[] = [
                'title' => $title !== '' ? $title : 'แจ้งเตือน',
                'message' => $message !== '' ? $message : '-',
                'time' => $time > 0 ? $time : time(),
                'is_unread' => (bool)($item['is_unread'] ?? true)
            ];
        }
    }

    // Backward compatibility with previously stored login notification key.
    $legacyLoginNotification = $_SESSION['login_success_notification'] ?? null;
    unset($_SESSION['login_success_notification']);

    if (is_array($legacyLoginNotification)) {
        $legacyTitle = trim((string)($legacyLoginNotification['title'] ?? 'เข้าสู่ระบบสำเร็จ'));
        $legacyMessage = trim((string)($legacyLoginNotification['message'] ?? 'ยินดีต้อนรับเข้าสู่ระบบ Office Plus ERP'));
        $legacyTime = (int)($legacyLoginNotification['time'] ?? time());

        $notifications[] = [
            'title' => $legacyTitle !== '' ? $legacyTitle : 'เข้าสู่ระบบสำเร็จ',
            'message' => $legacyMessage !== '' ? $legacyMessage : '-',
            'time' => $legacyTime > 0 ? $legacyTime : time(),
            'is_unread' => true
        ];
    }

    if (count($notifications) > 1) {
        usort($notifications, function ($a, $b) {
            return ((int)$b['time']) <=> ((int)$a['time']);
        });
    }

    return $notifications;
}

// ฟังก์ชันสำหรับความปลอดภัย
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if (time() - $_SESSION['csrf_token_time'] > CSRF_TOKEN_EXPIRE) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

// เริ่ม session อย่างปลอดภัย
function startSecureSession() {
    // ตั้งค่า session
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
    
    session_start();
    
    // ป้องกัน session fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
}

function invalidateCurrentSession(PDO $pdo = null) {
    $sessionId = session_id();

    if ($sessionId !== '') {
        try {
            $db = $pdo ?: getDBConnection();
            $stmt = $db->prepare('DELETE FROM user_sessions WHERE session_id = ?');
            $stmt->execute([$sessionId]);
        } catch (Throwable $e) {
            error_log('Session invalidation DB cleanup failed: ' . $e->getMessage());
        }
    }

    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            if (!headers_sent()) {
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    (bool)($params['secure'] ?? false),
                    (bool)($params['httponly'] ?? true)
                );
            }
        }

        session_destroy();
    }
}

// ตรวจสอบการล็อกอิน
function isLoggedIn() {
    if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
        return false;
    }

    $sessionId = session_id();
    if ($sessionId === '') {
        return false;
    }

    try {
        $pdo = getDBConnection();

        $cleanupStmt = $pdo->prepare('DELETE FROM user_sessions WHERE expires_at < NOW()');
        $cleanupStmt->execute();

        $stmt = $pdo->prepare(
            'SELECT us.user_id,
                    u.user_role,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.position,
                    u.department,
                    u.company
             FROM user_sessions us
             INNER JOIN users u ON u.user_id = us.user_id
             WHERE us.session_id = ?
               AND us.expires_at > NOW()
               AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$sessionId]);
        $row = $stmt->fetch();

        if (!$row || (string)$row['user_id'] !== (string)$_SESSION['user_id']) {
            invalidateCurrentSession($pdo);
            return false;
        }

        $_SESSION['user_role'] = (string)$row['user_role'];
        $_SESSION['first_name'] = (string)($row['first_name'] ?? ($_SESSION['first_name'] ?? ''));
        $_SESSION['last_name'] = (string)($row['last_name'] ?? ($_SESSION['last_name'] ?? ''));
        $_SESSION['email'] = (string)($row['email'] ?? ($_SESSION['email'] ?? ''));
        $_SESSION['position'] = (string)($row['position'] ?? ($_SESSION['position'] ?? ''));
        $_SESSION['department'] = (string)($row['department'] ?? ($_SESSION['department'] ?? ''));
        $_SESSION['company'] = (string)($row['company'] ?? ($_SESSION['company'] ?? ''));
        $_SESSION['login_time'] = time();

        $refreshStmt = $pdo->prepare(
            'UPDATE user_sessions
             SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 ip_address = ?,
                 user_agent = ?
             WHERE session_id = ?'
        );
        $refreshStmt->execute([
            SESSION_TIMEOUT,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $sessionId
        ]);

        return true;
    } catch (Throwable $e) {
        error_log('Session validation failed: ' . $e->getMessage());
        invalidateCurrentSession();
        return false;
    }
}

// ออกจากระบบ
function logout() {
    session_unset();
    session_destroy();
    header("Location: login");
    exit();
}

// เริ่ม session
startSecureSession();
?>