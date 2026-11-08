<?php

/**
 * Configuración central de la API.
 *
 * Todo se lee de variables de entorno (definidas en docker-compose.yml o en
 * un archivo .env), con valores por defecto pensados para desarrollo local.
 * Requiere que el autoloader ya esté cargado (ver public/index.php).
 */

use Api\Core\JwtSecret;

$dataDir = getenv('DATA_DIR') ?: __DIR__ . '/../data';
$environment = getenv('APP_ENV') ?: 'development';

return [
    'environment' => $environment,

    // Clave secreta para firmar los JWT (HS256). Nunca se versiona: en
    // producción llega por JWT_SECRET; en desarrollo se genera sola.
    'jwt_secret' => JwtSecret::resolve(getenv('JWT_SECRET'), $environment, $dataDir . '/jwt-secret'),

    // Emisor y audiencia esperados en los tokens.
    'jwt_issuer' => getenv('JWT_ISSUER') ?: 'jwt-api-php',
    'jwt_audience' => getenv('JWT_AUDIENCE') ?: 'jwt-webapp-php',

    // Vida del access token (corta) y del refresh token (larga), en segundos.
    'access_token_ttl' => (int) (getenv('ACCESS_TOKEN_TTL') ?: 15 * 60),        // 15 minutos
    'refresh_token_ttl' => (int) (getenv('REFRESH_TOKEN_TTL') ?: 7 * 24 * 3600), // 7 días

    // Rate limiting de login/refresh (ventana deslizante, solo intentos fallidos):
    //  - por cuenta: frena la fuerza bruta contra un usuario aunque cambie de IP;
    //  - por IP: frena el "password spraying" (una contraseña contra muchas cuentas).
    'rate_limit_max_attempts' => (int) (getenv('RATE_LIMIT_MAX_ATTEMPTS') ?: 5),
    'rate_limit_ip_max_attempts' => (int) (getenv('RATE_LIMIT_IP_MAX_ATTEMPTS') ?: 20),
    'rate_limit_window_seconds' => (int) (getenv('RATE_LIMIT_WINDOW_SECONDS') ?: 15 * 60),

    // Ruta del archivo SQLite.
    'db_path' => getenv('DB_PATH') ?: $dataDir . '/app.sqlite',
];
