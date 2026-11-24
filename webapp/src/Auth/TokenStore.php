<?php

namespace WebApp\Auth;

/**
 * Guarda el par de tokens y los datos del usuario en la sesión PHP.
 *
 * Los tokens viven solo en el servidor (la cookie de sesión es lo único
 * que viaja al navegador), a diferencia de guardarlos en localStorage.
 */
final class TokenStore
{
    /** @param array<string, mixed> $tokens */
    public function save(array $tokens): void
    {
        $_SESSION['access_token'] = $tokens['access_token'];
        $_SESSION['refresh_token'] = $tokens['refresh_token'];
    }

    public function accessToken(): ?string
    {
        return $_SESSION['access_token'] ?? null;
    }

    public function refreshToken(): ?string
    {
        return $_SESSION['refresh_token'] ?? null;
    }

    /** @param array<string, mixed> $user */
    public function saveUser(array $user): void
    {
        $_SESSION['user'] = $user;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return $this->accessToken() !== null;
    }

    /** Limpia la sesión por completo (logout). */
    public function clear(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
