<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;

final class AuthController
{
    public function showLogin(): void
    {
        if (is_logged_in()) {
            $role = (string) (current_user()['role'] ?? 'user');
            redirect(user_home_path($role));
        }

        view('auth/login', [
            'csrfToken' => csrf_token(),
        ]);
    }

    public function login(): void
    {
        if (!csrf_validate($_POST['csrf_token'] ?? null)) {
            json_response([
                'success' => false,
                'message' => 'Invalid CSRF token.',
            ], 419);
        }

        if ($this->isLocked()) {
            json_response([
                'success' => false,
                'message' => 'Too many attempts. Try again in a few minutes.',
            ], 429);
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->addFailedAttempt();
            json_response([
                'success' => false,
                'message' => 'Email or password is invalid.',
            ], 422);
        }

        $model = new User();
        $user = $model->findByEmail($email);

        $isValidUser = is_array($user)
            && ($user['is_active'] ?? 0) == 1
            && password_verify($password, (string) ($user['password'] ?? ''));

        if (!$isValidUser) {
            $this->addFailedAttempt();
            json_response([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $this->clearFailedAttempts();
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];

        json_response([
            'success' => true,
            'message' => 'Login successful.',
            'redirect' => app_url(user_home_path((string) $user['role'])),
        ]);
    }

    public function logout(): void
    {
        if (!csrf_validate($_POST['csrf_token'] ?? null)) {
            redirect('login');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        redirect('login');
    }

    private function isLocked(): bool
    {
        $count = (int) ($_SESSION['login_failed_count'] ?? 0);
        $last = (int) ($_SESSION['login_failed_at'] ?? 0);

        if ($count < 5) {
            return false;
        }

        return (time() - $last) < 300;
    }

    private function addFailedAttempt(): void
    {
        $_SESSION['login_failed_count'] = (int) ($_SESSION['login_failed_count'] ?? 0) + 1;
        $_SESSION['login_failed_at'] = time();
    }

    private function clearFailedAttempts(): void
    {
        unset($_SESSION['login_failed_count'], $_SESSION['login_failed_at']);
    }
}
