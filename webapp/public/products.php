<?php

/**
 * Listado completo de productos (GET /api/products, requiere Bearer token).
 *
 * Si el access token expiró, el cliente HTTP lo renueva automáticamente
 * con el refresh token y reintenta; el usuario no percibe nada.
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/View/layout.php';

requireLogin($tokenStore);

$response = $productsClient->list();

// Si aun con refresh la API respondió 401, la sesión ya no es válida.
if ($response->status === 401) {
    $tokenStore->clear();
    header('Location: /login.php');
    exit;
}

renderHeader('Productos (protegido)', $tokenStore->user());
?>

<div class="alert alert-info">
    Esta página consume <code>GET /api/products</code> enviando el access
    token en el encabezado <code>Authorization: Bearer …</code>. A diferencia
    del catálogo público, aquí sí se muestra el <strong>precio</strong>.
</div>

<?php if (!$response->ok()): ?>
    <div class="alert alert-error"><?= e($response->error()) ?></div>
<?php else: ?>
    <table>
        <thead>
            <tr><th>Producto</th><th>Descripción</th><th>Precio</th></tr>
        </thead>
        <tbody>
            <?php foreach ($response->data as $product): ?>
                <tr>
                    <td><?= e($product['name'] ?? '') ?></td>
                    <td><?= e($product['description'] ?? '') ?></td>
                    <td>$<?= number_format((float) ($product['price'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php renderFooter(); ?>
