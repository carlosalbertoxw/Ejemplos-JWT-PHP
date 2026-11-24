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
use Api\Http\Request;
use Api\Http\Response;
use Api\Http\Router;

try {
    $config = require __DIR__ . '/../config/config.php';

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

    // Dos límites sobre la misma tabla: por cuenta y por IP (ver config.php).
    $attempts = new RateLimitRepository($pdo);
    $accountLimiter = new RateLimiter(
        $attempts,
        $config['rate_limit_max_attempts'],
        $config['rate_limit_window_seconds'],
    );
    $ipLimiter = new RateLimiter(
        $attempts,
        $config['rate_limit_ip_max_attempts'],
        $config['rate_limit_window_seconds'],
    );

    $authController = new AuthController($authService, $accountLimiter, $ipLimiter);
    $productController = new ProductController(new ProductRepository($pdo));

    // --- Rutas (ver config/routes.php) ---
    $router = new Router($jwt);
    (require __DIR__ . '/../config/routes.php')($router, $authController, $productController);

    $router->dispatch(Request::fromGlobals())->send();
} catch (\Throwable $e) {
    // Nunca exponer trazas internas al cliente.
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    Response::error('Error interno del servidor', 500)->send();
}
