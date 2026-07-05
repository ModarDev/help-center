<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . 'app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . 'app/Core/helpers.php';

$sessionName = (string) config('app.session_name', 'app_session');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => (int) config('app.session_lifetime', 0),
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
