<?php

declare(strict_types=1);

use Api\Core\JwtService;
use Api\Http\Access;
use Api\Http\Request;
use Api\Http\Response;
use Api\Http\Router;

/**
 * Pruebas del enrutamiento y del control de acceso por token y rol.
 * Posibles gracias a que dispatch() devuelve la respuesta en vez de emitirla.
 */
final class RouterTest extends TestCase
{
    private JwtService $jwt;
    private Router $router;

    protected function setUp(): void
    {
        $this->jwt = new JwtService('secreto-de-pruebas-de-al-menos-32-bytes', 'iss', 'aud', 900);

        $this->router = new Router($this->jwt);
        $this->router->add('GET', '/ping', fn(Request $r) => Response::json(['pong' => true]));
        $this->router->add(
            'GET',
            '/secure',
            fn(Request $r) => Response::json(['user' => $r->claims['username'] ?? null]),
            Access::Auth,
        );
        $this->router->add(
            'POST',
            '/admin',
            fn(Request $r) => Response::json(['ok' => true], 201),
            Access::Auth,
            'Admin',
        );
    }

    public function testDespachaUnaRutaPublica(): void
    {
        $response = $this->router->dispatch($this->request('GET', '/ping'));

        $this->assertSame(200, $response->status());
        $this->assertSame(['pong' => true], $response->data());
    }

    public function testRutaProtegidaSinTokenResponde401(): void
    {
        $response = $this->router->dispatch($this->request('GET', '/secure'));

        $this->assertSame(401, $response->status());
    }

    public function testRutaProtegidaConTokenInvalidoResponde401(): void
    {
        $response = $this->router->dispatch($this->request('GET', '/secure', 'token-basura'));

        $this->assertSame(401, $response->status());
    }

    public function testRutaProtegidaConTokenValidoEntregaLosClaims(): void
    {
        $token = $this->jwt->create(['sub' => '1', 'username' => 'admin', 'role' => 'Admin']);

        $response = $this->router->dispatch($this->request('GET', '/secure', $token));

        $this->assertSame(200, $response->status());
        $this->assertSame(['user' => 'admin'], $response->data());
    }

    public function testRutaConRolRechazaOtroRolCon403(): void
    {
        $token = $this->jwt->create(['sub' => '2', 'username' => 'user', 'role' => 'User']);

        $response = $this->router->dispatch($this->request('POST', '/admin', $token));

        $this->assertSame(403, $response->status());
    }

    public function testRutaConRolAceptaElRolExacto(): void
    {
        $token = $this->jwt->create(['sub' => '1', 'username' => 'admin', 'role' => 'Admin']);

        $response = $this->router->dispatch($this->request('POST', '/admin', $token));

        $this->assertSame(201, $response->status());
    }

    public function testRutaDesconocidaResponde404(): void
    {
        $response = $this->router->dispatch($this->request('GET', '/no-existe'));

        $this->assertSame(404, $response->status());
    }

    public function testMetodoIncorrectoResponde405(): void
    {
        $response = $this->router->dispatch($this->request('POST', '/ping'));

        $this->assertSame(405, $response->status());
    }

    private function request(string $method, string $path, ?string $bearerToken = null): Request
    {
        return new Request(
            method: $method,
            path: $path,
            body: [],
            bearerToken: $bearerToken,
        );
    }
}
