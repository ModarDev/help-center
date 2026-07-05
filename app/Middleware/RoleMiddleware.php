<?php

declare(strict_types=1);

namespace App\Middleware;

final class RoleMiddleware
{
    public static function handle(string $role): bool
    {
        $user = current_user();

        if ($user === null) {
            redirect('login');
        }

        if (($user['role'] ?? '') !== $role) {
            http_response_code(403);
            echo '403 Forbidden';
            return false;
        }

        return true;
    }
}
