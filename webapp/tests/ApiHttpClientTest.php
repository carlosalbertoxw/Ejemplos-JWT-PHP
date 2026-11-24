<?php

declare(strict_types=1);

use WebApp\Api\ApiHttpClient;
use WebApp\Api\ApiResponse;
use WebApp\Api\AuthApiClient;
use WebApp\Api\HttpTransport;
use WebApp\Auth\TokenStore;

/**
 * Transporte falso: devuelve las respuestas encoladas en orden y registra
 * cada petición recibida.
 */
final class FakeTransport implements HttpTransport
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    public array $requests = [];

    /** @param list<ApiResponse> $responses */
    public function __construct(private array $responses) {}

    public function send(string $method, string $url, array $headers, ?string $body): ApiResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return array_shift($this->responses) ?? new ApiResponse(0, ['error' => 'sin respuesta encolada']);
    }

    public function authorization(int $index): ?string
    {
        foreach ($this->requests[$index]['headers'] as $header) {
            if (str_starts_with($header, 'Authorization: ')) {
                return substr($header, strlen('Authorization: '));
            }
        }
        return null;
    }
}

/**
 * Pruebas del canal HTTP de la webapp: adjuntar el token, renovarlo ante un
 * 401 y reintentar una sola vez, y limpiar la sesión cuando el refresh falla.
 */
final class ApiHttpClientTest extends TestCase
{
    private TokenStore $tokens;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->tokens = new TokenStore();
        $this->tokens->save(['access_token' => 'viejo', 'refresh_token' => 'r1']);
    }

    public function testUn401RenuevaElTokenYReintentaUnaVez(): void
    {
        $transport = new FakeTransport([
            new ApiResponse(401, ['error' => 'expirado']),
            new ApiResponse(200, ['access_token' => 'nuevo', 'refresh_token' => 'r2']),
            new ApiResponse(200, [['id' => 1]]),
        ]);
        $client = new ApiHttpClient('http://api', $this->tokens, $transport);

        $response = $client->authenticatedRequest('GET', '/api/products');

        $this->assertSame(200, $response->status);
        $this->assertSame(3, count($transport->requests));
        $this->assertSame('Bearer viejo', $transport->authorization(0));
        $this->assertSame('http://api/api/auth/refresh', $transport->requests[1]['url']);
        $this->assertNull($transport->authorization(1), 'El refresh no lleva access token');
        $this->assertSame('{"refresh_token":"r1"}', $transport->requests[1]['body']);
        $this->assertSame('Bearer nuevo', $transport->authorization(2));
        $this->assertSame('r2', $this->tokens->refreshToken());
    }

    public function testSiElRefreshFallaSeLimpiaLaSesionYSeDevuelveEl401(): void
    {
        $transport = new FakeTransport([
            new ApiResponse(401, ['error' => 'expirado']),
            new ApiResponse(401, ['error' => 'refresh revocado']),
        ]);
        $client = new ApiHttpClient('http://api', $this->tokens, $transport);

        $response = $client->authenticatedRequest('GET', '/api/products');

        $this->assertSame(401, $response->status);
        $this->assertSame(2, count($transport->requests), 'No debe reintentar sin token nuevo');
        $this->assertFalse($this->tokens->isLoggedIn());
    }

    public function testUnaRespuestaCorrectaNoDisparaElRefresh(): void
    {
        $transport = new FakeTransport([new ApiResponse(200, ['ok' => true])]);
        $client = new ApiHttpClient('http://api', $this->tokens, $transport);

        $client->authenticatedRequest('GET', '/api/auth/me');

        $this->assertSame(1, count($transport->requests));
    }

    public function testElLogoutLimpiaLaSesionAunqueLaApiNoResponda(): void
    {
        $transport = new FakeTransport([new ApiResponse(0, ['error' => 'No se pudo contactar la API'])]);
        $auth = new AuthApiClient(new ApiHttpClient('http://api', $this->tokens, $transport), $this->tokens);

        $auth->logout();

        $this->assertNull($transport->authorization(0), 'El logout no lleva access token');
        $this->assertSame('{"refresh_token":"r1"}', $transport->requests[0]['body']);
        $this->assertFalse($this->tokens->isLoggedIn());
    }
}
