<?php

namespace Api\Http;

use Api\Core\JwtException;
use Api\Core\JwtService;

/**
 * Router minimalista con control de acceso por ruta.
 *
 * Cada ruta declara su nivel de acceso (Access::PublicRoute o Access::Auth)
 * y, opcionalmente, el rol exacto que exige (p. ej. 'Admin').
 *
 * dispatch() DEVUELVE la respuesta en lugar de emitirla: eso permite probar
 * el enrutamiento y los controladores sin terminar el proceso.
 */
final class Router
{
    /** @var list<array{method: string, path: string, handler: callable, access: Access, role: ?string}> */
    private array $routes = [];

    public function __construct(private readonly JwtService $jwt) {}

    public function add(
        string $method,
        string $path,
        callable $handler,
        Access $access = Access::PublicRoute,
        ?string $role = null,
    ): void {
        // Exigir un rol implica exigir autenticación.
        if ($role !== null) {
            $access = Access::Auth;
        }
        $this->routes[] = compact('method', 'path', 'handler', 'access', 'role');
    }

    /**
     * Rutas declaradas como "MÉTODO /ruta" (p. ej. "GET /api/products").
     *
     * @return list<string>
     */
    public function routes(): array
    {
        return array_map(fn(array $r): string => $r['method'] . ' ' . $r['path'], $this->routes);
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method || $route['path'] !== $request->path) {
                continue;
            }

            if ($route['access'] === Access::Auth) {
                $claims = $this->authenticate($request);
                if ($claims instanceof Response) {
                    return $claims; // 401
                }
                $request->claims = $claims;

                if ($route['role'] !== null
                    && ($request->claims['role'] ?? null) !== $route['role']) {
                    return Response::error('Se requiere el rol ' . $route['role'], 403);
                }
            }

            return ($route['handler'])($request);
        }

        // Distinguir 404 (ruta desconocida) de 405 (método incorrecto).
        foreach ($this->routes as $route) {
            if ($route['path'] === $request->path) {
                return Response::error('Método no permitido', 405);
            }
        }
        return Response::error('Ruta no encontrada', 404);
    }

    /**
     * Valida el bearer token: devuelve sus claims o la respuesta 401.
     *
     * @return array<string, mixed>|Response
     */
    private function authenticate(Request $request): array|Response
    {
        if ($request->bearerToken === null) {
            return Response::error('Falta el encabezado Authorization: Bearer', 401);
        }

        try {
            return $this->jwt->verify($request->bearerToken);
        } catch (JwtException $e) {
            return Response::error('Token inválido: ' . $e->getMessage(), 401);
        }
    }
}
