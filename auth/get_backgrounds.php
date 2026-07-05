<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('GET');

if (!isLoggedIn() || !userHasAnyRole(['admin'])) {
    ajax_error('ไม่มีสิทธิ์ในการเข้าถึงข้อมูลพื้นหลัง', 403);
}

// ไฟล์นามสกุลที่รองรับ
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$background_dir = '../assets/images/backgrounds/';

try {
    $backgrounds = [];
    
    if (is_dir($background_dir)) {
        $files = scandir($background_dir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            
            if (in_array($file_extension, $allowed_extensions)) {
                $full_path = $background_dir . $file;
                if (is_file($full_path)) {
                    $backgrounds[] = $file;
                }
            }
        }
    }
    
    // เรียงลำดับตามชื่อไฟล์
    sort($backgrounds);
    
    ajax_success('', [
        'backgrounds' => $backgrounds,
        'count' => count($backgrounds)
    ]);
    
} catch (Exception $e) {
    ajax_error('เกิดข้อผิดพลาดในการโหลดรูปพื้นหลัง', 500, [
        'error' => $e->getMessage()
    ]);
}
?>