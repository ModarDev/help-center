<?php
if (!function_exists('ajax_send')) {
    function ajax_send($success, $message = '', $payload = [], $statusCode = 200) {
        if (!headers_sent()) {
            http_response_code((int)$statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $response = array_merge([
            'success' => (bool)$success,
            'message' => (string)$message,
            'timestamp' => gmdate('c')
        ], is_array($payload) ? $payload : []);

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('ajax_success')) {
    function ajax_success($message = '', $payload = [], $statusCode = 200) {
        ajax_send(true, $message, $payload, $statusCode);
    }
}

if (!function_exists('ajax_error')) {
    function ajax_error($message = 'Request failed', $statusCode = 400, $payload = []) {
        ajax_send(false, $message, $payload, $statusCode);
    }
}

if (!function_exists('ajax_require_method')) {
    function ajax_require_method($method = 'POST') {
        $expected = strtoupper((string)$method);
        $actual = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($actual !== $expected) {
            ajax_error('Method not allowed', 405, ['expected_method' => $expected]);
        }
    }
}

if (!function_exists('ajax_require_login')) {
    function ajax_require_login($allowedRoles = null) {
        if (!function_exists('isLoggedIn') || !isLoggedIn()) {
            ajax_error('กรุณาเข้าสู่ระบบก่อนใช้งาน', 401);
        }

        if ($allowedRoles === null) {
            return;
        }

        $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
        $currentRole = (string)($_SESSION['user_role'] ?? '');
        if (!in_array($currentRole, $roles, true)) {
            ajax_error('ไม่มีสิทธิ์ในการดำเนินการนี้', 403);
        }
    }
}
