<?php

declare(strict_types=1);

use Api\Controllers\ProductController;
use Api\Data\Database;
use Api\Data\ProductRepository;
use Api\Http\Request;

/**
 * Pruebas de la validación al crear productos.
 */
final class ProductControllerTest extends TestCase
{
    private ProductController $controller;

    protected function setUp(): void
    {
        $pdo = (new Database(':memory:'))->pdo();
        $this->controller = new ProductController(new ProductRepository($pdo));
    }

    public function testCreaUnProductoValido(): void
    {
        $response = $this->controller->create($this->request(['name' => 'Cable', 'price' => 9.5]));

        $this->assertSame(201, $response->status());
        $this->assertSame(9.5, $response->data()['price']);
    }

    public function testRechazaPreciosNoFinitos(): void
    {
        // "1e999" es numérico para is_numeric() pero se convierte en INF.
        foreach (['1e999', '-1e999'] as $price) {
            $response = $this->controller->create($this->request(['name' => 'X', 'price' => $price]));
            $this->assertSame(400, $response->status(), "price=$price debe rechazarse");
        }
    }

    public function testRechazaPreciosFueraDeRango(): void
    {
        foreach ([-1, 1_000_000_001, 'abc'] as $price) {
            $response = $this->controller->create($this->request(['name' => 'X', 'price' => $price]));
            $this->assertSame(400, $response->status(), 'price=' . var_export($price, true) . ' debe rechazarse');
        }
    }

    public function testElListadoSigueSerializandoTrasIntentosInvalidos(): void
    {
        $this->controller->create($this->request(['name' => 'X', 'price' => '1e999']));

        // Si INF hubiera llegado a la base, body() lanzaría JsonException.
        $body = $this->controller->list($this->request([]))->body();
        $this->assertTrue(str_starts_with($body, '['));
    }

    private function request(array $body): Request
    {
        return new Request('POST', '/api/products', $body, null);
    }
}
