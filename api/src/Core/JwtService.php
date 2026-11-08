<?php

namespace Api\Core;

/**
 * Creación y validación de JSON Web Tokens (JWT) en PHP puro.
 *
 * Implementa el algoritmo HS256 (HMAC-SHA256) según RFC 7519:
 *   token = base64url(header) . "." . base64url(payload) . "." . base64url(firma)
 *
 * No usa ninguna librería externa para que el mecanismo quede a la vista.
 */
final class JwtService
{
    private const ALGORITHM = 'HS256';

    // Tolerancia (segundos) para diferencias de reloj al validar exp/nbf.
    private const LEEWAY = 30;

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly string $audience,
        private readonly int $ttlSeconds,
    ) {
        // HS256 con una clave corta se puede romper por fuerza bruta offline:
        // el RFC 7518 exige una clave de al menos 256 bits (32 bytes).
        if (strlen($secret) < JwtSecret::MIN_BYTES) {
            throw new \InvalidArgumentException(
                'La clave de firma debe tener al menos ' . JwtSecret::MIN_BYTES . ' bytes'
            );
        }
    }

    /**
     * Crea un token firmado con los claims de negocio recibidos más los
     * claims estándar: iss, aud, iat, nbf, exp y jti.
     */
    public function create(array $claims): string
    {
        $now = time();

        $payload = array_merge($claims, [
            'iss' => $this->issuer,          // quién emite el token
            'aud' => $this->audience,        // para quién es válido
            'iat' => $now,                   // momento de emisión
            'nbf' => $now,                   // no válido antes de
            'exp' => $now + $this->ttlSeconds, // expiración
            'jti' => bin2hex(random_bytes(8)), // identificador único del token
        ]);

        $header = ['alg' => self::ALGORITHM, 'typ' => 'JWT'];

        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign("$headerB64.$payloadB64");

        return "$headerB64.$payloadB64." . $this->base64UrlEncode($signature);
    }

    /**
     * Valida un token y devuelve sus claims.
     *
     * Comprueba, en este orden: estructura, algoritmo, firma, vigencia
     * (exp / nbf) y emisor/audiencia.
     *
     * @throws JwtException si el token no es válido.
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JwtException('Estructura de token inválida');
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== self::ALGORITHM) {
            // Rechazar cualquier algoritmo distinto evita el ataque "alg: none".
            throw new JwtException('Algoritmo no soportado');
        }

        $expected = $this->sign("$headerB64.$payloadB64");
        $actual = $this->base64UrlDecode($signatureB64);
        // hash_equals compara en tiempo constante (evita timing attacks).
        if (!hash_equals($expected, $actual)) {
            throw new JwtException('Firma inválida');
        }

        $claims = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($claims)) {
            throw new JwtException('Payload inválido');
        }

        $now = time();
        // exp es obligatorio: un token firmado sin expiración sería eterno.
        if (!isset($claims['exp']) || !is_int($claims['exp'])) {
            throw new JwtException('Falta el claim exp');
        }
        if ($now > $claims['exp'] + self::LEEWAY) {
            throw new JwtException('Token expirado');
        }
        if (isset($claims['nbf']) && $now < $claims['nbf'] - self::LEEWAY) {
            throw new JwtException('Token aún no válido');
        }
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new JwtException('Emisor inválido');
        }
        if (($claims['aud'] ?? null) !== $this->audience) {
            throw new JwtException('Audiencia inválida');
        }

        return $claims;
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    /** Base64 URL-safe (RFC 4648 §5): sin relleno "=" y con -_ en vez de +/. */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new JwtException('Codificación base64url inválida');
        }
        return $decoded;
    }
}
