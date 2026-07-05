<?php

declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    public static function handle(): bool
    {
        if (!is_logged_in()) {
            redirect('login');
        }

        return true;
    }
}
