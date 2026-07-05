<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e(app_url()) ?>">
    <title>Login | Help Center</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <h1>Sign in</h1>
        <p class="subtitle">Secure role-based access for Help Center</p>

        <form id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" id="submitBtn">Login</button>
        </form>

        <p class="hint">Default password in seed data: Password@123</p>
        <div id="message" class="message" aria-live="polite"></div>
    </div>
</div>
<script src="<?= e(app_url('assets/js/login.js')) ?>"></script>
</body>
</html>
