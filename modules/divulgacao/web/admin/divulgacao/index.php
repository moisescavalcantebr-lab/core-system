<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

divulgacaoRequireAdmin();
divulgacaoEnsureSchema($pdo);

$title = 'Divulgacao';
$summary = divulgacaoSummary($pdo);
$pages = $pdo->query("
    SELECT p.*,
           COUNT(l.id) AS leads_count,
           COUNT(CASE WHEN l.status = 'novo' THEN 1 END) AS new_leads_count
    FROM divulgacao_pages p
    LEFT JOIN divulgacao_leads l ON l.page_id = p.id
    GROUP BY p.id
    ORDER BY p.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="c-page-header">
    <div>
        <h1>Divulgacao</h1>
        <p>Landing pages simples para campanhas, produtos e captacao de leads.</p>
    </div>
    <div class="c-page-actions">
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/divulgacao/leads.php">Leads</a>
        <a class="c-btn-primary" href="<?= PROJECT_URL ?>/admin/divulgacao/create.php">Nova pagina</a>
    </div>
</div>

<?php flash_show(); ?>

<div class="c-grid-3 divulgacao-summary">
    <div class="c-card c-metric-card">
        <span>Paginas</span>
        <strong><?= (int)$summary['total_pages'] ?></strong>
        <small><?= (int)$summary['published_pages'] ?> publicadas</small>
    </div>
    <div class="c-card c-metric-card">
        <span>Leads</span>
        <strong><?= (int)$summary['total_leads'] ?></strong>
        <small><?= (int)$summary['new_leads'] ?> novos</small>
    </div>
    <div class="c-card c-metric-card">
        <span>Conversoes</span>
        <strong><?= (int)$summary['converted_leads'] ?></strong>
        <small>Marcadas manualmente</small>
    </div>
</div>

<div class="c-card">
    <h2>Paginas</h2>
    <?php if (empty($pages)): ?>
        <p>Nenhuma pagina criada ainda.</p>
    <?php else: ?>
        <div class="c-table-wrap">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Titulo</th>
                        <th>Slug</th>
                        <th>Modelo</th>
                        <th>Leads</th>
                        <th>Status</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <?php $template = divulgacaoTemplate((string)$page['template_key']); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$page['title']) ?></strong></td>
                            <td><?= htmlspecialchars((string)$page['slug']) ?></td>
                            <td><?= htmlspecialchars((string)$template['label']) ?></td>
                            <td>
                                <?= (int)$page['leads_count'] ?>
                                <?php if ((int)$page['new_leads_count'] > 0): ?>
                                    <span class="c-badge c-badge--info"><?= (int)$page['new_leads_count'] ?> novo(s)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="c-badge <?= divulgacaoBadgeClass((string)$page['status']) ?>">
                                    <?= divulgacaoStatusLabel((string)$page['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/divulgacao/edit.php?id=<?= (int)$page['id'] ?>">Editar</a>
                                <a class="c-btn-secondary" href="<?= divulgacaoPublicUrl((string)$page['slug']) ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.c-grid-3 { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.divulgacao-summary { margin-bottom: 18px; }
.c-metric-card strong { display:block; font-size: 28px; margin: 8px 0 4px; }
@media (max-width: 760px) { .c-grid-3 { grid-template-columns: 1fr; } }
</style>
<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
