<?php

/**
 * Plantilla compartida: encabezado con navegación y pie de página.
 */

function renderHeader(string $title, ?array $user): void
{
    // Cabeceras de seguridad de toda página HTML de la aplicación.
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'");
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> · Ejemplo JWT PHP</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
<header class="topbar">
    <span class="brand">🔐 Ejemplo JWT PHP</span>
    <nav>
        <a href="/index.php">Inicio</a>
        <?php if ($user !== null): ?>
            <a href="/products.php">Productos</a>
            <?php if (($user['role'] ?? '') === 'Admin'): ?>
                <a href="/product-new.php">Nuevo producto</a>
            <?php endif; ?>
            <a href="/profile.php">Mi perfil</a>
            <!-- Logout por POST con token CSRF: un enlace GET podría
                 dispararse desde otro sitio sin intención del usuario. -->
            <form class="logout-form" method="post" action="/logout.php">
                <?= csrfField() ?>
                <button type="submit" class="user-chip">
                    Salir (<?= e($user['username'] ?? '') ?>)
                </button>
            </form>
        <?php else: ?>
            <a class="user-chip" href="/login.php">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <h1><?= e($title) ?></h1>
    <?php
}

function renderFooter(): void
{
    ?>
</main>
<footer>
    <p>Ejemplo educativo de autenticación con JWT — API REST y aplicación web en PHP puro.</p>
</footer>
</body>
</html>
    <?php
}
