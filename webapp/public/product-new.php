<?php

/**
 * Alta de producto (POST /api/products, requiere rol Admin).
 *
 * La autorización real la hace la API validando el claim "role" del
 * token: aunque alguien llegara a esta página sin ser Admin, la API
 * respondería 403.
 */

require __DIR__ . '/../src/bootstrap.php';

/**
 * Servicios creados en bootstrap.php:
 * @var \WebApp\Auth\TokenStore $tokenStore
 * @var \WebApp\Api\AuthApiClient $authClient
 * @var \WebApp\Api\ProductsApiClient $productsClient
 */
require __DIR__ . '/../src/View/layout.php';

requireLogin($tokenStore);

$error = null;
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $name = trim(postString('name'));
    $description = trim(postString('description'));
    $price = postString('price');

    // is_finite: "1e999" es numérico pero se convierte en INF. La API valida
    // el rango exacto; aquí solo se evita enviarle valores sin sentido.
    if ($name === '' || !is_numeric($price) || !is_finite((float) $price)) {
        $error = 'Nombre y precio (numérico) son obligatorios';
    } else {
        $response = $productsClient->create($name, $description, (float) $price);
        if ($response->ok()) {
            $created = $response->data;
        } else {
            $error = $response->error();
        }
    }
}

renderHeader('Nuevo producto (solo Admin)', $tokenStore->user());
?>

<div class="alert alert-info">
    Esta página consume <code>POST /api/products</code>, protegido por rol:
    la API solo lo acepta si el claim <code>role</code> del token es
    <code>Admin</code>. Prueba entrar con <code>user</code> para ver el 403.
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($created !== null): ?>
    <div class="alert alert-success">
        Producto creado: <strong><?= e($created['name'] ?? '') ?></strong>
        (id <?= e((string) ($created['id'] ?? '')) ?>).
        <a href="/products.php">Ver el listado</a>.
    </div>
<?php endif; ?>

<form class="panel" method="post" action="/product-new.php">
    <?= csrfField() ?>
    <label>
        Nombre
        <input type="text" name="name" required>
    </label>
    <label>
        Descripción
        <input type="text" name="description">
    </label>
    <label>
        Precio
        <input type="number" name="price" step="0.01" min="0" required>
        <span class="hint">Solo visible para usuarios autenticados; el catálogo público lo oculta.</span>
    </label>
    <button type="submit">Crear producto</button>
</form>

<?php renderFooter(); ?>
