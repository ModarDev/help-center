<?php
require_once '../../auth/config.php';

// ตรวจสอบการล็อกอิน
if (!isLoggedIn()) {
    header("Location: ../../auth/login");
    exit();
}

// ตรวจสอบสิทธิ์แอดมิน
if ($_SESSION['user_role'] !== 'admin') {
    header("Location: ../../auth/login");
    exit();
}
?>
.