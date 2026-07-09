<?php

/**
 * Front controller de la API.
 *
 * Toda petición entra por aquí (ver public/.htaccess): se construyen las
 * dependencias, se declaran las rutas y se despacha la petición. La
 * respuesta que devuelve el router se emite al final con send().
 */

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use Api\Controllers\AuthController;
use Api\Controllers\ProductController;
use Api\Core\AuthService;
use Api\Core\JwtService;
use Api\Core\PasswordHasher;
use Api\Core\RateLimiter;
use Api\Data\Database;
use Api\Data\ProductRepository;
use Api\Data\RateLimitRepository;
use Api\Data\RefreshTokenRepository;
use Api\Data\UserRepository;
use Api\Http\Access;
use Api\Http\Request;
use Api\Http\Response;
use Api\Http\Router;

$config = require __DIR__ . '/../config/config.php';

try {
    // --- Composición de dependencias (inyección manual) ---
    $database = new Database($config['db_path']);
    $pdo = $database->pdo();

    $jwt = new JwtService(
        $config['jwt_secret'],
        $config['jwt_issuer'],
        $config['jwt_audience'],
        $config['access_token_ttl'],
    );

    $authService = new AuthService(
        new UserRepository($pdo),
        new RefreshTokenRepository($pdo),
        $jwt,
        new PasswordHasher(),
        $config['access_token_ttl'],
        $config['refresh_token_ttl'],
    );

    $rateLimiter = new RateLimiter(
        new RateLimitRepository($pdo),
        $config['rate_limit_max_attempts'],
        $config['rate_limit_window_seconds'],
    );

    $authController = new AuthController($authService, $rateLimiter);
    $productController = new ProductController(new ProductRepository($pdo));

    // --- Rutas ---
    $router = new Router($jwt);

    $router->add('GET', '/', fn (Request $r) => Response::json([
        'name' => 'API de ejemplo JWT en PHP puro',
        'docs' => '/docs (Swagger UI) — especificación en /openapi.json',
        'endpoints' => [
            'POST /api/auth/login',
            'POST /api/auth/refresh',
            'POST /api/auth/logout   (Bearer)',
            'GET  /api/auth/me       (Bearer)',
            'GET  /api/products/public',
            'GET  /api/products      (Bearer)',
            'POST /api/products      (Bearer, rol Admin)',
        ],
    ]));

    $router->add('POST', '/api/auth/login', [$authController, 'login']);
    $router->add('POST', '/api/auth/refresh', [$authController, 'refresh']);
    $router->add('POST', '/api/auth/logout', [$authController, 'logout'], Access::Auth);
    $router->add('GET', '/api/auth/me', [$authController, 'me'], Access::Auth);

    $router->add('GET', '/api/products/public', [$productController, 'publicList']);
    $router->add('GET', '/api/products', [$productController, 'list'], Access::Auth);
    $router->add('POST', '/api/products', [$productController, 'create'], Access::Auth, 'Admin');

    $router->dispatch(Request::fromGlobals())->send();
} catch (\Throwable $e) {
    // Nunca exponer trazas internas al cliente.
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    Response::error('Error interno del servidor', 500)->send();
}
