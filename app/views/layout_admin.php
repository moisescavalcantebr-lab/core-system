<!-- /views/layout_admin.php -->
<!DOCTYPE html>
<html lang="pt-br"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $title ?? 'Admin' ?></title>

<!-- BASE -->

<!-- css -->
<link rel="stylesheet" href="/public/assets/css/core_admin.css">

<link rel="stylesheet" href="<?= '/public/assets/css/themes/' . htmlspecialchars($theme) . '.css?v=' . time() ?>">
<!-- FAVICON -->
<?php if (!empty($coreSettings['app_favicon'])): ?>
<link rel="icon" href="/public/assets/uploads/<?= htmlspecialchars($coreSettings['app_favicon']) ?>">
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
    <main class="c-content">
        <?= $content ?>
    </main>

    <!-- RIGHT SIDEBAR -->
    <?php if (!empty($rightSidebarEnabled)): ?>
    <aside class="c-sidebar-right">
        <?= $rightSidebarContent ?? '' ?>
    </aside>
    <?php endif; ?>

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
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</body>
</html>