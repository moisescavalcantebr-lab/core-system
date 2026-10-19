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
<?php if(!empty($mailPrivatePage)&&isset($mailOwner)): ?><link rel="stylesheet" href="/web/assets/css/admin/central_contas.css?v=<?= (int)filemtime(PUBLIC_PATH.'/assets/css/admin/central_contas.css') ?>"><?php endif; ?>
<!-- FAVICON -->
<?php if (!empty($coreSettings['app_favicon'])): ?>
<link rel="icon" href="/web/assets/uploads/<?= htmlspecialchars($coreSettings['app_favicon']) ?>">
<?php endif; ?>

</head>

<body class="c-app<?= !empty($mailPrivatePage)&&isset($mailOwner)?' mail-core-mode':'' ?>">

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

        <?php if (function_exists('flash_show')): ?>
            <div class="c-flash-wrap">
                <?php flash_show(); ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($mailPrivatePage)&&isset($mailOwner)&&in_array($mailTab??'',['messages','unified'],true)): ?>
        <div class="mail-client">
            <?php require __DIR__.'/partials/mail_folders.php'; ?>
            <section class="mail-client-main"><?= $content ?></section>
        </div>
        <?php else: ?><?= $content ?><?php endif; ?>
    </main>

</div>

<!-- FOOTER -->
<?php require __DIR__ . '/partials/footer.php'; ?>

<!-- JS (ADMIN) -->
<script>
function toggleSidebar() {
    if(document.body.classList.contains('mail-core-mode')&&matchMedia('(min-width: 1024px)').matches){document.body.classList.toggle('mail-core-expanded');return;}
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
<?php if (empty($mailPrivatePage)): ?>
<script src="/web/assets/js/Sortable-1.15.0.min.js"></script>
<?php else: ?><script src="/web/assets/js/central_contas.js?v=<?= (int)filemtime(PUBLIC_PATH.'/assets/js/central_contas.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
