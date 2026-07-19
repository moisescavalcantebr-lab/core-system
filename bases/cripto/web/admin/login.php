<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

/* =========================
REDIRECT SE LOGADO
========================= */

if (!empty($_SESSION['project_user_id'])) {
    header('Location: ' . projectDashboardUrl());
    exit;
}

$title = 'Login';

ob_start();
?>

<div class="c-auth-layout">
<div class="c-auth-card">

<?php
$logo = function_exists('getSetting') ? getSetting('logo') : '';
$siteName = function_exists('getSetting') ? getSetting('site_name', $project['name'] ?? 'Projeto') : ($project['name'] ?? 'Projeto');
?>

<?php if (!empty($logo) && function_exists('media')): ?>

    <img 
        src="<?= media($logo) ?>" 
        alt="Logo"
        style="max-height:80px; margin-bottom:15px;"
    >

<?php else: ?>

    <h1 class="c-auth-title">
        <?= htmlspecialchars($siteName) ?>
    </h1>

<?php endif; ?>

    <p class="c-auth-subtitle">
        Acesso ao sistema
    </p>

    <?php flash_show(); ?>

    <form method="post"
          action="<?= PROJECT_URL ?>/admin/login_action.php"
          class="c-auth-form">

        <?= csrf_field(); ?>

        <div class="c-auth-input">
            <input type="text"
                   name="email"
                   placeholder="E-mail ou usuário"
                   autocomplete="username"
                   required>
        </div>

        <div class="c-auth-input">
            <input type="password"
                   name="password"
                   placeholder="Senha"
                   autocomplete="current-password"
                   required>
        </div>

        <button type="submit" class="c-auth-btn c-btn-block">
            Entrar
        </button>

    </form>

    <div class="c-auth-link">
        <a href="<?= PROJECT_URL ?>/admin/forgot-password.php">
            Esqueci minha senha
        </a>
    </div>

    <div class="c-auth-install">
        <button type="button"
                class="c-auth-install-btn"
                data-install-app
                data-project-url="<?= PROJECT_URL ?>"
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
require APP_PATH . '/views/layout_auth.php';
