<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    return rtrim(BASE_PATH, '/\\') . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function config(string $key, mixed $default = null): mixed
{
    static $cache = [];

    $parts = explode('.', $key, 2);
    $file = $parts[0] ?? '';
    $item = $parts[1] ?? null;

    if ($file === '') {
        return $default;
    }

    if (!array_key_exists($file, $cache)) {
        $configPath = base_path('config/' . $file . '.php');
        $cache[$file] = is_file($configPath) ? require $configPath : [];
    }

    if ($item === null) {
        return $cache[$file] ?? $default;
    }

    return $cache[$file][$item] ?? $default;
}

function app_url(string $path = ''): string
{
    $base = rtrim((string) config('app.base_url', ''), '/');
    $path = trim($path, '/');

    if ($path === '') {
        return $base !== '' ? $base : '/';
    }

    return ($base !== '' ? $base : '') . '/' . $path;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    $key = (string) config('app.csrf_key', 'csrf_token');

    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$key];
}

function csrf_validate(?string $token): bool
{
    $key = (string) config('app.csrf_key', 'csrf_token');
    $sessionToken = $_SESSION[$key] ?? '';

    if (!is_string($token) || $token === '' || !is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? $user : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function json_response(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function user_home_path(string $role): string
{
    return match ($role) {
        'admin' => 'dashboard/admin',
        'superadmin' => 'dashboard/superadmin',
        default => 'dashboard/user',
    };
}

function view(string $template, array $data = []): never
{
    $file = base_path('app/Views/' . $template . '.php');

    if (!is_file($file)) {
        http_response_code(500);
        echo 'View not found.';
        exit;
    }

    extract($data, EXTR_SKIP);
    require $file;
    exit;
}
