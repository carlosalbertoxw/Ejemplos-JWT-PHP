<?php

declare(strict_types=1);

use Api\Core\JwtSecret;

/**
 * Pruebas de la resolución de la clave JWT: nunca hay una clave versionada.
 */
final class JwtSecretTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/jwt-secret-test-' . bin2hex(random_bytes(6)) . '/jwt-secret';
    }

    public function testUsaLaVariableDeEntornoSiExiste(): void
    {
        $secret = str_repeat('k', 40);

        $this->assertSame($secret, JwtSecret::resolve($secret, 'production', $this->file));
        $this->assertFalse(is_file($this->file), 'No debe generar archivo si hay variable');
    }

    public function testRechazaUnaVariableDemasiadoCorta(): void
    {
        $this->expectException(
            \RuntimeException::class,
            fn() => JwtSecret::resolve('corta', 'development', $this->file),
        );
    }

    public function testEnProduccionLaVariableEsObligatoria(): void
    {
        $this->expectException(
            \RuntimeException::class,
            fn() => JwtSecret::resolve(false, 'production', $this->file),
        );
    }

    public function testEnDesarrolloGeneraUnaClaveYLaReutiliza(): void
    {
        $first = JwtSecret::resolve(false, 'development', $this->file);
        $second = JwtSecret::resolve('', 'development', $this->file);

        $this->assertTrue(strlen($first) >= JwtSecret::MIN_BYTES, 'La clave generada debe tener >= 32 bytes');
        $this->assertSame($first, $second, 'Las peticiones siguientes deben reutilizar la misma clave');

        unlink($this->file);
        rmdir(dirname($this->file));
    }
}
