<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
$favicon = function_exists('getSetting') ? getSetting('favicon') : '';
?>

<?php if (!empty($favicon) && function_exists('media')): ?>
<link rel="icon" href="<?= media($favicon) ?>">
<?php endif; ?>
    
<title><?= $title ?? 'Admin' ?></title>

<link rel="stylesheet" href="<?= PROJECT_URL ?>/assets/css/core_admin.css">

<?php
$theme = getSetting('theme') ?: 'dark';
?>

<link rel="stylesheet"
href="<?= PROJECT_URL ?>/assets/css/themes/<?= htmlspecialchars($theme) ?>.css">

</head>

<body class="c-app">

<!-- OVERLAY -->
<div class="c-sidebar-overlay" id="sidebarOverlay"></div>

<!-- HEADER -->
<header class="c-header">
    <?php require APP_PATH . '/views/partials/header_admin.php'; ?>
</header>

<!-- LAYOUT -->
<div class="c-layout">

    <!-- SIDEBAR -->
    <aside class="c-sidebar" id="sidebar">
        <?php require APP_PATH . '/views/partials/sidebar_admin.php'; ?>
    </aside>

    <!-- CONTENT -->
    <main class="c-content">
        <?= $content ?? '' ?>
    </main>

</div>
<?php require APP_PATH . '/views/partials/footer.php'; ?>
    
<!-- JS LIMPO -->
<script>
    
document.addEventListener('DOMContentLoaded', () => {

    const btn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (btn) {
        btn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

});
    
    </script>

</body>
</html>