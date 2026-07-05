<?php
require_once 'config.php';
require_once 'ajax_response.php';

function mapUploadError($code) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'ขนาดไฟล์ใหญ่เกินค่าที่ระบบกำหนด',
        UPLOAD_ERR_FORM_SIZE => 'ขนาดไฟล์ใหญ่เกินที่ฟอร์มกำหนด',
        UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัปโหลดไม่สมบูรณ์',
        UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์รูปก่อนอัปโหลด',
        UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวของระบบ',
        UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
        UPLOAD_ERR_EXTENSION => 'การอัปโหลดถูกยกเลิกโดยส่วนเสริมของ PHP'
    ];

    return $messages[$code] ?? 'เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์';
}

function safeFilePart($value) {
    $value = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$value);
    $value = trim($value, '_');
    if ($value === '') {
        return 'user';
    }
    return strtolower(substr($value, 0, 40));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ajax_error('Method ไม่ถูกต้อง', 405);
}

if (!isLoggedIn()) {
    ajax_error('กรุณาเข้าสู่ระบบก่อนใช้งาน', 401);
}

if (!isset($_FILES['profile_photo'])) {
    ajax_error('ไม่พบไฟล์ที่อัปโหลด', 422);
}

$uploadedFile = $_FILES['profile_photo'];
if (!isset($uploadedFile['error']) || (int)$uploadedFile['error'] !== UPLOAD_ERR_OK) {
    ajax_error(mapUploadError((int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE)), 422);
}

$maxFileSize = 5 * 1024 * 1024;
if (!isset($uploadedFile['size']) || (int)$uploadedFile['size'] <= 0 || (int)$uploadedFile['size'] > $maxFileSize) {
    ajax_error('ขนาดไฟล์ต้องไม่เกิน 5MB', 422);
}

if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
    ajax_error('ไฟล์ที่ส่งมาไม่ถูกต้อง', 422);
}

$allowedMimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = (string)$finfo->file($uploadedFile['tmp_name']);
if (!isset($allowedMimeToExt[$detectedMime])) {
    ajax_error('รองรับเฉพาะไฟล์ JPG, PNG, GIF, WebP เท่านั้น', 422);
}

$extension = $allowedMimeToExt[$detectedMime];
$profileDirAbsolute = realpath(__DIR__ . '/../assets/images');
if ($profileDirAbsolute === false) {
    ajax_error('ไม่พบโฟลเดอร์ assets/images ของระบบ', 500);
}

$profileDirAbsolute = $profileDirAbsolute . DIRECTORY_SEPARATOR . 'profiles';
if (!is_dir($profileDirAbsolute) && !mkdir($profileDirAbsolute, 0755, true)) {
    ajax_error('ไม่สามารถสร้างโฟลเดอร์สำหรับรูปโปรไฟล์ได้', 500);
}

$userId = (string)($_SESSION['user_id'] ?? '');
if ($userId === '') {
    ajax_error('ไม่พบข้อมูลผู้ใช้งานในระบบ', 401);
}

$pdo = null;
$newRelativePath = '';
$newAbsolutePath = '';

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    $columnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if (!$columnStmt || !$columnStmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL AFTER password_hash");
    }

    $oldPathStmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = :user_id LIMIT 1");
    $oldPathStmt->execute(['user_id' => $userId]);
    $oldPath = (string)($oldPathStmt->fetchColumn() ?: '');

    $safeUserPart = safeFilePart($userId);
    $newFileName = 'profile_' . $safeUserPart . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $newRelativePath = 'assets/images/profiles/' . $newFileName;
    $newAbsolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newRelativePath);

    if (!move_uploaded_file($uploadedFile['tmp_name'], $newAbsolutePath)) {
        throw new RuntimeException('ไม่สามารถบันทึกไฟล์รูปโปรไฟล์ได้');
    }

    $updateStmt = $pdo->prepare("UPDATE users SET profile_image = :profile_image, updated_at = NOW() WHERE user_id = :user_id LIMIT 1");
    $updateStmt->execute([
        'profile_image' => $newRelativePath,
        'user_id' => $userId
    ]);

    $pdo->commit();

    $oldPath = ltrim($oldPath, '/');
    if ($oldPath !== '' && strpos($oldPath, 'assets/images/profiles/') === 0) {
        $oldAbsolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldPath);
        if (is_file($oldAbsolutePath) && $oldAbsolutePath !== $newAbsolutePath) {
            @unlink($oldAbsolutePath);
        }
    }

    ajax_success('อัปโหลดรูปโปรไฟล์สำเร็จ', [
        'image_path' => $newRelativePath
    ]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($newAbsolutePath !== '' && is_file($newAbsolutePath)) {
        @unlink($newAbsolutePath);
    }

    error_log('Upload profile image failed: ' . $e->getMessage());
    ajax_error('ไม่สามารถอัปโหลดรูปโปรไฟล์ได้', 500);
}
