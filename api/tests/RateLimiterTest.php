<?php

declare(strict_types=1);

use Api\Core\RateLimiter;
use Api\Data\Database;
use Api\Data\RateLimitRepository;

/**
 * Pruebas del límite de intentos con ventana deslizante.
 */
final class RateLimiterTest extends TestCase
{
    private const MAX_ATTEMPTS = 3;
    private const WINDOW = 60;

    private RateLimiter $limiter;
    private RateLimitRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new RateLimitRepository((new Database(':memory:'))->pdo());
        $this->limiter = new RateLimiter($this->repository, self::MAX_ATTEMPTS, self::WINDOW);
    }

    public function testPermiteIntentosPorDebajoDelLimite(): void
    {
        $this->limiter->recordFailure('login:1.2.3.4:admin');
        $this->limiter->recordFailure('login:1.2.3.4:admin');

        $this->assertFalse($this->limiter->tooManyAttempts('login:1.2.3.4:admin'));
    }

    public function testBloqueaAlAlcanzarElLimite(): void
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->limiter->recordFailure('login:1.2.3.4:admin');
        }

        $this->assertTrue($this->limiter->tooManyAttempts('login:1.2.3.4:admin'));

        $retryAfter = $this->limiter->retryAfter('login:1.2.3.4:admin');
        $this->assertTrue(
            $retryAfter > 0 && $retryAfter <= self::WINDOW,
            "Retry-After debe estar entre 1 y la ventana; fue $retryAfter",
        );
    }

    public function testCadaClaveSeLimitaPorSeparado(): void
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->limiter->recordFailure('login:1.2.3.4:admin');
        }

        // Otro usuario (u otra IP) no queda bloqueado por los fallos ajenos.
        $this->assertFalse($this->limiter->tooManyAttempts('login:1.2.3.4:user'));
        $this->assertFalse($this->limiter->tooManyAttempts('login:5.6.7.8:admin'));
    }

    public function testClearReiniciaElContador(): void
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->limiter->recordFailure('login:1.2.3.4:admin');
        }

        $this->limiter->clear('login:1.2.3.4:admin');

        $this->assertFalse($this->limiter->tooManyAttempts('login:1.2.3.4:admin'));
    }

    public function testLosIntentosFueraDeLaVentanaNoCuentan(): void
    {
        // Intentos "viejos", insertados directamente fuera de la ventana.
        $old = time() - self::WINDOW - 10;
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $this->repository->add('login:1.2.3.4:admin', $old);
        }

        $this->assertFalse($this->limiter->tooManyAttempts('login:1.2.3.4:admin'));
    }
}
