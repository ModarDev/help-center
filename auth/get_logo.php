<?php
require_once 'config.php';
require_once 'ajax_response.php';

ajax_require_method('GET');

try {
    $logoPath = 'assets/images/logo/logo3.png';
    $logoFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'logo3.png';

    if (is_file($logoFile)) {
        ajax_success('', [
            'logo' => 'logo3.png',
            'logo_path' => $logoPath,
            'logo_url' => '../' . ltrim($logoPath, '/')
        ]);
    }

    ajax_error('ไม่พบไฟล์โลโก้ logo3.png', 404);
} catch (Throwable $e) {
    error_log('Get logo failed: ' . $e->getMessage());
    ajax_error('ไม่สามารถโหลดโลโก้ได้', 500);
}
