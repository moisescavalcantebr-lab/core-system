<?php
$user = function_exists('projectUser') ? projectUser() : null;

$logo = function_exists('getSetting') ? getSetting('logo') : '';
$siteName = function_exists('getSetting') 
    ? getSetting('site_name', $project['name'] ?? 'Projeto') 
    : 'Projeto';
$avatar = trim((string)($user['avatar'] ?? ''));
?>

<div class="c-header-inner">

    <div class="c-header-left">

        <!-- HAMBURGUER -->
        <button id="menuToggle" class="c-menu-toggle">
            ☰
        </button>

        <?php if (!empty($logo) && function_exists('media')): ?>
            <img
                src="<?= media($logo) ?>"
                alt="<?= htmlspecialchars($siteName) ?>"
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

            <?php if ($avatar !== ''): ?>
                <a href="<?= PROJECT_URL ?>/admin/profile/index.php"
                   class="c-user-avatar-link"
                   title="<?= htmlspecialchars($user['name'] ?? 'Usuario') ?>">
                    <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars($avatar) ?>"
                         alt="<?= htmlspecialchars($user['name'] ?? 'Usuario') ?>"
                         class="c-user-avatar-img">
                </a>
            <?php else: ?>
                <span class="c-user-name">
                    <?= htmlspecialchars($user['name'] ?? 'Usuario') ?>
                </span>
            <?php endif; ?>

            <a href="<?= PROJECT_URL ?>/admin/saldo.php"
               class="c-header-action <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/admin/saldo') ? 'is-active' : '' ?>">
                Carteira
            </a>

            <a href="<?= PROJECT_URL ?>/admin/logout.php"
               class="c-header-action c-header-action--logout">
                Sair
            </a>

        </div>

    </div>

</div>
