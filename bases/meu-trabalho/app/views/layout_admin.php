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
    <main class="c-content <?= !empty($rightSidebarEnabled) ? 'c-content--has-page-info' : '' ?>">
        <?php if (!empty($rightSidebarEnabled)): ?>
            <div class="c-page-info-popover" data-page-info-popover>
                <button class="c-page-info-button" type="button" aria-label="Informações da página" data-page-info-toggle>
                    ?
                </button>

                <div class="c-page-info-panel" data-page-info-panel>
                    <?= $rightSidebarContent ?? '' ?>
                </div>
            </div>
        <?php endif; ?>

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

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-page-info-popover]').forEach(function (popover) {
        const toggle = popover.querySelector('[data-page-info-toggle]');
        const pageHeader = document.querySelector('.c-page-header');
        let pageActions = pageHeader?.querySelector('.c-page-actions');

        if (!toggle) {
            return;
        }

        if (pageHeader) {
            if (!pageActions) {
                pageActions = document.createElement('div');
                pageActions.className = 'c-page-actions';
                pageHeader.appendChild(pageActions);
            }

            pageActions.appendChild(popover);
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            popover.classList.toggle('is-open');
        });

        document.addEventListener('click', function (event) {
            if (!popover.contains(event.target)) {
                popover.classList.remove('is-open');
            }
        });
    });
});
    
    </script>

</body>
</html>
