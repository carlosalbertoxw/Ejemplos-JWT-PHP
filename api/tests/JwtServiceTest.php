<?php

declare(strict_types=1);

use Api\Core\JwtException;
use Api\Core\JwtService;

/**
 * Pruebas de la pieza más delicada del proyecto: la creación y validación
 * de JWT escrita a mano. Cubre el camino feliz y los ataques clásicos
 * (firma manipulada, "alg: none", secreto incorrecto, token sin exp…).
 */
final class JwtServiceTest extends TestCase
{
    private const SECRET = 'secreto-de-pruebas-de-al-menos-32-bytes';
    private const ISSUER = 'test-issuer';
    private const AUDIENCE = 'test-audience';

    private JwtService $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JwtService(self::SECRET, self::ISSUER, self::AUDIENCE, 900);
    }

    public function testRechazaUnaClaveDeMenosDe32Bytes(): void
    {
        $this->expectException(
            \InvalidArgumentException::class,
            fn() => new JwtService('corta', self::ISSUER, self::AUDIENCE, 900),
        );
    }

    public function testCreaYVerificaUnTokenConSusClaims(): void
    {
        $token = $this->jwt->create(['sub' => '1', 'username' => 'admin', 'role' => 'Admin']);

        $claims = $this->jwt->verify($token);

        $this->assertSame('1', $claims['sub']);
        $this->assertSame('admin', $claims['username']);
        $this->assertSame('Admin', $claims['role']);
        $this->assertSame(self::ISSUER, $claims['iss']);
        $this->assertSame(self::AUDIENCE, $claims['aud']);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('jti', $claims);
        $this->assertTrue($claims['exp'] > time(), 'exp debe estar en el futuro');
    }

    public function testRechazaUnPayloadManipulado(): void
    {
        $token = $this->jwt->create(['sub' => '2', 'role' => 'User']);
        [$header, , $signature] = explode('.', $token);

        // El atacante se cambia el rol pero no puede recalcular la firma.
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '2', 'role' => 'Admin',
            'iss' => self::ISSUER, 'aud' => self::AUDIENCE,
            'iat' => time(), 'nbf' => time(), 'exp' => time() + 900,
        ]));
        $forged = "$header.$payload.$signature";

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($forged), 'Firma inválida');
    }

    public function testRechazaElAlgoritmoNone(): void
    {
        // Ataque clásico: header con "alg": "none" y firma vacía.
        $header = $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => '1', 'role' => 'Admin',
            'iss' => self::ISSUER, 'aud' => self::AUDIENCE, 'exp' => time() + 900,
        ]));
        $forged = "$header.$payload.";

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($forged), 'Algoritmo no soportado');
    }

    public function testRechazaUnTokenFirmadoConOtroSecreto(): void
    {
        $otherService = new JwtService('otro-secreto-distinto-de-al-menos-32-bytes', self::ISSUER, self::AUDIENCE, 900);
        $token = $otherService->create(['sub' => '1']);

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($token), 'Firma inválida');
    }

    public function testRechazaUnTokenExpirado(): void
    {
        // TTL negativo mayor que el leeway (30 s): nace expirado.
        $expiredIssuer = new JwtService(self::SECRET, self::ISSUER, self::AUDIENCE, -120);
        $token = $expiredIssuer->create(['sub' => '1']);

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($token), 'Token expirado');
    }

    public function testElLeewayToleraUnaExpiracionReciente(): void
    {
        // Expiró hace 10 s, dentro de la tolerancia de 30 s por desfase de reloj.
        $justExpired = new JwtService(self::SECRET, self::ISSUER, self::AUDIENCE, -10);
        $token = $justExpired->create(['sub' => '1']);

        $claims = $this->jwt->verify($token);

        $this->assertSame('1', $claims['sub']);
    }

    public function testRechazaUnEmisorIncorrecto(): void
    {
        $otherIssuer = new JwtService(self::SECRET, 'emisor-malicioso', self::AUDIENCE, 900);
        $token = $otherIssuer->create(['sub' => '1']);

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($token), 'Emisor inválido');
    }

    public function testRechazaUnaAudienciaIncorrecta(): void
    {
        $otherAudience = new JwtService(self::SECRET, self::ISSUER, 'otra-app', 900);
        $token = $otherAudience->create(['sub' => '1']);

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($token), 'Audiencia inválida');
    }

    public function testRechazaUnTokenSinExp(): void
    {
        // Un token bien firmado pero sin expiración sería eterno: se rechaza.
        $token = $this->sign([
            'sub' => '1',
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
        ]);

        $this->expectException(JwtException::class, fn() => $this->jwt->verify($token), 'Falta el claim exp');
    }

    public function testRechazaUnaEstructuraInvalida(): void
    {
        $this->expectException(
            JwtException::class,
            fn() => $this->jwt->verify('no-es.un-jwt'),
            'Estructura de token inválida',
        );
    }

    public function testRechazaBase64Invalido(): void
    {
        $this->expectException(JwtException::class, fn() => $this->jwt->verify('!!!.@@@.###'));
    }

    /** Firma un payload arbitrario con el mismo secreto (para forjar casos). */
    private function sign(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "$header.$body", self::SECRET, true);

        return "$header.$body." . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
