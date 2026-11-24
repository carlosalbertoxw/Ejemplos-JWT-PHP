<?php

declare(strict_types=1);

use Api\Http\Response;

/**
 * Pruebas de la serialización de las respuestas.
 */
final class ResponseTest extends TestCase
{
    public function testSerializaSinEscaparUnicodeNiBarras(): void
    {
        $this->assertSame('{"m":"Sesión /ok"}', Response::json(['m' => 'Sesión /ok'])->body());
    }

    public function testUnValorNoSerializableLanzaEnVezDeResponderVacio(): void
    {
        // Sin JSON_THROW_ON_ERROR, json_encode devolvería false y el cliente
        // recibiría un cuerpo vacío con código 200.
        $this->expectException(\JsonException::class, fn() => Response::json(['price' => INF])->body());
    }
}
