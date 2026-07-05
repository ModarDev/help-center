<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('POST');

try {
    // ตรวจสอบการล็อกอินและสิทธิ์ Admin
    if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
        ajax_error('ไม่มีสิทธิ์ในการอัพโหลด Logo', 403);
    }

    // ตรวจสอบว่ามีการอัพโหลดไฟล์หรือไม่
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        ajax_error('ไม่มีไฟล์ที่อัพโหลดหรือเกิดข้อผิดพลาด', 422);
    }

    $uploadedFile = $_FILES['logo'];
    $logoDir = '../assets/images/logo/';
    
    // สร้าง directory หากยังไม่มี
    if (!is_dir($logoDir)) {
        if (!mkdir($logoDir, 0755, true)) {
            ajax_error('ไม่สามารถสร้างโฟลเดอร์ logo ได้', 500);
        }
    }

    // ตรวจสอบประเภทไฟล์
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $uploadedFile['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        ajax_error('ประเภทไฟล์ไม่ถูกต้อง กรุณาใช้ไฟล์รูปภาพ (JPG, PNG, GIF, WebP)', 422);
    }

    // ตรวจสอบขนาดไฟล์ (จำกัดที่ 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($uploadedFile['size'] > $maxFileSize) {
        ajax_error('ขนาดไฟล์ใหญ่เกินไป กรุณาใช้ไฟล์ที่เล็กกว่า 5MB', 422);
    }

    // กำหนดนามสกุลไฟล์ใหม่
    $extension = '';
    switch ($fileType) {
        case 'image/jpeg':
        case 'image/jpg':
            $extension = 'jpg';
            break;
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/gif':
            $extension = 'gif';
            break;
        case 'image/webp':
            $extension = 'webp';
            break;
    }

    // ลบไฟล์ logo เก่าทั้งหมดก่อน
    $oldLogos = glob($logoDir . 'logo.*');
    foreach ($oldLogos as $oldLogo) {
        if (file_exists($oldLogo)) {
            unlink($oldLogo);
        }
    }

    // กำหนดชื่อไฟล์ใหม่
    $newFileName = 'logo.' . $extension;
    $destinationPath = $logoDir . $newFileName;

    // อัพโหลดไฟล์
    if (move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
        // บันทึก Log การเปลี่ยนแปลง
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('logo_upload', 'อัพโหลด Logo ใหม่: ' . $newFileName, $_SESSION['user_id']);
        }
        
        ajax_success('อัพโหลด Logo สำเร็จ', [
            'logo' => $newFileName
        ]);
    } else {
        ajax_error('ไม่สามารถอัพโหลดไฟล์ได้', 500);
    }

} catch (Exception $e) {
    ajax_error('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
?>