<?php
// ไฟล์ security headers และ functions เพิ่มเติม
require_once 'config.php';

// ตั้งค่า Security Headers
function setSecurityHeaders() {
    // ป้องกัน XSS
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // HTTPS Redirect (ใช้เมื่อมี SSL)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
    
    // Remove server information
    header('Server: ');
}

// ตรวจสอบความแข็งแกร่งของรหัสผ่าน
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีตัวพิมพ์เล็กอย่างน้อย 1 ตัว";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "รหัสผ่านต้องมีอักขระพิเศษอย่างน้อย 1 ตัว";
    }
    
    return $errors;
}

// ตรวจสอบ IP Address ที่น่าสงสัย
function checkSuspiciousIP($ip) {
    // รายการ IP ที่ถูกบล็อก (ควรเก็บในฐานข้อมูล)
    $blocked_ips = [
        // เพิ่ม IP ที่ต้องการบล็อก
    ];
    
    return in_array($ip, $blocked_ips);
}

// ป้องกัน SQL Injection
function validateInput($input, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
        default:
            return sanitizeInput($input);
    }
}

// Rate limiting function
function checkRateLimit($identifier, $limit = 5, $window = 60) {
    try {
        $pdo = getDBConnection();
        
        // ลบ records เก่า
        $cleanup = $pdo->prepare("DELETE FROM rate_limit WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $cleanup->execute([$window]);
        
        // นับจำนวน requests ปัจจุบัน
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limit WHERE identifier = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $count_stmt->execute([$identifier, $window]);
        $count = $count_stmt->fetchColumn();
        
        if ($count >= $limit) {
            return false; // เกินขีดจำกัด
        }
        
        // บันทึก request ใหม่
        $insert = $pdo->prepare("INSERT INTO rate_limit (identifier, created_at) VALUES (?, NOW())");
        $insert->execute([$identifier]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Rate limit error: " . $e->getMessage());
        return true; // ให้ผ่านถ้าเกิดข้อผิดพลาด
    }
}

// ตรวจสอบ session ที่หมดอายุ
function validateSessionTimeout() {
    if (isset($_SESSION['login_time'])) {
        $session_lifetime = time() - $_SESSION['login_time'];
        if ($session_lifetime > SESSION_TIMEOUT) {
            logout();
            return false;
        }
        
        // อัพเดทเวลาล่าสุด
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// ลบ session ที่หมดอายุจากฐานข้อมูล
function cleanupExpiredSessions() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Session cleanup error: " . $e->getMessage());
    }
}

// เข้ารหัสข้อมูลที่สำคัญ
function encryptSensitiveData($data, $key = null) {
    if ($key === null) {
        $key = hash('sha256', 'your-secret-key-here', true);
    }
    
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

// ถอดรหัสข้อมูลที่สำคัญ
function decryptSensitiveData($data, $key = null) {
    if ($key === null) {
        $key = hash('sha256', 'your-secret-key-here', true);
    }
    
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

// Log security events
function logSecurityEvent($event_type, $details, $user_id = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO security_logs (event_type, details, user_id, ip_address, user_agent, created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $event_type,
            $details,
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("Security log error: " . $e->getMessage());
    }
}

// สร้างตาราง security_logs และ rate_limit ถ้ายังไม่มี
function createSecurityTables() {
    try {
        $pdo = getDBConnection();
        
        // ตาราง security_logs
        $pdo->exec("CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            details TEXT,
            user_id VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        )");
        
        // ตาราง rate_limit
        $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_identifier (identifier),
            INDEX idx_created_at (created_at)
        )");
        
    } catch (PDOException $e) {
        error_log("Create security tables error: " . $e->getMessage());
    }
}

// เรียกใช้เมื่อ include ไฟล์นี้
setSecurityHeaders();
cleanupExpiredSessions();
createSecurityTables();
?>