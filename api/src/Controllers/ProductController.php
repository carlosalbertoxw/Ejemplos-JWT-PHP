<?php

namespace Api\Controllers;

use Api\Data\ProductRepository;
use Api\Http\Request;
use Api\Http\Response;

/**
 * Endpoints del recurso de ejemplo: /api/products*
 */
final class ProductController
{
    private const MAX_NAME_LENGTH = 100;
    private const MAX_DESCRIPTION_LENGTH = 500;
    private const MAX_PRICE = 1_000_000_000;

    public function __construct(private readonly ProductRepository $products) {}

    /** GET /api/products/public — catálogo sin autenticación (oculta el precio). */
    public function publicList(Request $request): Response
    {
        return Response::json($this->products->findPublic());
    }

    /** GET /api/products — listado con precio, requiere token válido. */
    public function list(Request $request): Response
    {
        return Response::json($this->products->findAll());
    }

    /** POST /api/products — crea un producto, requiere rol Admin. */
    public function create(Request $request): Response
    {
        $name = $request->input('name');
        $description = $request->input('description');
        $price = $request->body['price'] ?? null;

        if ($name === '') {
            return Response::error('name es obligatorio', 400);
        }
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            return Response::error(
                'name no puede superar los ' . self::MAX_NAME_LENGTH . ' caracteres',
                400,
            );
        }
        if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            return Response::error(
                'description no puede superar los ' . self::MAX_DESCRIPTION_LENGTH . ' caracteres',
                400,
            );
        }
        // is_numeric() acepta notación científica: "1e999" se convierte en INF,
        // que SQLite guarda pero json_encode no puede serializar. Por eso se
        // exige un número finito y acotado (el mismo máximo que el ejemplo .NET).
        if (!is_numeric($price)
            || !is_finite((float) $price)
            || (float) $price < 0
            || (float) $price > self::MAX_PRICE) {
            return Response::error('price debe ser un número entre 0 y ' . self::MAX_PRICE, 400);
        }

        $product = $this->products->create($name, $description, (float) $price);
        return Response::json($product, 201);
    }
}
