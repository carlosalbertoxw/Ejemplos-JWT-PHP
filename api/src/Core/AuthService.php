<?php

namespace Api\Core;

use Api\Data\RefreshTokenRepository;
use Api\Data\UserRepository;

/**
 * Orquesta el flujo de autenticación: login, renovación con rotación
 * de refresh tokens (con detección de reuso) y logout.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly JwtService $jwt,
        private readonly PasswordHasher $hasher,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
    ) {}

    /**
     * Valida credenciales y devuelve el par de tokens, o null si son inválidas.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function login(string $username, string $password): ?array
    {
        $user = $this->users->findByUsername($username);

        // Verificar SIEMPRE contra un hash real (el del usuario o el señuelo,
        // con el mismo coste) para que el tiempo de respuesta no revele si
        // el usuario existe.
        $hash = $user['password_hash'] ?? $this->hasher->decoyHash();

        if (!$this->hasher->verify($password, $hash) || $user === null) {
            return null;
        }

        // Hashes creados con otro coste (p. ej. bases sembradas con una
        // versión anterior de PHP) se rehacen aprovechando que ahora se
        // conoce la contraseña: así todos quedan con el coste del señuelo.
        if ($this->hasher->needsRehash($hash)) {
            $this->users->updatePasswordHash((int) $user['id'], $this->hasher->hash($password));
        }

        return $this->issueTokenPair($user);
    }

    /**
     * Renueva el par de tokens a partir de un refresh token válido.
     *
     * El token usado se revoca en el acto (rotación). Si llega un token que
     * ya fue rotado/revocado, se asume robo (alguien lo usó dos veces) y se
     * revocan TODOS los refresh tokens del usuario: tanto el atacante como
     * el usuario legítimo pierden la sesión y deben volver a autenticarse.
     *
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}|null
     */
    public function refresh(string $refreshToken): ?array
    {
        // Canje atómico: comprueba y revoca en una sola operación (ver
        // RefreshTokenRepository::consume). Si falla, el token no existe,
        // expiró o ya se había usado.
        if (!$this->refreshTokens->consume($refreshToken)) {
            $this->detectReuse($refreshToken);
            return null;
        }

        $stored = $this->refreshTokens->find($refreshToken);
        $user = $stored === null ? null : $this->users->findById((int) $stored['user_id']);
        if ($user === null) {
            return null;
        }

        return $this->issueTokenPair($user);
    }

    /**
     * Revoca el refresh token para que no pueda volver a usarse (cierra solo
     * la sesión a la que pertenece). Revocarlo dos veces no tiene efecto.
     */
    public function logout(string $refreshToken): void
    {
        $this->refreshTokens->revoke($refreshToken);
    }

    /**
     * Detección de reuso: si el token existe pero está revocado, alguien
     * está reutilizando un token ya rotado — se revoca toda la familia.
     */
    private function detectReuse(string $refreshToken): void
    {
        $stored = $this->refreshTokens->find($refreshToken);
        if ($stored !== null && (int) $stored['revoked'] === 1) {
            $this->refreshTokens->revokeAllForUser((int) $stored['user_id']);
            error_log(sprintf(
                'Reuso de refresh token detectado (user_id=%d): se revocaron todas sus sesiones',
                (int) $stored['user_id'],
            ));
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    private function issueTokenPair(array $user): array
    {
        // Mantenimiento oportunista: eliminar tokens ya expirados.
        $this->refreshTokens->purgeExpired();

        $accessToken = $this->jwt->create([
            'sub' => (string) $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ]);

        $refreshToken = $this->refreshTokens->create((int) $user['id'], $this->refreshTokenTtl);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenTtl,
        ];
    }
}
