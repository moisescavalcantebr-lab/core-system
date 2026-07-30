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
        COUNT(CASE WHEN status = 'published' THEN 1 END) AS published,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) AS drafts
    FROM divulgacao_pages
")->fetch(PDO::FETCH_ASSOC) ?: ['pages' => 0, 'published' => 0, 'drafts' => 0];

$leads = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COUNT(CASE WHEN status = 'novo' THEN 1 END) AS novos,
        COUNT(CASE WHEN status = 'convertido' THEN 1 END) AS convertidos,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) AS recentes
    FROM divulgacao_leads
")->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'novos' => 0, 'convertidos' => 0, 'recentes' => 0];

$latestPages = $pdo->query("
    SELECT title, slug, status, created_at
    FROM divulgacao_pages
    ORDER BY created_at DESC, id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$latestLeads = $pdo->query("
    SELECT name, email, phone, status, created_at
    FROM divulgacao_leads
    ORDER BY created_at DESC, id DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$published = (int)$summary['published'];
$totalPages = (int)$summary['pages'];
$totalLeads = (int)$leads['total'];
$conversionBase = max(1, $published);
$leadRatio = $totalLeads > 0 ? min(100, (int)round(($totalLeads / $conversionBase) * 100)) : 0;

ob_start();
?>
<section class="dash-module dash-module--divulgacao">
    <div class="dash-module-head">
        <div>
            <h3>Resumo da divulgacao</h3>
            <p>Paginas publicas, leads e campanhas da base.</p>
        </div>
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/divulgacao/index.php">Abrir</a>
    </div>

    <div class="dash-finance-layout">
        <div class="dash-metric-card dash-metric-card--primary">
            <span>Paginas</span>
            <strong><?= $totalPages ?></strong>
            <small><?= $published ?> publicada(s) / <?= (int)$summary['drafts'] ?> rascunho(s)</small>
            <svg class="dash-sparkline dash-sparkline--balance" viewBox="0 0 210 56" aria-hidden="true">
                <polyline points="8,44 58,44 108,<?= $published > 0 ? 30 : 44 ?> 158,<?= $totalPages > 0 ? 20 : 44 ?> 202,<?= $totalPages > 0 ? 12 : 44 ?>"></polyline>
            </svg>
        </div>

        <div class="dash-pair-card">
            <div>
                <span>Leads capturados</span>
                <strong class="dash-good"><?= $totalLeads ?></strong>
                <small><?= (int)$leads['recentes'] ?> nos ultimos 7 dias</small>
                <svg class="dash-sparkline dash-sparkline--income" viewBox="0 0 120 42" aria-hidden="true">
                    <polyline points="4,34 34,34 64,<?= $totalLeads > 0 ? 24 : 34 ?> 94,<?= $totalLeads > 0 ? 14 : 34 ?> 116,<?= $totalLeads > 0 ? 8 : 34 ?>"></polyline>
                </svg>
            </div>
            <div>
                <span>Novos leads</span>
                <strong><?= (int)$leads['novos'] ?></strong>
                <small><?= (int)$leads['convertidos'] ?> convertido(s)</small>
                <svg class="dash-sparkline dash-sparkline--expense" viewBox="0 0 120 42" aria-hidden="true">
                    <polyline points="4,34 34,34 64,34 94,<?= (int)$leads['novos'] > 0 ? 18 : 34 ?> 116,<?= (int)$leads['novos'] > 0 ? 12 : 34 ?>"></polyline>
                </svg>
            </div>
        </div>

        <div class="dash-chart-card">
            <h4>Conversao rapida</h4>
            <div class="dash-rank">
                <div class="dash-rank-row">
                    <span>Leads por pagina publicada</span>
                    <div><i style="width:<?= $leadRatio ?>%;"></i></div>
                    <strong><?= $leadRatio ?>%</strong>
                </div>
            </div>
            <p>Indicador simples para leitura rapida das paginas ativas.</p>
        </div>

        <div class="dash-list-card">
            <h4>Paginas recentes</h4>
            <?php if (!$latestPages): ?>
                <p>Nenhuma pagina criada ainda.</p>
            <?php else: ?>
                <?php foreach ($latestPages as $page): ?>
                    <div class="dash-list-row">
                        <span><?= htmlspecialchars((string)$page['title']) ?></span>
                        <strong><?= htmlspecialchars((string)$page['status']) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="dash-list-card">
            <h4>Leads recentes</h4>
            <?php if (!$latestLeads): ?>
                <p>Nenhum lead capturado ainda.</p>
            <?php else: ?>
                <?php foreach ($latestLeads as $lead): ?>
                    <div class="dash-list-row">
                        <span><?= htmlspecialchars((string)$lead['name']) ?></span>
                        <strong><?= htmlspecialchars((string)$lead['status']) ?></strong>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="dash-chart-card">
            <h4>Campanhas</h4>
            <div class="dash-upgrade-box">
                <strong>Espaco para evolucao</strong>
                <p>Origem dos leads, campanhas e metricas entram aqui nas proximas etapas.</p>
            </div>
        </div>
    </div>
</section>
<?php

return [
    'type' => 'panel',
    'module' => 'divulgacao',
    'group' => 'divulgacao',
    'group_label' => 'Divulgacao',
    'order' => 10,
    'size' => 'wide',
    'html' => ob_get_clean(),
];
