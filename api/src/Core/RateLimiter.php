<?php

namespace Api\Core;

use Api\Data\RateLimitRepository;

/**
 * Límite de intentos con ventana deslizante (protección anti fuerza bruta).
 *
 * Solo se registran los intentos FALLIDOS: tras maxAttempts fallos dentro
 * de la ventana, la clave queda bloqueada hasta que el intento más antiguo
 * salga de ella. Un intento exitoso limpia el contador.
 *
 * La clave agrupa los intentos (p. ej. "login:<ip>:<usuario>" para el login
 * o "refresh:<ip>" para la renovación de tokens).
 */
final class RateLimiter
{
    public function __construct(
        private readonly RateLimitRepository $attempts,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {}

    public function tooManyAttempts(string $key): bool
    {
        $since = time() - $this->windowSeconds;
        return $this->attempts->countSince($key, $since) >= $this->maxAttempts;
    }

    /** Segundos que faltan para poder reintentar (para el header Retry-After). */
    public function retryAfter(string $key): int
    {
        $oldest = $this->attempts->oldestSince($key, time() - $this->windowSeconds);
        if ($oldest === null) {
            return 0;
        }
        return max(0, $oldest + $this->windowSeconds - time());
    }

    public function recordFailure(string $key): void
    {
        $this->attempts->add($key, time());
        // Mantenimiento oportunista: descartar intentos fuera de la ventana.
        $this->attempts->purgeBefore(time() - $this->windowSeconds);
    }

    public function clear(string $key): void
    {
        $this->attempts->deleteKey($key);
    }
}
