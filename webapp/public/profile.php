<?php

/**
 * Perfil: muestra los claims que la API extrae del access token
 * (GET /api/auth/me).
 */

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/View/layout.php';

requireLogin($tokenStore);

$response = $authClient->me();

if ($response->status === 401) {
    $tokenStore->clear();
    header('Location: /login.php');
    exit;
}

renderHeader('Mi perfil', $tokenStore->user());
?>

<div class="alert alert-info">
    Estos datos no vienen de la sesión: la API los lee de los
    <strong>claims del access token</strong> en <code>GET /api/auth/me</code>.
</div>

<?php if (!$response->ok()): ?>
    <div class="alert alert-error"><?= e($response->error()) ?></div>
<?php else: ?>
    <div class="card">
        <p><strong>Id:</strong> <?= e((string) ($response->data['id'] ?? '')) ?></p>
        <p><strong>Usuario:</strong> <?= e((string) ($response->data['username'] ?? '')) ?></p>
        <p><strong>Rol:</strong> <?= e((string) ($response->data['role'] ?? '')) ?></p>
        <p>
            <strong>El token expira:</strong>
            <?= e(date('Y-m-d H:i:s', (int) ($response->data['token_expires_at'] ?? 0))) ?>
            <span class="hint">(cuando expire, la web lo renovará sola con el refresh token)</span>
        </p>
    </div>
<?php endif; ?>

<?php renderFooter(); ?>
