<?php

namespace Api\Http;

/**
 * Respuesta JSON de la API.
 *
 * Los controladores construyen y DEVUELVEN la respuesta; solo el front
 * controller la emite con send(). Así los controladores y el router se
 * pueden probar con tests unitarios sin que el proceso termine.
 */
final class Response
{
    /** @var array<string, string> Encabezados adicionales (p. ej. Retry-After). */
    private array $headers = [];

    private function __construct(
        private readonly int $status,
        private readonly mixed $data,
    ) {
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, $data);
    }

    public static function error(string $message, int $status): self
    {
        return self::json(['error' => $message], $status);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    /** Emite la respuesta al cliente y termina el proceso. */
    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        // Cabeceras de seguridad para toda respuesta de la API.
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
