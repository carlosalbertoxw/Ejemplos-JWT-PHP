<?php

namespace WebApp\Api;

use WebApp\Auth\TokenStore;

/**
 * Cliente de los endpoints de autenticación de la API.
 */
final class AuthApiClient
{
    public function __construct(
        private readonly ApiHttpClient $http,
        private readonly TokenStore $tokens,
    ) {
    }

    /**
     * Inicia sesión: guarda los tokens y los datos del usuario en la sesión.
     * Devuelve null si todo salió bien, o el mensaje de error.
     */
    public function login(string $username, string $password): ?string
    {
        $response = $this->http->request('POST', '/api/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);

        if (!$response->ok()) {
            return $response->error();
        }

        $this->tokens->save($response->data);

        // Obtener los datos del usuario recién autenticado.
        $me = $this->http->authenticatedRequest('GET', '/api/auth/me');
        if ($me->ok()) {
            $this->tokens->saveUser($me->data);
        }

        return null;
    }

    /** Cierra sesión: revoca el refresh token en la API y limpia la sesión local. */
    public function logout(): void
    {
        $refreshToken = $this->tokens->refreshToken();
        if ($refreshToken !== null) {
            // Petición sin access token: el refresh token es la credencial.
            // Si fuera por el canal autenticado y el access token hubiera
            // expirado, el reintento tras el refresh revocaría el token viejo
            // y dejaría vivo el recién emitido.
            $this->http->request('POST', '/api/auth/logout', [
                'refresh_token' => $refreshToken,
            ]);
        }
        $this->tokens->clear();
    }

    /** Consulta los claims del usuario autenticado. */
    public function me(): ApiResponse
    {
        return $this->http->authenticatedRequest('GET', '/api/auth/me');
    }
}
