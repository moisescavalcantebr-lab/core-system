<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';

if (isset($_SESSION['core_user'])) {
    header('Location: dashboard.php');
    exit;
}

ob_start();
?>


    <div class="c-auth-layout">
    <div class="c-auth-card">     
        
        <?php if (!empty($coreSettings['app_logo'])): ?>
<div class="c-auth-logo">
    <img src="/web/assets/uploads/<?= htmlspecialchars($coreSettings['app_logo']) ?>"
         style="height:120px;width:auto;">
</div>
        <?php else: ?>
            <h1 class="c-auth-title">
                <?= htmlspecialchars($coreSettings['app_name'] ?? 'CORE') ?>
            </h1>
        <?php endif; ?>

        <p class="c-auth-subtitle">
            Acesso ao sistema
        </p>

        <?php flash_show(); ?>

        <form method="post" action="/app/actions/auth/login.php" class="c-auth-form">

            <?= csrf_field(); ?>

            <div class="c-auth-input">
                <input type="email" name="email" placeholder="E-mail" required>
            </div>

            <div class="c-auth-input">
                <input type="password" name="password" placeholder="Senha" required>
            </div>

            <button type="submit" class="c-auth-btn c-btn-block">
                Entrar
            </button>

        </form>

        <div class="c-auth-link">
            <a href="/web/admin/register.php">Criar conta</a>
        </div>

        <div class="c-auth-install">
            <button type="button"
                    class="c-auth-install-btn c-auth-btn c-btn-block"
                    data-install-app
                    data-project-url="/web"
                    hidden>
                Adicionar à tela inicial
            </button>

            <p class="c-auth-install-hint" data-install-hint hidden>
                No celular, use o menu do navegador e escolha Adicionar à tela inicial.
            </p>
        </div>

    </div>
    </div>


<?php
$content = ob_get_clean();
$title = 'Login';
require APP_PATH . '/views/layout_auth.php';
