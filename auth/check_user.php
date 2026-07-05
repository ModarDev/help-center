<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('POST');

// ตรวจสอบ CSRF token
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    ajax_error('Invalid CSRF token', 419);
}

$user_id = sanitizeInput($_POST['user_id'] ?? '');

if (empty($user_id)) {
    ajax_error('กรุณากรอกรหัสผู้ใช้งาน', 422);
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, email, position, department, company, user_role, is_active 
                          FROM users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        ajax_success('', [
            'user' => [
                'user_id' => $user['user_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'position' => $user['position'],
                'department' => $user['department'],
                'company' => $user['company'],
                'user_role' => $user['user_role']
            ]
        ]);
    } else {
        ajax_error('ไม่พบรหัสผู้ใช้งานในระบบ', 404);
    }

} catch (PDOException $e) {
    error_log("Database error in check_user.php: " . $e->getMessage());
    ajax_error('เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล', 500);
}
?>