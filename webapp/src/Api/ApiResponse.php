<?php

namespace WebApp\Api;

/**
 * Resultado de una llamada a la API: código de estado + cuerpo decodificado.
 */
final class ApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $data,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** Mensaje de error devuelto por la API, si lo hay. */
    public function error(): string
    {
        return (string) ($this->data['error'] ?? 'Error desconocido');
    }
}
