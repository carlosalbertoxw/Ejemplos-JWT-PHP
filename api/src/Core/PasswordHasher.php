<?php

namespace Api\Core;

/**
 * Hashing de contraseñas usando las funciones nativas de PHP
 * (bcrypt con salt aleatorio y verificación en tiempo constante).
 */
final class PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
