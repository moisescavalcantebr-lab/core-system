<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

landingPageRequireAdmin();
landingPageEnsureSchema($pdo);

$title = 'Landing Pages';
$summary = landingPageSummary($pdo);
$pages = $pdo->query("
    SELECT p.*,
           COUNT(l.id) AS leads_count,
           COUNT(CASE WHEN l.status = 'novo' THEN 1 END) AS new_leads_count
    FROM landing_page_pages p
    LEFT JOIN landing_page_leads l ON l.page_id = p.id
    GROUP BY p.id
    ORDER BY p.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="c-page-header">
    <div>
        <h1>Landing Pages</h1>
        <p>Landing pages simples para campanhas, produtos e captacao de leads.</p>
    </div>
    <div class="c-page-actions landing_page-actions">
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/landing_page/leads.php">Leads</a>
        <a class="c-btn-primary" href="<?= PROJECT_URL ?>/admin/landing_page/create.php">Nova pagina</a>
    </div>
</div>

<?php flash_show(); ?>

<div class="c-grid-3 landing_page-summary">
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
        <div class="c-table-wrap landing_page-table-wrap">
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
                        <?php $template = landingPageTemplate((string)$page['template_key']); ?>
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
                                <span class="c-badge <?= landingPageBadgeClass((string)$page['status']) ?>">
                                    <?= landingPageStatusLabel((string)$page['status']) ?>
                                </span>
                            </td>
                            <td>
                              <div class="landing_page-row-actions">
                                <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/landing_page/edit.php?id=<?= (int)$page['id'] ?>">Editar</a>
                                <a class="c-btn-secondary" href="<?= landingPagePublicUrl((string)$page['slug']) ?>" target="_blank" rel="noopener noreferrer">Ver</a>
                                <?php if ($page['status'] === 'published'): ?>
                                    <button class="c-btn-secondary landing_page-copy" type="button" title="Copiar link" aria-label="Copiar link de <?= htmlspecialchars((string)$page['title']) ?>" data-copy-link="<?= htmlspecialchars(landingPagePublicUrl((string)$page['slug'])) ?>">&#x29C9;</button>
                                <?php endif; ?>
                              </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<p class="landing_page-copy-status" role="status" aria-live="polite"></p>
<script>
document.querySelectorAll('[data-copy-link]').forEach(button => {
    button.addEventListener('click', async () => {
        const url = new URL(button.dataset.copyLink, window.location.href).href;
        const status = document.querySelector('.landing_page-copy-status');
        button.disabled = true;
        try {
            try {
                if (!navigator.clipboard) throw new Error('Clipboard indisponivel');
                await navigator.clipboard.writeText(url);
            } catch (error) {
                const field = document.createElement('textarea');
                field.value = url;
                field.style.cssText = 'position:fixed;left:-9999px;top:0';
                document.body.appendChild(field);
                try {
                    field.select();
                    if (!document.execCommand('copy')) throw new Error('Nao foi possivel copiar');
                } finally {
                    field.remove();
                    button.focus();
                }
            }
            status.textContent = 'Link copiado';
        } catch (error) {
            status.textContent = 'Nao foi possivel copiar o link. Abra a pagina em Ver.';
        } finally {
            button.disabled = false;
        }
    });
});
</script>
<style>
.landing_page-table-wrap { max-width:100%; overflow-x:auto; }
.landing_page-table-wrap .c-table { min-width:600px; }
.landing_page-row-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.landing_page-row-actions .c-btn-secondary { margin-right:0; }
.landing_page-copy { width:30px; height:30px; padding:0; font-size:18px; display:inline-flex; align-items:center; justify-content:center; }
.landing_page-copy-status { min-height:18px; margin:8px 0; color:var(--text-secondary); }
.landing_page-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.landing_page-actions a { display:inline-flex; align-items:center; justify-content:center; min-height:30px; box-sizing:border-box; margin-right:0; white-space:nowrap; }
.landing_page-actions .c-btn-primary { background:var(--primary-color, var(--color-primary, #3b82f6)); border:1px solid var(--primary-color, var(--color-primary, #3b82f6)); }
.c-grid-3 { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.landing_page-summary { margin-bottom: 18px; }
.c-metric-card strong { display:block; font-size: 28px; margin: 8px 0 4px; }
@media (max-width: 760px) { .c-grid-3 { grid-template-columns: 1fr; } }
</style>
<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
