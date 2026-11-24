<?php

namespace WebApp\Api;

/**
 * Cliente de los endpoints de productos de la API.
 */
final class ProductsApiClient
{
    public function __construct(private readonly ApiHttpClient $http) {}

    /** Catálogo público, sin precios (no requiere sesión). */
    public function publicList(): ApiResponse
    {
        return $this->http->request('GET', '/api/products/public');
    }

    /** Listado con precios (requiere sesión). */
    public function list(): ApiResponse
    {
        return $this->http->authenticatedRequest('GET', '/api/products');
    }

    /** Crear producto (requiere rol Admin). */
    public function create(string $name, string $description, float $price): ApiResponse
    {
        return $this->http->authenticatedRequest('POST', '/api/products', [
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ]);
    }
}
