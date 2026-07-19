<!-- /views/layout_admin.php -->
<!DOCTYPE html>
<html lang="pt-br"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $title ?? 'Admin' ?></title>

<!-- BASE -->

<!-- css -->
<?php
$adminCssFiles = [
    ROOT_PATH . '/web/assets/css/core_admin.css',
    ROOT_PATH . '/web/assets/css/admin/layout.css',
    ROOT_PATH . '/web/assets/css/admin/components.css',
];
$adminCssVersion = time();

foreach ($adminCssFiles as $adminCssFile) {
    if (is_file($adminCssFile)) {
        $adminCssVersion = max($adminCssVersion, (int)filemtime($adminCssFile));
    }
}
?>
<link rel="stylesheet" href="/web/assets/css/core_admin.css?v=<?= $adminCssVersion ?>">

<link rel="stylesheet" href="<?= '/web/assets/css/themes/' . htmlspecialchars($theme) . '.css?v=' . time() ?>">
<!-- FAVICON -->
<?php if (!empty($coreSettings['app_favicon'])): ?>
<link rel="icon" href="/web/assets/uploads/<?= htmlspecialchars($coreSettings['app_favicon']) ?>">
<?php endif; ?>

</head>

<body class="c-app">

<!-- OVERLAY (MOBILE) -->
<div class="c-sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- HEADER -->
<header class="c-header">
    <?php require __DIR__ . '/partials/header.php'; ?>
</header>

<!-- LAYOUT -->
<div class="c-layout">

    <!-- SIDEBAR -->
    <aside class="c-sidebar">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
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

        <?= $content ?>
    </main>

</div>

<!-- FOOTER -->
<?php require __DIR__ . '/partials/footer.php'; ?>

<!-- JS (ADMIN) -->
<script>
function toggleSidebar() {
    const sidebar = document.querySelector('.c-sidebar');
    const overlay = document.querySelector('.c-sidebar-overlay');

    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('active');
}

// fechar ao clicar link
document.querySelectorAll('.c-sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        document.querySelector('.c-sidebar')?.classList.remove('open');
        document.querySelector('.c-sidebar-overlay')?.classList.remove('active');
    });
});

// fechar com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelector('.c-sidebar')?.classList.remove('open');
        document.querySelector('.c-sidebar-overlay')?.classList.remove('active');
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</body>
</html>
