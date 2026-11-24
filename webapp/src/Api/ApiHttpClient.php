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
    private readonly HttpTransport $transport;

    public function __construct(
        private readonly string $baseUrl,
        private readonly TokenStore $tokens,
        ?HttpTransport $transport = null,
    ) {
        $this->transport = $transport ?? new CurlTransport();
    }

    /**
     * Petición pública (sin token).
     *
     * @param array<string, mixed>|null $body
     */
    public function request(string $method, string $path, ?array $body = null): ApiResponse
    {
        return $this->send($method, $path, $body, null);
    }

    /**
     * Petición autenticada con manejo automático de expiración.
     *
     * @param array<string, mixed>|null $body
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

    /** @param array<string, mixed>|null $body */
    private function send(string $method, string $path, ?array $body, ?string $bearerToken): ApiResponse
    {
        $headers = ['Accept: application/json'];
        if ($bearerToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        $json = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $json = json_encode($body, JSON_THROW_ON_ERROR);
        }

        return $this->transport->send($method, rtrim($this->baseUrl, '/') . $path, $headers, $json);
    }
}
