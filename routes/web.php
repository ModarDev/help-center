<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;

$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'home'], ['auth']);
$router->get('/dashboard/user', [DashboardController::class, 'user'], ['auth', 'role:user']);
$router->get('/dashboard/admin', [DashboardController::class, 'admin'], ['auth', 'role:admin']);
$router->get('/dashboard/superadmin', [DashboardController::class, 'superadmin'], ['auth', 'role:superadmin']);
