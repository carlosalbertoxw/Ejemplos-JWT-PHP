<?php

declare(strict_types=1);

use Api\Controllers\AuthController;
use Api\Controllers\ProductController;
use Api\Core\AuthService;
use Api\Core\JwtService;
use Api\Core\PasswordHasher;
use Api\Core\RateLimiter;
use Api\Data\Database;
use Api\Data\ProductRepository;
use Api\Data\RateLimitRepository;
use Api\Data\RefreshTokenRepository;
use Api\Data\UserRepository;
use Api\Http\Router;

/**
 * openapi.json se escribe a mano: esta prueba comprueba que documenta
 * exactamente las rutas que declara config/routes.php, para que un
 * endpoint nuevo (o uno eliminado) no deje la especificación desfasada.
 */
final class OpenApiTest extends TestCase
{
    public function testLaEspecificacionDocumentaTodasLasRutasYNadaMas(): void
    {
        $this->assertSame($this->documentedRoutes(), $this->declaredRoutes());
    }

    /** @return list<string> */
    private function declaredRoutes(): array
    {
        $pdo = (new Database(':memory:'))->pdo();
        $jwt = new JwtService('secreto-de-pruebas-de-al-menos-32-bytes', 'iss', 'aud', 900);
        $attempts = new RateLimitRepository($pdo);
        $auth = new AuthController(
            new AuthService(
                new UserRepository($pdo),
                new RefreshTokenRepository($pdo),
                $jwt,
                new PasswordHasher(),
                900,
                3600,
            ),
            new RateLimiter($attempts, 5, 900),
            new RateLimiter($attempts, 20, 900),
        );

        $router = new Router($jwt);
        (require dirname(__DIR__) . '/config/routes.php')(
            $router,
            $auth,
            new ProductController(new ProductRepository($pdo)),
        );

        // "GET /" solo describe la API en texto; no forma parte del contrato.
        $routes = array_values(array_diff($router->routes(), ['GET /']));
        sort($routes);
        return $routes;
    }

    /** @return list<string> */
    private function documentedRoutes(): array
    {
        $spec = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/public/openapi.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $routes = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $routes[] = strtoupper($method) . ' ' . $path;
                }
            }
        }
        sort($routes);
        return $routes;
    }
}
