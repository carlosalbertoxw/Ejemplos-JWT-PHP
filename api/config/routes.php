<?php

/**
 * Declaración de las rutas de la API.
 *
 * Vive aparte de public/index.php para que las pruebas usen exactamente
 * las mismas rutas (p. ej. para comprobar que openapi.json las documenta).
 */

use Api\Controllers\AuthController;
use Api\Controllers\ProductController;
use Api\Http\Access;
use Api\Http\Request;
use Api\Http\Response;
use Api\Http\Router;

return static function (Router $router, AuthController $auth, ProductController $products): void {
    // Liveness: solo confirma que PHP responde (lo usa el healthcheck de Docker).
    $router->add('GET', '/health', fn(Request $r) => Response::json(['status' => 'ok']));

    $router->add('GET', '/', fn(Request $r) => Response::json([
        'name' => 'API de ejemplo JWT en PHP puro',
        'docs' => '/docs (Swagger UI) — especificación en /openapi.json',
        'endpoints' => [
            'POST /api/auth/login',
            'POST /api/auth/refresh',
            'POST /api/auth/logout',
            'GET  /api/auth/me       (Bearer)',
            'GET  /api/products/public',
            'GET  /api/products      (Bearer)',
            'POST /api/products      (Bearer, rol Admin)',
        ],
    ]));

    $router->add('POST', '/api/auth/login', [$auth, 'login']);
    $router->add('POST', '/api/auth/refresh', [$auth, 'refresh']);
    // El logout es público: el refresh token del cuerpo es la credencial. Así
    // el cliente no necesita un access token vigente (ni renovarlo) para salir.
    $router->add('POST', '/api/auth/logout', [$auth, 'logout']);
    $router->add('GET', '/api/auth/me', [$auth, 'me'], Access::Auth);

    $router->add('GET', '/api/products/public', [$products, 'publicList']);
    $router->add('GET', '/api/products', [$products, 'list'], Access::Auth);
    $router->add('POST', '/api/products', [$products, 'create'], Access::Auth, 'Admin');
};
