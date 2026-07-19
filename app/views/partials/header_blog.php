<?php
$appName = (string)($coreSettings['app_name'] ?? 'Meu Projeto Web');
$logo = trim((string)($coreSettings['app_logo'] ?? ''));
$logoUrl = $logo !== '' ? '/web/assets/uploads/' . rawurlencode($logo) : '';
?>

<header class="c-store-header c-blog-public-header">
    <a class="c-store-brand <?= $logoUrl !== '' ? 'c-store-brand-has-logo' : '' ?>" href="/web/">
        <?php if ($logoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($appName) ?>">
        <?php endif; ?>
        <span>
            <strong><?= htmlspecialchars($appName) ?></strong>
            <span>Blog</span>
        </span>
    </a>

    <nav class="c-store-nav" aria-label="Navegacao principal">
        <a href="/web/">Inicio</a>
        <a href="/web/bases.php">Bases</a>
        <a href="/web/blog.php">Blog</a>
    </nav>
</header>
