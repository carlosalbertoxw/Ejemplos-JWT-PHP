<?php

namespace WebApp\Api;

/**
 * Transporte HTTP real con cURL.
 */
final class CurlTransport implements HttpTransport
{
    public function send(string $method, string $url, array $headers, ?string $body): ApiResponse
    {
        if ($method === '') {
            throw new \InvalidArgumentException('El método HTTP no puede estar vacío');
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // Si la API está caída conviene fallar rápido (3 s para conectar)
        // en vez de dejar la página en blanco los 10 s del timeout total.
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($curl);
        $error = curl_error($curl);

        if ($errno !== 0) {
            // La API no respondió (contenedor caído, red, etc.).
            return new ApiResponse(0, ['error' => 'No se pudo contactar la API: ' . $error]);
        }

        $data = json_decode((string) $raw, true);
        return new ApiResponse($status, is_array($data) ? $data : []);
    }
}
