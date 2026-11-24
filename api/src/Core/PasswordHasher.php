<?php

namespace Api\Core;

/**
 * Hashing de contraseñas usando las funciones nativas de PHP
 * (bcrypt con salt aleatorio y verificación en tiempo constante).
 */
final class PasswordHasher
{
    /**
     * Coste de bcrypt fijado explícitamente. El valor por defecto de PHP
     * cambia entre versiones (10 hasta PHP 8.3, 12 desde PHP 8.4): fijarlo
     * evita que los hashes nuevos y el señuelo queden con costes distintos.
     */
    public const COST = 12;

    /**
     * Hash bcrypt VÁLIDO de una cadena descartable, con el mismo coste que
     * los hashes reales. Cuando el usuario no existe se verifica la
     * contraseña contra este señuelo para que el tiempo de respuesta sea el
     * mismo que con un usuario real y no se pueda deducir qué usuarios
     * existen (enumeración por tiempo). Si cambia COST, hay que regenerarlo:
     *   php -r 'echo password_hash("x", PASSWORD_BCRYPT, ["cost" => 12]);'
     */
    private const DECOY_HASH = '$2y$12$Km1QeeS4w.pPYoAoN/nweePkpQ0Bi6KIAcIp2AuD8hby96RFhJcGW';

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Indica si un hash se creó con otro algoritmo o coste (p. ej. con el
     * coste por defecto de una versión anterior de PHP) y conviene rehacerlo.
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    public function decoyHash(): string
    {
        return self::DECOY_HASH;
    }
}
