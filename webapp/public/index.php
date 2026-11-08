<?php

/**
 * Página de inicio: muestra el catálogo público (no requiere sesión).
 */

require __DIR__ . '/../src/bootstrap.php';

/**
 * Servicios creados en bootstrap.php:
 * @var \WebApp\Auth\TokenStore $tokenStore
 * @var \WebApp\Api\AuthApiClient $authClient
 * @var \WebApp\Api\ProductsApiClient $productsClient
 */
require __DIR__ . '/../src/View/layout.php';

$response = $productsClient->publicList();

renderHeader('Catálogo público', $tokenStore->user());
?>

<div class="alert alert-info">
    Esta página consume <code>GET /api/products/public</code>, un endpoint
    que <strong>no requiere token</strong> y por eso <strong>oculta el
    precio</strong>. Inicia sesión para verlo.
</div>

<?php if (!$response->ok()): ?>
    <div class="alert alert-error"><?= e($response->error()) ?></div>
<?php elseif ($response->data === []): ?>
    <div class="card">No hay productos.</div>
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
                    <td><span class="badge">🔒 Requiere sesión</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php renderFooter(); ?>
