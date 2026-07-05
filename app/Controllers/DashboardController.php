<?php

declare(strict_types=1);

namespace App\Controllers;

final class DashboardController
{
    public function home(): void
    {
        $role = (string) (current_user()['role'] ?? 'user');
        redirect(user_home_path($role));
    }

    public function user(): void
    {
        view('dashboard/user/index', $this->viewData());
    }

    public function admin(): void
    {
        view('dashboard/admin/index', $this->viewData());
    }

    public function superadmin(): void
    {
        view('dashboard/superadmin/index', $this->viewData());
    }

    private function viewData(): array
    {
        return [
            'user' => current_user(),
            'csrfToken' => csrf_token(),
        ];
    }
}
