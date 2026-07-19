<?php
$footerQuickLinks = [];

try {
    require_once APP_PATH . '/helpers/quick_links.php';
    $settingsServiceForFooter = new SettingsService($pdo);
    $footerQuickLinks = array_slice(
        coreQuickLinksEnabled(coreQuickLinksDecode($settingsServiceForFooter->get('quick_links'), $config ?? [])),
        0,
        6
    );
} catch (Throwable $e) {
    $footerQuickLinks = [];
}
?>

<footer class="c-footer">

    <div class="c-footer-left">
        © <?= date('Y') ?> <?= htmlspecialchars($coreSettings['app_name'] ?? 'CORE') ?>
    </div>

    <div class="c-footer-right">
        <?php if (!empty($footerQuickLinks)): ?>
            <nav class="c-footer-quick-links" aria-label="Acessos rápidos">
                <?php foreach ($footerQuickLinks as $link): ?>
                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <?= htmlspecialchars($link['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <span>Sistema Administrativo</span>
    </div>

</footer>
