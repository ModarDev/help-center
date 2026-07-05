<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('POST');

$request_started_microtime = microtime(true);
$request_started_at = date('Y-m-d H:i:s');

// ตรวจสอบ CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    ajax_error('Invalid CSRF token', 419);
}

$user_id = sanitizeInput($_POST['user_id'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($user_id) || empty($password)) {
    ajax_error('กรุณากรอกข้อมูลให้ครบถ้วน', 422);
}

try {
    $pdo = getDBConnection();
    
    // ตรวจสอบการล็อกอินที่ล้มเหลวใน 15 นาทีที่ผ่านมา
    $lockout_check = $pdo->prepare("SELECT COUNT(*) FROM login_logs 
                                   WHERE user_id = ? AND login_status = 'failed' 
                                   AND failure_reason = 'Invalid password'
                                   AND login_time > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $lockout_check->execute([$user_id, LOGIN_LOCKOUT_TIME]);
    $failed_attempts = $lockout_check->fetchColumn();

    if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
        ajax_error('บัญชีถูกล็อกชั่วคราว กรุณาลองใหม่ในอีก 15 นาที', 429);
    }

    // ดึงข้อมูลผู้ใช้
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, position, department, company, user_role, password_hash, is_active 
                          FROM users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // ล็อกอินสำเร็จ
        
        // สร้าง session ใหม่
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_role'] = $user['user_role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['position'] = $user['position'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['company'] = $user['company'];
        $_SESSION['login_time'] = time();
        addTopbarNotification('เข้าสู่ระบบสำเร็จ', 'ยินดีต้อนรับเข้าสู่ระบบ Office Plus ERP');

        // รีเซ็ตสาขาที่เลือกทุกครั้งหลังล็อกอิน เพื่อให้ผู้ใช้เลือกสาขาก่อนใช้งาน
        clearCurrentBranchContext();

        // บันทึก session ในฐานข้อมูล
        $session_id = session_id();
        $session_stmt = $pdo->prepare("INSERT INTO user_sessions (session_id, user_id, expires_at, ip_address, user_agent) 
                                      VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, ?)
                                      ON DUPLICATE KEY UPDATE 
                                      expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), 
                                      ip_address = VALUES(ip_address), 
                                      user_agent = VALUES(user_agent)");
        $session_stmt->execute([
            $session_id,
            $user['user_id'],
            SESSION_TIMEOUT,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            SESSION_TIMEOUT
        ]);

        // บันทึก log การล็อกอินสำเร็จ
        $log_stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, login_status) 
                                  VALUES (?, ?, ?, 'success')");
        $log_stmt->execute([
            $user['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        try {
            $display_name = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')));
            if ($display_name === '') {
                $display_name = (string)($user['user_id'] ?? '-');
            }

            $request_completed_at = date('Y-m-d H:i:s');
            $duration_seconds = microtime(true) - $request_started_microtime;

            sendAccessAuditToDiscord($pdo, 'login', [
                'name' => $display_name,
                'role' => (string)($user['user_role'] ?? '-'),
                'started_at' => $request_started_at,
                'completed_at' => $request_completed_at,
                'duration_seconds' => $duration_seconds,
                'device_type' => getDeviceTypeFromUserAgent($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'action' => 'Login',
                'status' => 'Success'
            ]);
        } catch (Throwable $e) {
            error_log('Login Discord webhook error: ' . $e->getMessage());
        }

        // กำหนดหน้าที่จะ redirect ไปตามสิทธิ์จากตาราง roles
        $redirect_url = getDashboardByRole($pdo, (string)$user['user_role']);
        $requires_branch_selection = shouldRequireBranchSelection($pdo);
        $branch_selector_url = '';
        if ($requires_branch_selection) {
            $branch_selector_url = 'branch_selector_popup.php?redirect=' . rawurlencode($redirect_url);
        }

        ajax_success('เข้าสู่ระบบสำเร็จ', [
            'redirect' => $redirect_url,
            'requires_branch_selection' => $requires_branch_selection,
            'branch_selector_url' => $branch_selector_url,
            'user' => [
                'user_id' => $user['user_id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'role' => $user['user_role']
            ]
        ]);

    } else {
        // ล็อกอินไม่สำเร็จ
        $log_stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, login_status, failure_reason) 
                                  VALUES (?, ?, ?, 'failed', 'Invalid password')");
        $log_stmt->execute([
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        $remaining_attempts = MAX_LOGIN_ATTEMPTS - ($failed_attempts + 1);
        $message = 'รหัสผ่านไม่ถูกต้อง';
        
        if ($remaining_attempts > 0) {
            $message .= " (เหลือ $remaining_attempts ครั้ง)";
        } else {
            $message = 'รหัสผ่านไม่ถูกต้อง บัญชีถูกล็อกชั่วคราว 15 นาที';
        }

        ajax_error($message, 401);
    }

} catch (PDOException $e) {
    error_log("Database error in authenticate.php: " . $e->getMessage());
    ajax_error('เกิดข้อผิดพลาดในการเข้าใช้งานระบบกรุณาลองใหม่อีกครั้งหรือติดต่อเจ้า้าหน้าที่', 500);
}
?>