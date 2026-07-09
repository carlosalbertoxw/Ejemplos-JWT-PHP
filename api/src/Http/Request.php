<?php

namespace Api\Http;

/**
 * Representa la petición HTTP entrante.
 */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body,
        public readonly ?string $bearerToken,
        /** IP del cliente (para el rate limiting). */
        public readonly string $ip = '0.0.0.0',
        /** Claims del access token; los rellena el router si la ruta exige autenticación. */
        public ?array $claims = null,
    ) {
    }

    /** Construye la petición a partir de las superglobales de PHP. */
    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        $raw = file_get_contents('php://input');
        $body = json_decode($raw ?: '', true);

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: $path,
            body: is_array($body) ? $body : [],
            bearerToken: self::extractBearerToken(),
            ip: $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        );
    }

    /** Extrae el token del encabezado "Authorization: Bearer <token>". */
    private static function extractBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /** Devuelve un campo del cuerpo JSON como texto recortado. */
    public function input(string $key): string
    {
        $value = $this->body[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
