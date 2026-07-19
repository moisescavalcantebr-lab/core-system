<?php
$contentStudioNav = [
    '/web/admin/content_studio/index.php' => 'Dashboard',
    '/web/admin/content_studio/campaigns.php' => 'Campanhas',
    '/web/admin/content_studio/ideas.php' => 'Ideias',
    '/web/admin/content_studio/production.php' => 'Produção',
    '/web/admin/content_studio/calendar.php' => 'Calendário',
    '/web/admin/content_studio/media.php' => 'Mídia',
    '/web/admin/content_studio/settings.php' => 'Configurações',
];
?>
<div class="cs-tabs">
    <?php foreach ($contentStudioNav as $url => $label): ?>
        <a class="c-btn-secondary <?= str_contains($_SERVER['REQUEST_URI'], basename($url)) ? 'is-active' : '' ?>" href="<?= $url ?>">
            <?= htmlspecialchars($label) ?>
        </a>
    <?php endforeach; ?>
</div>
