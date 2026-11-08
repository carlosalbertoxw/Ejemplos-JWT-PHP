<?php

/**
 * Formulario de inicio de sesión. Envía las credenciales a la API
 * (POST /api/auth/login) y guarda el par de tokens en la sesión.
 */

require __DIR__ . '/../src/bootstrap.php';

/**
 * Servicios creados en bootstrap.php:
 * @var \WebApp\Auth\TokenStore $tokenStore
 * @var \WebApp\Api\AuthApiClient $authClient
 * @var \WebApp\Api\ProductsApiClient $productsClient
 */
require __DIR__ . '/../src/View/layout.php';

if ($tokenStore->isLoggedIn()) {
    header('Location: /products.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Usuario y contraseña son obligatorios';
    } else {
        $error = $authClient->login($username, $password);
        if ($error === null) {
            // Regenerar el ID de sesión al elevar privilegios evita la
            // fijación de sesión (los datos de la sesión se conservan).
            session_regenerate_id(true);
            header('Location: /products.php');
            exit;
        }
    }
}

renderHeader('Iniciar sesión', null);
?>

<?php if ($error !== null): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form class="panel" method="post" action="/login.php">
    <?= csrfField() ?>
    <label>
        Usuario
        <input type="text" name="username" required
               value="<?= e($_POST['username'] ?? '') ?>">
    </label>
    <label>
        Contraseña
        <input type="password" name="password" required>
    </label>
    <button type="submit">Entrar</button>
    <p class="hint">
        Usuarios de prueba: <code>admin / Admin123!</code> (rol Admin)
        y <code>user / User123!</code> (rol User).
    </p>
</form>

<?php renderFooter(); ?>
