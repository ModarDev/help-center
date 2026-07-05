<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('POST');

// ตรวจสอบสิทธิ์การอัพโหลด (เฉพาะแอดมิน)
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    ajax_error('ไม่มีสิทธิ์ในการอัพโหลดรูปภาพ', 403);
}

$upload_dir = '../assets/images/backgrounds/';
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_file_size = 5 * 1024 * 1024; // 5MB

try {
    if (!isset($_FILES['background'])) {
        throw new Exception('ไม่พบไฟล์ที่อัพโหลด');
    }

    $file = $_FILES['background'];
    
    // ตรวจสอบข้อผิดพลาดการอัพโหลด
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('เกิดข้อผิดพลาดในการอัพโหลดไฟล์');
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > $max_file_size) {
        throw new Exception('ขนาดไฟล์ใหญ่เกินไป (สูงสุด 5MB)');
    }

    // ตรวจสอบประเภทไฟล์
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('ประเภทไฟล์ไม่ถูกต้อง กรุณาใช้ไฟล์ jpg, jpeg, png, gif หรือ webp');
    }

    // ตรวจสอบว่าเป็นไฟล์รูปภาพจริง
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        throw new Exception('ไฟล์ที่อัพโหลดไม่ใช่รูปภาพ');
    }

    // สร้างชื่อไฟล์ใหม่เพื่อป้องกันปัญหาความปลอดภัย
    $new_filename = uniqid('bg_') . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;

    // สร้างโฟลเดอร์ถ้ายังไม่มี
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // ย้ายไฟล์ไปยังตำแหน่งที่ต้องการ
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('ไม่สามารถบันทึกไฟล์ได้');
    }

    // ปรับขนาดรูปภาพถ้าจำเป็น (เพื่อประสิทธิภาพ)
    resizeImage($upload_path, 1920, 1080);

    // บันทึก log การอัพโหลด
    if (function_exists('logSecurityEvent')) {
        logSecurityEvent('background_upload', "Uploaded background: $new_filename", $_SESSION['user_id']);
    }

    ajax_success('อัพโหลดรูปพื้นหลังสำเร็จ', [
        'filename' => $new_filename
    ]);

} catch (Exception $e) {
    ajax_error($e->getMessage(), 422);
}

// ฟังก์ชันปรับขนาดรูปภาพ
function resizeImage($file_path, $max_width, $max_height) {
    $image_info = getimagesize($file_path);
    $width = $image_info[0];
    $height = $image_info[1];
    $mime_type = $image_info['mime'];

    // ถ้ารูปเล็กกว่าที่กำหนดก็ไม่ต้องปรับ
    if ($width <= $max_width && $height <= $max_height) {
        return;
    }

    // คำนวณขนาดใหม่
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);

    // สร้าง image resource
    switch ($mime_type) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($file_path);
            break;
        case 'image/png':
            $source = imagecreatefrompng($file_path);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file_path);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file_path);
            break;
        default:
            return; // ไม่รองรับ
    }

    if (!$source) return;

    // สร้างรูปภาพใหม่
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // รักษาความโปร่งใส (สำหรับ PNG และ GIF)
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefill($new_image, 0, 0, $transparent);
    }

    // ปรับขนาด
    imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // บันทึกไฟล์
    switch ($mime_type) {
        case 'image/jpeg':
            imagejpeg($new_image, $file_path, 85);
            break;
        case 'image/png':
            imagepng($new_image, $file_path, 8);
            break;
        case 'image/gif':
            imagegif($new_image, $file_path);
            break;
        case 'image/webp':
            imagewebp($new_image, $file_path, 85);
            break;
    }

    // ล้าง memory
    imagedestroy($source);
    imagedestroy($new_image);
}
?>