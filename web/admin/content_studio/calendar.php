<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
ContentStudioService::ensureSchema($pdo);

$campaigns = ContentStudioService::activeCampaigns($pdo);
$ideas = $pdo->query("SELECT id, title FROM content_studio_ideas ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$channels = ContentStudioService::references($pdo, 'content_studio_channels', true);
$publications = ContentStudioService::listPublications($pdo);

$publicationStats = [
    'planned' => 0,
    'published' => 0,
    'canceled' => 0,
    'late' => 0,
];
$now = new DateTimeImmutable();

foreach ($publications as $publication) {
    $status = (string)$publication['status'];
    if (isset($publicationStats[$status])) {
        $publicationStats[$status]++;
    }

    if ($status === 'planned' && !empty($publication['scheduled_at'])) {
        $scheduledAt = new DateTimeImmutable((string)$publication['scheduled_at']);
        if ($scheduledAt < $now) {
            $publicationStats['late']++;
        }
    }
}

$title = 'Calendário';
ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Calendário</h1>
            <p class="c-page-subtitle">Publicações planejadas e vínculos com campanhas</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-metrics">
            <div class="c-card cs-metric"><span>Planejadas</span><strong><?= $publicationStats['planned'] ?></strong></div>
            <div class="c-card cs-metric"><span>Publicadas</span><strong><?= $publicationStats['published'] ?></strong></div>
            <div class="c-card cs-metric"><span>Atrasadas</span><strong><?= $publicationStats['late'] ?></strong></div>
            <div class="c-card cs-metric"><span>Canceladas</span><strong><?= $publicationStats['canceled'] ?></strong></div>
        </div>

        <div class="cs-grid-2">
            <div class="c-card">
                <h3>Nova publicação</h3>
                <form method="post" action="/app/actions/content_studio/publication_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <label>Título<input class="c-input" name="title" required></label>
                    <div class="cs-inline">
                        <label>Campanha
                            <select class="c-input" name="campaign_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?= (int)$campaign['id'] ?>"><?= htmlspecialchars($campaign['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Canal
                            <select class="c-input" name="channel_id">
                                <option value="">Nenhum</option>
                                <?php foreach ($channels as $channel): ?>
                                    <option value="<?= (int)$channel['id'] ?>"><?= htmlspecialchars($channel['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>Ideia
                        <select class="c-input" name="idea_id">
                            <option value="">Nenhuma</option>
                            <?php foreach ($ideas as $idea): ?>
                                <option value="<?= (int)$idea['id'] ?>"><?= htmlspecialchars($idea['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Destino<input class="c-input" name="destination_url" placeholder="Landing, checkout, WhatsApp ou post"></label>
                    <div class="cs-inline">
                        <label>Agendada para<input class="c-input" type="datetime-local" name="scheduled_at"></label>
                        <label>Status
                            <select class="c-input" name="status">
                                <option value="planned">Planejada</option>
                                <option value="published">Publicada</option>
                                <option value="canceled">Cancelada</option>
                            </select>
                        </label>
                    </div>
                    <label>Notas<textarea class="c-input" name="notes" rows="3"></textarea></label>
                    <button class="c-btn-primary">Salvar publicação</button>
                </form>
            </div>

            <div class="c-card">
                <h3>Agenda editorial</h3>
                <?php if (empty($publications)): ?>
                    <p>Nenhuma publicação planejada.</p>
                <?php else: ?>
                    <div class="cs-publications">
                        <?php foreach ($publications as $publication): ?>
                            <?php
                                $isLate = false;
                                if (($publication['status'] ?? '') === 'planned' && !empty($publication['scheduled_at'])) {
                                    $isLate = new DateTimeImmutable((string)$publication['scheduled_at']) < $now;
                                }
                            ?>
                            <article class="cs-publication-row">
                                <div>
                                    <strong><?= htmlspecialchars($publication['title']) ?></strong>
                                    <span><?= htmlspecialchars((string)($publication['campaign_name'] ?: $publication['idea_title'] ?: 'Sem campanha')) ?></span>
                                </div>
                                <div>
                                    <small>Canal</small>
                                    <span><?= htmlspecialchars((string)($publication['channel_name'] ?: '-')) ?></span>
                                </div>
                                <div>
                                    <small>Data</small>
                                    <span><?= htmlspecialchars((string)($publication['scheduled_at'] ?: '-')) ?></span>
                                </div>
                                <div class="cs-publication-status">
                                    <span class="c-badge"><?= htmlspecialchars($publication['status']) ?></span>
                                    <?php if ($isLate): ?>
                                        <span class="c-badge c-badge--warning">atrasada</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($publication['destination_url'])): ?>
                                    <a class="c-btn-secondary" target="_blank" href="<?= htmlspecialchars((string)$publication['destination_url']) ?>">Abrir</a>
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
$content .= '<style>.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}.cs-metric span{display:block;color:#9ca3af;font-size:12px}.cs-metric strong{display:block;font-size:24px;margin-top:8px}.cs-grid-2{display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:14px}.cs-form{display:grid;gap:10px}.cs-form label{display:grid;gap:5px;color:#9ca3af;font-size:12px}.cs-inline{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cs-publications{display:grid;gap:8px}.cs-publication-row{display:grid;grid-template-columns:1.3fr .7fr .9fr auto auto;gap:10px;align-items:center;border:1px solid #334155;background:#0f172a;padding:10px}.cs-publication-row strong,.cs-publication-row span,.cs-publication-row small{display:block}.cs-publication-row>div>span{color:#e5e7eb}.cs-publication-row small,.cs-publication-row>div:first-child span{color:#9ca3af;font-size:12px}.cs-publication-status{display:flex;gap:6px;flex-wrap:wrap}@media(max-width:900px){.cs-metrics,.cs-grid-2,.cs-inline,.cs-publication-row{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}}</style>';
require APP_PATH . '/views/layout_admin.php';
