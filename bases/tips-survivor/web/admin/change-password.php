<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();

$title = 'Alterar Senha';

ob_start();
?>

<div class="c-auth-layout">
    <div class="c-auth-card">
        <h1 class="c-auth-title">Alterar Senha</h1>
        <p class="c-auth-subtitle">Defina uma senha nova para continuar</p>

        <?php flash_show(); ?>

        <form method="POST" action="<?= PROJECT_URL ?>/admin/change-password-save.php" class="c-auth-form">
            <?= csrf_field(); ?>

            <div class="c-auth-input">
                <input type="password" name="password" placeholder="Nova senha" autocomplete="new-password" required>
            </div>

            <div class="c-auth-input">
                <input type="password" name="confirm_password" placeholder="Confirmar senha" autocomplete="new-password" required>
            </div>

            <button class="c-auth-btn c-btn-block">
                Salvar senha
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';
