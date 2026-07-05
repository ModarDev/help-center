<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Help Center</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body>
<div class="dashboard-shell">
    <div class="dashboard-card">
        <h1>Admin Dashboard</h1>
        <p>You are logged in as an admin.</p>

        <div class="user-meta">
            <strong>Name:</strong> <?= e((string) ($user['name'] ?? '-')) ?><br>
            <strong>Email:</strong> <?= e((string) ($user['email'] ?? '-')) ?><br>
            <strong>Role:</strong> <?= e((string) ($user['role'] ?? '-')) ?>
        </div>

        <form action="<?= e(app_url('logout')) ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <button type="submit">Logout</button>
        </form>
    </div>
</div>
</body>
</html>
