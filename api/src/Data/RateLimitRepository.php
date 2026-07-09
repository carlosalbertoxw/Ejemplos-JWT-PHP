<?php

namespace Api\Data;

/**
 * Almacenamiento de los intentos fallidos para el rate limiting
 * (tabla rate_limit_attempts: clave + timestamp de cada fallo).
 */
final class RateLimitRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function add(string $key, int $timestamp): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limit_attempts (attempt_key, attempted_at) VALUES (?, ?)'
        );
        $stmt->execute([$key, $timestamp]);
    }

    /** Cantidad de intentos de la clave desde el instante dado. */
    public function countSince(string $key, int $since): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limit_attempts
             WHERE attempt_key = ? AND attempted_at >= ?'
        );
        $stmt->execute([$key, $since]);
        return (int) $stmt->fetchColumn();
    }

    /** Timestamp del intento más antiguo dentro de la ventana, o null. */
    public function oldestSince(string $key, int $since): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(attempted_at) FROM rate_limit_attempts
             WHERE attempt_key = ? AND attempted_at >= ?'
        );
        $stmt->execute([$key, $since]);
        $oldest = $stmt->fetchColumn();
        return $oldest === null || $oldest === false ? null : (int) $oldest;
    }

    /** Borra los intentos de una clave (tras un intento exitoso). */
    public function deleteKey(string $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limit_attempts WHERE attempt_key = ?');
        $stmt->execute([$key]);
    }

    /** Borra los intentos anteriores al instante dado (fuera de toda ventana). */
    public function purgeBefore(int $timestamp): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rate_limit_attempts WHERE attempted_at < ?');
        $stmt->execute([$timestamp]);
    }
}
