<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
ContentStudioService::ensureSchema($pdo);

$campaigns = ContentStudioService::listCampaigns($pdo);
$pages = $pdo->query("SELECT id, title, slug FROM core_page_contents WHERE type IN ('page','blog') ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$projects = $pdo->query("SELECT id, name, slug FROM projects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$selectedPageId = (int)($_GET['page_id'] ?? 0);

$title = 'Campanhas';
ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Campanhas</h1>
            <p class="c-page-subtitle">Campanhas conectadas a páginas, projetos e leads</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-grid-2">
            <div class="c-card">
                <h3>Nova campanha</h3>
                <form method="post" action="/app/actions/content_studio/campaign_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <label>Nome<input class="c-input" name="name" required></label>
                    <label>Objetivo<input class="c-input" name="objective" placeholder="Ex: captar leads para produto"></label>
                    <label>Chave da campanha<input class="c-input" name="campaign_key" placeholder="Ex: produto-x-julho"></label>
                    <label>Landing externa<input class="c-input" name="landing_url" placeholder="https://..."></label>
                    <div class="cs-inline">
                        <label>Página
                            <select class="c-input" name="page_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($pages as $page): ?>
                                    <option value="<?= (int)$page['id'] ?>" <?= $selectedPageId === (int)$page['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($page['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Projeto
                            <select class="c-input" name="project_id">
                                <option value="">Nenhum</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="cs-inline">
                        <label>Status
                            <select class="c-input" name="status">
                                <option value="draft">Rascunho</option>
                                <option value="active">Ativa</option>
                                <option value="paused">Pausada</option>
                                <option value="finished">Finalizada</option>
                            </select>
                        </label>
                        <label>Início<input class="c-input" type="date" name="starts_at"></label>
                        <label>Fim<input class="c-input" type="date" name="ends_at"></label>
                    </div>
                    <label>Notas<textarea class="c-input" name="notes" rows="3"></textarea></label>
                    <button class="c-btn-primary">Salvar campanha</button>
                </form>
            </div>

            <div class="c-card">
                <h3>Campanhas</h3>
                <?php if (empty($campaigns)): ?>
                    <p>Nenhuma campanha criada.</p>
                <?php else: ?>
                    <div class="cs-campaign-list">
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php
                                $destination = ContentStudioService::campaignDestination($campaign);
                                $trackingUrl = ContentStudioService::campaignTrackingUrl($campaign);
                                $instagramUrl = ContentStudioService::campaignTrackingUrl($campaign, 'instagram');
                                $trafficUrl = ContentStudioService::campaignTrackingUrl($campaign, 'trafego-pago');
                            ?>
                            <article class="cs-campaign-card">
                                <div class="cs-campaign-main">
                                    <div>
                                        <strong><?= htmlspecialchars($campaign['name']) ?></strong>
                                        <span><?= htmlspecialchars((string)($campaign['objective'] ?: 'Sem objetivo definido')) ?></span>
                                    </div>
                                    <div class="cs-campaign-badges">
                                        <span class="c-badge"><?= htmlspecialchars($campaign['status']) ?></span>
                                        <span class="c-badge"><?= (int)($campaign['leads_count'] ?? 0) ?> lead(s)</span>
                                    </div>
                                </div>

                                <div class="cs-campaign-meta">
                                    <div>
                                        <small>Destino</small>
                                        <span><?= htmlspecialchars((string)($campaign['page_title'] ?: $destination ?: '-')) ?></span>
                                    </div>
                                    <div>
                                        <small>Projeto</small>
                                        <span><?= htmlspecialchars((string)($campaign['project_name'] ?: '-')) ?></span>
                                    </div>
                                    <div>
                                        <small>Chave</small>
                                        <span><?= htmlspecialchars($campaign['campaign_key']) ?></span>
                                    </div>
                                </div>

                                <?php if ($trackingUrl !== ''): ?>
                                    <div class="cs-tracking">
                                        <small>Link rastreável</small>
                                        <input class="c-input" readonly value="<?= htmlspecialchars($trackingUrl) ?>" onclick="this.select()">
                                        <div class="cs-tracking-actions">
                                            <a class="c-btn-secondary" target="_blank" href="<?= htmlspecialchars($trackingUrl) ?>">Abrir</a>
                                            <input class="c-input" readonly value="<?= htmlspecialchars($instagramUrl) ?>" onclick="this.select()" title="Instagram">
                                            <input class="c-input" readonly value="<?= htmlspecialchars($trafficUrl) ?>" onclick="this.select()" title="Tráfego pago">
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="c-text-muted">Defina uma página ou landing externa para gerar o link rastreável.</p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<style>.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-grid-2{display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:14px}.cs-form{display:grid;gap:10px}.cs-form label{display:grid;gap:5px;color:#9ca3af;font-size:12px}.cs-inline{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cs-inline:has(label:nth-child(3)){grid-template-columns:repeat(3,minmax(0,1fr))}.cs-campaign-list{display:grid;gap:12px}.cs-campaign-card{border:1px solid #334155;background:#0f172a;padding:12px}.cs-campaign-main{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.cs-campaign-main strong,.cs-campaign-main span{display:block}.cs-campaign-main span{color:#9ca3af;font-size:12px;margin-top:4px}.cs-campaign-badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.cs-campaign-meta{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:8px;margin-top:12px}.cs-campaign-meta>div{border:1px solid #243244;padding:8px;background:#111827}.cs-campaign-meta small,.cs-tracking small{display:block;color:#9ca3af;font-size:11px;margin-bottom:3px}.cs-campaign-meta span{font-weight:700}.cs-tracking{display:grid;gap:8px;margin-top:12px}.cs-tracking-actions{display:grid;grid-template-columns:auto 1fr 1fr;gap:8px;align-items:center}@media(max-width:900px){.cs-grid-2,.cs-inline,.cs-inline:has(label:nth-child(3)),.cs-campaign-meta,.cs-tracking-actions{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}.cs-campaign-main{display:grid}.cs-campaign-badges{justify-content:flex-start}}</style>';
require APP_PATH . '/views/layout_admin.php';
