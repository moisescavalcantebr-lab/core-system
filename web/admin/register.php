<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';

if (isset($_SESSION['core_user'])) {
    header('Location: dashboard.php');
    exit;
}

$settingsService = new SettingsService($pdo);
$coreSettings = $settingsService->all();

ob_start();
?>

<div class="c-auth-layout">
<div class="c-auth-card">     

    <?php if (!empty($coreSettings['app_logo'])): ?>
        <div class="c-auth-logo">
            <img src="/public/assets/uploads/<?= htmlspecialchars($coreSettings['app_logo']) ?>" 
                 style="height:120px;width:auto;">
        </div>
    <?php else: ?>
        <h1 class="c-auth-title">
            <?= htmlspecialchars($coreSettings['app_name'] ?? 'CORE') ?>
        </h1>
    <?php endif; ?>

    <p class="c-auth-subtitle">
        Criar conta no sistema
    </p>

    <?php flash_show(); ?>

    <form method="post" action="/app/actions/auth/register.php" class="c-auth-form">

        <?= csrf_field(); ?>

        <div class="c-auth-input">
            <input type="text" name="name" placeholder="Nome completo" required>
        </div>

        <div class="c-auth-input">
            <input type="email" name="email" placeholder="E-mail" required>
        </div>

        <div class="c-auth-input">
            <input type="password" name="password" placeholder="Senha" required>
        </div>

        <div class="c-auth-input">
            <input type="password" name="password_confirm" placeholder="Confirmar senha" required>
        </div>

        <button type="submit" class="c-auth-btn c-btn-block">
            Criar Conta
        </button>

    </form>

    <div class="c-auth-link">
        <a href="/public/admin/login.php">Já possui conta? Entrar</a>
    </div>

</div>
</div>

<?php
$content = ob_get_clean();
$title = 'Criar Conta';
require APP_PATH . '/views/layout_auth.php';