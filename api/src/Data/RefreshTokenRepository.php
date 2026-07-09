<?php

namespace Api\Data;

/**
 * Almacenamiento de refresh tokens.
 *
 * Nunca se guarda el token en claro: se guarda su hash SHA-256, de modo
 * que un vuelco de la base de datos no permita suplantar sesiones.
 */
final class RefreshTokenRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /** Genera un refresh token aleatorio, lo persiste y lo devuelve en claro. */
    public function create(int $userId, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32)); // 64 caracteres hex, imposible de adivinar
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (token_hash, user_id, expires_at, created_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$this->hash($token), $userId, time() + $ttlSeconds, time()]);
        return $token;
    }

    /** Devuelve el registro solo si existe, no está revocado y no expiró. */
    public function findValid(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM refresh_tokens
             WHERE token_hash = ? AND revoked = 0 AND expires_at > ?'
        );
        $stmt->execute([$this->hash($token), time()]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Devuelve el registro sin filtrar estado (para detectar reuso), o null. */
    public function find(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refresh_tokens WHERE token_hash = ?');
        $stmt->execute([$this->hash($token)]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Marca un token como revocado (logout o rotación). */
    public function revoke(string $token): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = ?'
        );
        $stmt->execute([$this->hash($token)]);
    }

    /** Revoca TODOS los tokens de un usuario (reacción ante reuso detectado). */
    public function revokeAllForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
    }

    /**
     * Elimina los tokens ya expirados para que la tabla no crezca sin límite.
     * Los revocados aún vigentes se conservan: son la evidencia que permite
     * detectar el reuso de un token rotado.
     */
    public function purgeExpired(): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE expires_at <= ?');
        $stmt->execute([time()]);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
