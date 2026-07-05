# Help Center Login System (PHP + MySQL)

Role-based secure login system with AJAX and clean URLs.

## Stack
- PHP 8+
- MySQL (phpMyAdmin / XAMPP)
- Vanilla JavaScript (AJAX)

## Features
- Secure auth with `password_hash` / `password_verify`
- CSRF protection on login/logout
- Session hardening (`HttpOnly`, `SameSite`, session regeneration)
- Basic login rate limit per session
- Role dashboards: `user`, `admin`, `superadmin`
- Clean URL routing (no `.php` in URL)

## Folder Structure
- `app/Controllers`
- `app/Core`
- `app/Middleware`
- `app/Models`
- `app/Views`
- `config`
- `database`
- `public/assets`
- `routes`
- `storage/logs`

## Setup
1. Open phpMyAdmin and import `database/schema.sql`.
2. Confirm DB credentials in `config/database.php`.
3. Start Apache + MySQL in XAMPP.
4. Open: `http://localhost/help-center/login`

## Demo Accounts
- `user@example.com` / `Password@123`
- `admin@example.com` / `Password@123`
- `superadmin@example.com` / `Password@123`

## Notes
- If rewrite rules are disabled, enable Apache `mod_rewrite` and allow `.htaccess` override.
- `superadmin` was implemented from your requested `supperadmin` role spelling.
