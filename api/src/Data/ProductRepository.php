<?php

namespace Api\Data;

/**
 * Acceso a la tabla de productos (el recurso protegido de ejemplo).
 *
 * La diferencia entre el acceso público y el autenticado no es cuántos
 * productos se ven, sino qué datos: el precio solo se expone con token.
 */
final class ProductRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    /**
     * Catálogo público: todos los productos, pero sin el precio.
     *
     * @return list<array<string, mixed>>
     */
    public function findPublic(): array
    {
        return $this->fetchAll('SELECT id, name, description FROM products');
    }

    /**
     * Listado completo con precio, solo para usuarios autenticados.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->fetchAll('SELECT id, name, description, price FROM products');
    }

    /** @return array{id: int, name: string, description: string, price: float} */
    public function create(string $name, string $description, float $price): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO products (name, description, price) VALUES (?, ?, ?)',
        );
        $stmt->execute([$name, $description, $price]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $name,
            'description' => $description,
            'price' => $price,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(string $sql): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        /** @var list<array<string, mixed>> $rows (FETCH_ASSOC por defecto, ver Database) */
        $rows = $stmt->fetchAll();
        return $rows;
    }
}
