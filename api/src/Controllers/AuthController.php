<?php

namespace Api\Controllers;

use Api\Core\AuthService;
use Api\Core\RateLimiter;
use Api\Http\Request;
use Api\Http\Response;

/**
 * Endpoints de autenticación: /api/auth/*
 *
 * Los endpoints públicos (login y refresh) están protegidos contra fuerza
 * bruta: tras varios intentos fallidos responden 429 con Retry-After.
 */
final class AuthController
{
    // Límites de tamaño de entrada: evitan payloads absurdos y dejan claro
    // el contrato. bcrypt además solo considera los primeros 72 bytes.
    private const MAX_USERNAME_LENGTH = 100;
    private const MAX_PASSWORD_LENGTH = 200;

    public function __construct(
        private readonly AuthService $auth,
        private readonly RateLimiter $limiter,
    ) {
    }

    /** POST /api/auth/login — valida credenciales y emite el par de tokens. */
    public function login(Request $request): Response
    {
        $username = $request->input('username');
        $password = $request->input('password');

        if ($username === '' || $password === '') {
            return Response::error('username y password son obligatorios', 400);
        }
        if (strlen($username) > self::MAX_USERNAME_LENGTH
            || strlen($password) > self::MAX_PASSWORD_LENGTH) {
            return Response::error('username o password demasiado largos', 400);
        }

        // El límite se aplica por IP + usuario: un atacante no bloquea al
        // resto de usuarios y el usuario legítimo no se bloquea desde otra IP.
        $key = 'login:' . $request->ip . ':' . strtolower($username);
        if ($this->limiter->tooManyAttempts($key)) {
            return $this->tooManyAttempts($key);
        }

        $tokens = $this->auth->login($username, $password);
        if ($tokens === null) {
            $this->limiter->recordFailure($key);
            return Response::error('Credenciales inválidas', 401);
        }

        $this->limiter->clear($key);
        return Response::json($tokens);
    }

    /** POST /api/auth/refresh — rota el refresh token y emite un nuevo par. */
    public function refresh(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken === '') {
            return Response::error('refresh_token es obligatorio', 400);
        }

        // Aquí el límite es por IP: impide adivinar refresh tokens por fuerza
        // bruta sin depender de conocer al usuario.
        $key = 'refresh:' . $request->ip;
        if ($this->limiter->tooManyAttempts($key)) {
            return $this->tooManyAttempts($key);
        }

        $tokens = $this->auth->refresh($refreshToken);
        if ($tokens === null) {
            $this->limiter->recordFailure($key);
            return Response::error('Refresh token inválido, expirado o revocado', 401);
        }

        $this->limiter->clear($key);
        return Response::json($tokens);
    }

    /** POST /api/auth/logout — revoca el refresh token del cliente. */
    public function logout(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken === '') {
            return Response::error('refresh_token es obligatorio', 400);
        }

        $this->auth->logout($refreshToken);
        return Response::json(['message' => 'Sesión cerrada']);
    }

    /** GET /api/auth/me — devuelve los claims del token autenticado. */
    public function me(Request $request): Response
    {
        return Response::json([
            'id' => $request->claims['sub'] ?? null,
            'username' => $request->claims['username'] ?? null,
            'role' => $request->claims['role'] ?? null,
            'token_expires_at' => $request->claims['exp'] ?? null,
        ]);
    }

    private function tooManyAttempts(string $key): Response
    {
        return Response::error('Demasiados intentos; espera antes de reintentar', 429)
            ->withHeader('Retry-After', (string) $this->limiter->retryAfter($key));
    }
}
