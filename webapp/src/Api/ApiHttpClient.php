<?php

namespace WebApp\Api;

use WebApp\Auth\TokenStore;

/**
 * Canal HTTP compartido hacia la API (cURL).
 *
 * Para las peticiones autenticadas implementa el flujo
 * 401 -> refresh -> reintento: si el access token expiró, intenta
 * renovarlo una sola vez con el refresh token y repite la petición.
 */
final class ApiHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly TokenStore $tokens,
    ) {
    }

    /** Petición pública (sin token). */
    public function request(string $method, string $path, ?array $body = null): ApiResponse
    {
        return $this->send($method, $path, $body, null);
    }

    /**
     * Petición autenticada con manejo automático de expiración.
     */
    public function authenticatedRequest(string $method, string $path, ?array $body = null): ApiResponse
    {
        $response = $this->send($method, $path, $body, $this->tokens->accessToken());

        if ($response->status === 401 && $this->tryRefresh()) {
            // El access token expiró pero se pudo renovar: reintentar una vez.
            $response = $this->send($method, $path, $body, $this->tokens->accessToken());
        }

        return $response;
    }

    /** Intenta renovar el par de tokens. Devuelve true si lo logró. */
    private function tryRefresh(): bool
    {
        $refreshToken = $this->tokens->refreshToken();
        if ($refreshToken === null) {
            return false;
        }

        $response = $this->send('POST', '/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ], null);

        if (!$response->ok()) {
            // Refresh inválido o revocado: la sesión ya no sirve.
            $this->tokens->clear();
            return false;
        }

        $this->tokens->save($response->data);
        return true;
    }

    private function send(string $method, string $path, ?array $body, ?string $bearerToken): ApiResponse
    {
        $headers = ['Accept: application/json'];
        if ($bearerToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $options = [
            CURLOPT_URL => rtrim($this->baseUrl, '/') . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            // Si la API está caída conviene fallar rápido (3 s para conectar)
            // en vez de dejar la página en blanco los 10 s del timeout total.
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($errno !== 0) {
            // La API no respondió (contenedor caído, red, etc.).
            return new ApiResponse(0, ['error' => 'No se pudo contactar la API: ' . $error]);
        }

        $data = json_decode((string) $raw, true);
        return new ApiResponse($status, is_array($data) ? $data : []);
    }
}
