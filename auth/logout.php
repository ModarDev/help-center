<?php
require_once 'config.php';

$request_started_microtime = microtime(true);
$request_started_at = date('Y-m-d H:i:s');

$logout_user_id = (string)($_SESSION['user_id'] ?? '');
$logout_role = (string)($_SESSION['user_role'] ?? '');
$logout_name = trim((string)($_SESSION['first_name'] ?? '') . ' ' . (string)($_SESSION['last_name'] ?? ''));
if ($logout_name === '') {
    $logout_name = $logout_user_id !== '' ? $logout_user_id : '-';
}

// ลบ session จากฐานข้อมูล
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        $session_id = session_id();
        
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        
        // บันทึก log การออกจากระบบ
        $log_stmt = $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, login_status, failure_reason) 
                                  VALUES (?, ?, ?, 'success', 'User logout')");
        $log_stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        try {
            $request_completed_at = date('Y-m-d H:i:s');
            $duration_seconds = microtime(true) - $request_started_microtime;

            sendAccessAuditToDiscord($pdo, 'logout', [
                'name' => $logout_name,
                'role' => $logout_role,
                'started_at' => $request_started_at,
                'completed_at' => $request_completed_at,
                'duration_seconds' => $duration_seconds,
                'device_type' => getDeviceTypeFromUserAgent($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
                'action' => 'Logout',
                'status' => 'Success'
            ]);
        } catch (Throwable $e) {
            error_log('Logout Discord webhook error: ' . $e->getMessage());
        }
        
    } catch (PDOException $e) {
        error_log("Database error in logout.php: " . $e->getMessage());
    }
}

// ทำลาย session
logout();
?>