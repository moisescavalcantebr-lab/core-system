<?php
$current = $_SERVER['REQUEST_URI'] ?? '';

function isActive($path) {
    return strpos($_SERVER['REQUEST_URI'], $path) !== false ? 'active' : '';
}
?>

<nav class="c-sidebar-nav">

    <div class="c-sidebar-section">

        <div class="c-sidebar-title">Principal</div>

        <a href="<?= PROJECT_URL ?>/admin/dashboard.php"
           class="c-sidebar-link <?= isActive('dashboard') ?>">
            Dashboard
        </a>

    </div>

    <div class="c-sidebar-section">

        <div class="c-sidebar-title">Sistema</div>

        <a href="<?= PROJECT_URL ?>/admin/profile/index.php"
           class="c-sidebar-link <?= isActive('profile') ?>">
            Perfil
        </a>

                <a href="<?= PROJECT_URL ?>/admin/settings/index.php"
           class="c-sidebar-link <?= isActive('settings') ?>">
            Configurações
        </a>

    </div>

</nav>