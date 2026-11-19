<?php

/**
 * Arranque común de todas las páginas: sesión endurecida, autoloader,
 * configuración y construcción de los clientes de la API.
 */

declare(strict_types=1);

// Cookie de sesión endurecida ANTES de abrir la sesión:
//  - httponly: JavaScript no puede leerla (mitiga robo de sesión vía XSS).
//  - samesite Lax: el navegador no la envía en peticiones cross-site (CSRF).
//  - secure: solo por HTTPS cuando la página se sirve por HTTPS.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

require __DIR__ . '/autoload.php';

use WebApp\Api\ApiHttpClient;
use WebApp\Api\AuthApiClient;
use WebApp\Api\ProductsApiClient;
use WebApp\Auth\TokenStore;

$config = require __DIR__ . '/../config/config.php';

$tokenStore = new TokenStore();
$apiClient = new ApiHttpClient($config['api_url'], $tokenStore);
$authClient = new AuthApiClient($apiClient, $tokenStore);
$productsClient = new ProductsApiClient($apiClient);

/** Escapa texto para HTML (evita XSS al imprimir datos de la API). */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Campo de un formulario POST como texto. Si llega como arreglo (p. ej.
 * "username[]=x") se trata como vacío: pasarlo a trim() o a htmlspecialchars()
 * lanzaría un TypeError y la página respondería con un error 500.
 */
function postString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Exige sesión iniciada; si no la hay, redirige al login. */
function requireLogin(WebApp\Auth\TokenStore $tokenStore): void
{
    if (!$tokenStore->isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/** Token anti-CSRF de la sesión (se genera una vez por sesión). */
function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto con el token anti-CSRF, para incluir en cada formulario. */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Valida el token anti-CSRF de un POST; si no coincide, corta con 403.
 * Así un sitio malicioso no puede enviar formularios en nombre del usuario.
 */
function requireValidCsrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        exit('Petición rechazada: token CSRF inválido o ausente.');
    }
}
