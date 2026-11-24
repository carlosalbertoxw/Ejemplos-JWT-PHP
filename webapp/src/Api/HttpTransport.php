<?php

namespace WebApp\Api;

/**
 * Envío de una petición HTTP ya armada. Separarlo de ApiHttpClient permite
 * probar el flujo 401 -> refresh -> reintento con un transporte falso.
 */
interface HttpTransport
{
    /**
     * @param list<string> $headers Encabezados en formato "Nombre: valor".
     */
    public function send(string $method, string $url, array $headers, ?string $body): ApiResponse;
}
