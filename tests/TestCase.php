<?php

/**
 * Mini framework de pruebas sin dependencias, fiel al espíritu "PHP puro"
 * del proyecto: clases *Test.php con métodos test*, aserciones básicas y
 * un runner (run.php). Cada test corre sobre una instancia nueva.
 */

declare(strict_types=1);

/** Falla de aserción (distinta de un error inesperado del código probado). */
final class AssertionFailedError extends \Exception {}

abstract class TestCase
{
    /** Se ejecuta antes de cada método de prueba. */
    protected function setUp(): void {}

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            $this->fail($message !== '' ? $message : 'Se esperaba true');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message !== '' ? $message : 'Se esperaba false');
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail(sprintf(
                "%s\n  Esperado: %s\n  Recibido: %s",
                $message !== '' ? $message : 'Los valores no coinciden',
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->assertSame(null, $value, $message !== '' ? $message : 'Se esperaba null');
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            $this->fail($message !== '' ? $message : 'No se esperaba null');
        }
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            $this->fail($message !== '' ? $message : "Falta la clave '$key' en el array");
        }
    }

    /**
     * Ejecuta $fn y verifica que lance una excepción de la clase dada
     * (y, opcionalmente, con el mensaje exacto).
     */
    protected function expectException(string $class, callable $fn, ?string $expectedMessage = null): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!($e instanceof $class)) {
                $this->fail(sprintf(
                    'Se esperaba %s pero se lanzó %s ("%s")',
                    $class,
                    $e::class,
                    $e->getMessage(),
                ));
            }
            if ($expectedMessage !== null && $e->getMessage() !== $expectedMessage) {
                $this->fail(sprintf(
                    "Mensaje de excepción distinto\n  Esperado: %s\n  Recibido: %s",
                    $expectedMessage,
                    $e->getMessage(),
                ));
            }
            return;
        }
        $this->fail("Se esperaba la excepción $class y no se lanzó ninguna");
    }

    protected function fail(string $message): never
    {
        throw new AssertionFailedError($message);
    }
}
