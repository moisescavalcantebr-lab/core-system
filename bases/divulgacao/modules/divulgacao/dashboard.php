<?php
declare(strict_types=1);

if (!isset($pdo) || !$pdo instanceof PDO) {
    return null;
}

$schema = PROJECT_PATH . '/modules/divulgacao/database/schema.sql';
if (is_file($schema)) {
    $pdo->exec((string)file_get_contents($schema));
}

$summary = $pdo->query("
    SELECT
        COUNT(*) AS pages,
        COUNT(CASE WHEN status = 'published' THEN 1 END) AS published
    FROM divulgacao_pages
")->fetch(PDO::FETCH_ASSOC) ?: ['pages' => 0, 'published' => 0];

$leads = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'novo' THEN 1 END) AS novos
    FROM divulgacao_leads
")->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'novos' => 0];

ob_start();
?>
<section class="dashboard-module-panel">
    <div class="dashboard-panel-head">
        <div>
            <h2>Divulgacao</h2>
            <p>Paginas publicas e leads capturados.</p>
        </div>
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/divulgacao/index.php">Abrir</a>
    </div>
    <div class="dashboard-stats-grid">
        <div><span>Paginas</span><strong><?= (int)$summary['pages'] ?></strong></div>
        <div><span>Publicadas</span><strong><?= (int)$summary['published'] ?></strong></div>
        <div><span>Leads</span><strong><?= (int)$leads['total'] ?></strong></div>
        <div><span>Novos</span><strong><?= (int)$leads['novos'] ?></strong></div>
    </div>
</section>
<?php

return [
    'module' => 'divulgacao',
    'group' => 'divulgacao',
    'group_label' => 'Divulgacao',
    'order' => 10,
    'size' => 'wide',
    'html' => ob_get_clean(),
];
