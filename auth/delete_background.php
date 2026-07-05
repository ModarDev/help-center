<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('POST');

// ตรวจสอบสิทธิ์การลบ (เฉพาะแอดมิน)
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    ajax_error('ไม่มีสิทธิ์ในการลบรูปภาพ', 403);
}

$filename = $_POST['filename'] ?? '';

try {
    if (empty($filename)) {
        throw new Exception('ไม่พบชื่อไฟล์ที่ต้องการลบ');
    }

    // ตรวจสอบและทำความสะอาดชื่อไฟล์
    $filename = basename($filename);
    $file_path = '../assets/images/backgrounds/' . $filename;

    // ตรวจสอบว่าไฟล์มีอยู่จริง
    if (!file_exists($file_path)) {
        throw new Exception('ไม่พบไฟล์ที่ต้องการลบ');
    }

    // ตรวจสอบว่าอยู่ในโฟลเดอร์ที่ถูกต้อง
    $real_path = realpath($file_path);
    $backgrounds_dir = realpath('../assets/images/backgrounds/');
    
    if (!$real_path || !$backgrounds_dir || strpos($real_path, $backgrounds_dir) !== 0) {
        throw new Exception('ตำแหน่งไฟล์ไม่ถูกต้อง');
    }

    // ลบไฟล์
    if (!unlink($file_path)) {
        throw new Exception('ไม่สามารถลบไฟล์ได้');
    }

    // บันทึก log การลบ
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('background_delete', "Deleted background: $filename", $_SESSION['user_id']);
    }

    ajax_success('ลบรูปพื้นหลังสำเร็จ');

} catch (Exception $e) {
    ajax_error($e->getMessage(), 422);
}
?>