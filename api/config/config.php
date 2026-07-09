<?php

/**
 * Configuración central de la API.
 *
 * Los valores sensibles se leen de variables de entorno (definidas en
 * docker-compose.yml o en un archivo .env) con un valor por defecto para
 * desarrollo local.
 */

$jwtSecret = getenv('JWT_SECRET');
if ($jwtSecret === false || $jwtSecret === '') {
    // Arrancar con la clave de desarrollo es aceptable en local, pero jamás
    // en producción: con la clave conocida cualquiera puede firmar tokens.
    // Se deja constancia en el log para que el descuido no pase inadvertido.
    error_log(
        'ADVERTENCIA: JWT_SECRET no está definida; usando la clave de '
        . 'desarrollo. No usar así en producción.'
    );
    $jwtSecret = 'clave-de-desarrollo-cambiar-en-produccion';
}

return [
    // Clave secreta para firmar los JWT (HS256). En producción debe ser
    // un valor largo y aleatorio, nunca versionado en el repositorio.
    'jwt_secret' => $jwtSecret,

    // Emisor y audiencia esperados en los tokens.
    'jwt_issuer' => getenv('JWT_ISSUER') ?: 'jwt-api-php',
    'jwt_audience' => getenv('JWT_AUDIENCE') ?: 'jwt-webapp-php',

    // Vida del access token (corta) y del refresh token (larga), en segundos.
    'access_token_ttl' => (int) (getenv('ACCESS_TOKEN_TTL') ?: 15 * 60),        // 15 minutos
    'refresh_token_ttl' => (int) (getenv('REFRESH_TOKEN_TTL') ?: 7 * 24 * 3600), // 7 días

    // Rate limiting de login/refresh: intentos fallidos permitidos por
    // ventana deslizante antes de responder 429.
    'rate_limit_max_attempts' => (int) (getenv('RATE_LIMIT_MAX_ATTEMPTS') ?: 5),
    'rate_limit_window_seconds' => (int) (getenv('RATE_LIMIT_WINDOW_SECONDS') ?: 15 * 60),

    // Ruta del archivo SQLite.
    'db_path' => getenv('DB_PATH') ?: __DIR__ . '/../data/app.sqlite',
];
