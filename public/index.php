<?php

declare(strict_types=1);

const BASE_PATH = __DIR__ . '/../';

require BASE_PATH . 'app/Core/bootstrap.php';

$router = new App\Core\Router();
require BASE_PATH . 'routes/web.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri);
