<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$stats = ContentStudioService::dashboard($pdo);
$campaigns = array_slice(ContentStudioService::listCampaigns($pdo), 0, 5);
$ideas = array_slice(ContentStudioService::listIdeas($pdo), 0, 5);
$publications = array_slice(ContentStudioService::listPublications($pdo), 0, 5);
$assets = array_slice(ContentStudioService::listAssets($pdo), 0, 4);

$title = 'Content Studio';

ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Content Studio</h1>
            <p class="c-page-subtitle">Organização de campanhas, ideias e produção de conteúdo</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-metrics">
            <div class="c-card cs-metric"><span>Campanhas ativas</span><strong><?= $stats['campaigns_active'] ?></strong></div>
            <div class="c-card cs-metric"><span>Ideias abertas</span><strong><?= $stats['ideas_open'] ?></strong></div>
            <div class="c-card cs-metric"><span>Publicações planejadas</span><strong><?= $stats['publications_planned'] ?></strong></div>
            <div class="c-card cs-metric"><span>Atrasadas</span><strong><?= $stats['publications_late'] ?></strong></div>
            <div class="c-card cs-metric"><span>Leads atribuídos</span><strong><?= $stats['leads_content_studio'] ?></strong></div>
            <div class="c-card cs-metric"><span>Mídias vinculadas</span><strong><?= $stats['assets_active'] ?></strong></div>
        </div>

        <div class="cs-metrics cs-metrics-secondary">
            <div class="c-card cs-metric"><span>Roteiros em produção</span><strong><?= $stats['scripts_draft'] ?></strong></div>
            <div class="c-card cs-metric"><span>Prompts ativos</span><strong><?= $stats['prompts_active'] ?></strong></div>
            <div class="c-card cs-metric"><span>Publicadas</span><strong><?= $stats['publications_published'] ?></strong></div>
            <div class="c-card cs-metric"><span>Canais ativos</span><strong><?= $stats['channels_active'] ?></strong></div>
            <div class="c-card cs-metric"><span>Campanhas rascunho</span><strong><?= $stats['campaigns_draft'] ?></strong></div>
            <div class="c-card cs-metric"><span>Leads no Core</span><strong><?= $stats['leads_total'] ?></strong></div>
        </div>

        <div class="cs-grid-2">
            <div class="c-card">
                <div class="cs-card-head">
                    <h3>Campanhas recentes</h3>
                    <a class="c-btn-secondary" href="/web/admin/content_studio/campaigns.php">Abrir</a>
                </div>
                <?php if (empty($campaigns)): ?>
                    <p class="c-text-muted">Nenhuma campanha criada ainda.</p>
                <?php else: ?>
                    <div class="cs-list">
                        <?php foreach ($campaigns as $campaign): ?>
                            <div class="cs-list-row">
                                <div>
                                    <strong><?= htmlspecialchars((string)$campaign['name']) ?></strong>
                                    <span><?= htmlspecialchars((string)($campaign['objective'] ?: 'Sem objetivo definido')) ?></span>
                                </div>
                                <span class="c-badge"><?= htmlspecialchars((string)$campaign['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-card">
                <div class="cs-card-head"><h3>Agenda próxima</h3><a class="c-btn-secondary" href="/web/admin/content_studio/calendar.php">Abrir</a></div>
                <?php if (empty($publications)): ?>
                    <p class="c-text-muted">Nenhuma publicação planejada ainda.</p>
                <?php else: ?>
                    <div class="cs-list">
                        <?php foreach ($publications as $publication): ?>
                            <div class="cs-list-row">
                                <div>
                                    <strong><?= htmlspecialchars((string)$publication['title']) ?></strong>
                                    <span><?= htmlspecialchars((string)($publication['campaign_name'] ?: $publication['channel_name'] ?: 'Sem campanha')) ?></span>
                                </div>
                                <span class="c-badge"><?= htmlspecialchars((string)$publication['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cs-grid-2">
            <div class="c-card">
                <div class="cs-card-head"><h3>Ideias recentes</h3><a class="c-btn-secondary" href="/web/admin/content_studio/ideas.php">Abrir</a></div>
                <?php if (empty($ideas)): ?>
                    <p class="c-text-muted">Nenhuma ideia registrada ainda.</p>
                <?php else: ?>
                    <div class="cs-list">
                        <?php foreach ($ideas as $idea): ?>
                            <div class="cs-list-row">
                                <div>
                                    <strong><?= htmlspecialchars((string)$idea['title']) ?></strong>
                                    <span><?= htmlspecialchars((string)($idea['campaign_name'] ?: 'Sem campanha')) ?></span>
                                </div>
                                <span class="c-badge"><?= htmlspecialchars((string)$idea['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-card">
                <div class="cs-card-head"><h3>Mídias vinculadas</h3><a class="c-btn-secondary" href="/web/admin/content_studio/media.php">Abrir</a></div>
                <?php if (empty($assets)): ?>
                    <p class="c-text-muted">Nenhuma imagem vinculada ainda.</p>
                <?php else: ?>
                    <div class="cs-assets-mini">
                        <?php foreach ($assets as $asset): ?>
                            <div class="cs-asset-mini">
                                <img src="/web/media.php?id=<?= (int)$asset['media_id'] ?>&thumb=1" alt="<?= htmlspecialchars((string)$asset['file_name']) ?>">
                                <span><?= htmlspecialchars((string)($asset['title'] ?: $asset['usage_type'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<style>
.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.cs-metrics-secondary{margin-top:12px}.cs-metric span{display:block;color:#9ca3af;font-size:12px}.cs-metric strong{display:block;font-size:24px;margin-top:8px}.cs-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}.cs-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.cs-card-head h3{margin:0}.cs-list{display:grid;gap:8px}.cs-list-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #334155;padding:10px;background:#0f172a}.cs-list-row span{display:block;color:#9ca3af;font-size:12px;margin-top:3px}.cs-assets-mini{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.cs-asset-mini{border:1px solid #334155;background:#0f172a;padding:6px}.cs-asset-mini img{width:100%;aspect-ratio:16/10;object-fit:cover}.cs-asset-mini span{display:block;color:#9ca3af;font-size:12px;margin-top:6px}@media(max-width:900px){.cs-metrics,.cs-grid-2,.cs-assets-mini{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}}</style>';

require APP_PATH . '/views/layout_admin.php';
