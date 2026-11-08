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
 * El login combina dos límites: por cuenta y por IP.
 */
final class AuthController
{
    // Límites de tamaño de entrada: evitan payloads absurdos y dejan claro
    // el contrato. bcrypt además solo considera los primeros 72 bytes.
    private const MAX_USERNAME_LENGTH = 100;
    private const MAX_PASSWORD_LENGTH = 200;

    public function __construct(
        private readonly AuthService $auth,
        /** Límite estricto por cuenta. */
        private readonly RateLimiter $accountLimiter,
        /** Límite más holgado por IP, compartido por todas las cuentas. */
        private readonly RateLimiter $ipLimiter,
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

        // Dos contadores de fallos:
        //  - por cuenta: frena la fuerza bruta contra un usuario aunque el
        //    atacante rote de IP;
        //  - por IP: frena el "password spraying" (probar una contraseña
        //    contra muchas cuentas desde el mismo origen).
        $accountKey = 'login:user:' . strtolower($username);
        $ipKey = 'login:ip:' . $request->ip;
        foreach ([[$this->accountLimiter, $accountKey], [$this->ipLimiter, $ipKey]] as [$limiter, $key]) {
            if ($limiter->tooManyAttempts($key)) {
                return $this->tooManyAttempts($limiter, $key);
            }
        }

        $tokens = $this->auth->login($username, $password);
        if ($tokens === null) {
            $this->accountLimiter->recordFailure($accountKey);
            $this->ipLimiter->recordFailure($ipKey);
            return Response::error('Credenciales inválidas', 401);
        }

        // Solo se limpia el contador de la cuenta: si se limpiara el de la IP,
        // un atacante con una cuenta propia podría reiniciarlo a voluntad.
        $this->accountLimiter->clear($accountKey);
        return Response::json($tokens);
    }

    /** POST /api/auth/refresh — rota el refresh token y emite un nuevo par. */
    public function refresh(Request $request): Response
    {
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken === '') {
            return Response::error('refresh_token es obligatorio', 400);
        }

        // Aquí el límite es por IP (con el umbral holgado): impide adivinar
        // refresh tokens por fuerza bruta sin depender de conocer al usuario.
        // No se limpia tras un éxito: si no, un atacante con un token propio
        // podría intercalar canjes válidos para reiniciar el contador.
        $key = 'refresh:ip:' . $request->ip;
        if ($this->ipLimiter->tooManyAttempts($key)) {
            return $this->tooManyAttempts($this->ipLimiter, $key);
        }

        $tokens = $this->auth->refresh($refreshToken);
        if ($tokens === null) {
            $this->ipLimiter->recordFailure($key);
            return Response::error('Refresh token inválido, expirado o revocado', 401);
        }

        return Response::json($tokens);
    }

    /**
     * POST /api/auth/logout — revoca el refresh token enviado (cierra solo
     * esta sesión; las de otros dispositivos siguen activas).
     *
     * Es público: quien tiene el refresh token ya puede usarlo, así que
     * poseerlo basta para revocarlo. No exigir access token evita que el
     * cliente tenga que renovarlo (y rotar el refresh token) justo al salir.
     * Responde 200 aunque el token no exista, para no revelar su validez.
     */
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

    private function tooManyAttempts(RateLimiter $limiter, string $key): Response
    {
        return Response::error('Demasiados intentos; espera antes de reintentar', 429)
            ->withHeader('Retry-After', (string) $limiter->retryAfter($key));
    }
}
