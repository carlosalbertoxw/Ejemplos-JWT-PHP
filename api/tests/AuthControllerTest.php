<?php

declare(strict_types=1);

use Api\Controllers\AuthController;
use Api\Core\AuthService;
use Api\Core\JwtService;
use Api\Core\PasswordHasher;
use Api\Core\RateLimiter;
use Api\Data\Database;
use Api\Data\RateLimitRepository;
use Api\Data\RefreshTokenRepository;
use Api\Data\UserRepository;
use Api\Http\Request;
use Api\Http\Response;

/**
 * Pruebas del controlador de autenticación: límites de intentos por cuenta
 * y por IP, y logout sin access token.
 */
final class AuthControllerTest extends TestCase
{
    private const ACCOUNT_MAX = 3;
    private const IP_MAX = 5;

    private AuthController $controller;

    protected function setUp(): void
    {
        $pdo = (new Database(':memory:'))->pdo();
        $auth = new AuthService(
            new UserRepository($pdo),
            new RefreshTokenRepository($pdo),
            new JwtService('secreto-de-pruebas-de-al-menos-32-bytes', 'iss', 'aud', 900),
            new PasswordHasher(),
            900,
            3600,
        );
        $attempts = new RateLimitRepository($pdo);
        $this->controller = new AuthController(
            $auth,
            new RateLimiter($attempts, self::ACCOUNT_MAX, 60),
            new RateLimiter($attempts, self::IP_MAX, 60),
        );
    }

    public function testBloqueaLaCuentaTrasVariosFallosAunqueCambieLaIp(): void
    {
        for ($i = 0; $i < self::ACCOUNT_MAX; $i++) {
            $this->login('admin', 'mala', "10.0.0.$i");
        }

        $response = $this->login('admin', 'Admin123!', '10.0.0.99');

        $this->assertSame(429, $response->status());
    }

    public function testBloqueaLaIpQuePruebaMuchasCuentas(): void
    {
        for ($i = 0; $i < self::IP_MAX; $i++) {
            $this->login("usuario$i", 'mala', '10.0.0.1');
        }

        // Ninguna cuenta llegó a su límite, pero la IP sí.
        $this->assertSame(429, $this->login('otro', 'mala', '10.0.0.1')->status());
        $this->assertSame(200, $this->login('user', 'User123!', '10.0.0.2')->status());
    }

    public function testElLogoutNoRequiereAccessToken(): void
    {
        $tokens = $this->login('user', 'User123!', '10.0.0.1')->data();

        $logout = $this->controller->logout($this->request(['refresh_token' => $tokens['refresh_token']]));
        $refresh = $this->controller->refresh($this->request(['refresh_token' => $tokens['refresh_token']]));

        $this->assertSame(200, $logout->status());
        $this->assertSame(401, $refresh->status(), 'El refresh token debe quedar revocado');
    }

    private function login(string $username, string $password, string $ip): Response
    {
        return $this->controller->login(
            $this->request(['username' => $username, 'password' => $password], $ip)
        );
    }

    /** @param array<string, string> $body */
    private function request(array $body, string $ip = '10.0.0.1'): Request
    {
        return new Request(method: 'POST', path: '/', body: $body, bearerToken: null, ip: $ip);
    }
}
