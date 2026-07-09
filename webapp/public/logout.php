<?php

/**
 * Cierra la sesión: revoca el refresh token en la API
 * (POST /api/auth/logout) y limpia la sesión local.
 *
 * Solo acepta POST con token CSRF (el formulario está en la barra de
 * navegación): un enlace GET podría dispararse desde otro sitio.
 */

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

requireValidCsrf();

$authClient->logout();

header('Location: /index.php');
exit;
