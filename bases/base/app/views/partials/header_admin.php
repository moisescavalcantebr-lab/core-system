<?php
$user = function_exists('projectUser') ? projectUser() : null;

$logo = function_exists('getSetting') ? getSetting('logo') : '';
$siteName = function_exists('getSetting') 
    ? getSetting('site_name', $project['name'] ?? 'Projeto') 
    : 'Projeto';
?>

<div class="c-header-inner">

    <div class="c-header-left">

        <!-- HAMBURGUER -->
        <button id="menuToggle" class="c-menu-toggle">
            ☰
        </button>

        <!-- LOGO OU NOME -->
        <?php if (!empty($logo) && function_exists('media')): ?>

            <img 
                src="<?= media($logo) ?>" 
                alt="Logo"
                class="c-header-logo"
            >

        <?php else: ?>

            <span class="c-header-title">
                <?= htmlspecialchars($siteName) ?>
            </span>

        <?php endif; ?>

    </div>

    <div class="c-header-right">

        <div class="c-header-user">

            <span class="c-user-name">
                <?= htmlspecialchars($user['name'] ?? 'Usuário') ?>
            </span>

            <a href="<?= PROJECT_URL ?>/admin/logout.php"
               class="c-btn c-btn-secondary">
                Sair
            </a>

        </div>

    </div>

</div>