<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
ContentStudioService::ensureSchema($pdo);

$assets = ContentStudioService::listAssets($pdo);
$mediaItems = ContentStudioService::availableMedia($pdo);
$campaigns = ContentStudioService::activeCampaigns($pdo);
$ideas = ContentStudioService::activeIdeas($pdo);
$publications = ContentStudioService::activePublications($pdo);

$title = 'Mídia';
ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Mídia</h1>
            <p class="c-page-subtitle">Organize imagens da biblioteca dentro da produção de conteúdo</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-media-layout">
            <div class="c-card">
                <h3>Vincular imagem</h3>
                <p class="cs-muted">O upload continua na Biblioteca de Imagens. Aqui voce apenas conecta a imagem ao uso correto.</p>

                <?php if (empty($mediaItems)): ?>
                    <p>Nenhuma imagem encontrada na biblioteca.</p>
                    <a class="c-btn-primary" href="/web/admin/media/">Abrir biblioteca</a>
                <?php else: ?>
                    <form method="post" action="/app/actions/content_studio/asset_save.php" class="cs-form">
                        <?= csrf_field() ?>
                        <label>Imagem
                            <select class="c-input" name="media_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($mediaItems as $item): ?>
                                    <option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars($item['file_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="cs-inline">
                            <label>Uso
                                <select class="c-input" name="usage_type">
                                    <option value="reference">Referência</option>
                                    <option value="creative">Criativo</option>
                                    <option value="cover">Capa</option>
                                    <option value="publication">Publicação</option>
                                    <option value="proof">Prova</option>
                                </select>
                            </label>
                            <label>Título<input class="c-input" name="title" placeholder="Opcional"></label>
                        </div>
                        <label>Campanha
                            <select class="c-input" name="campaign_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?= (int)$campaign['id'] ?>"><?= htmlspecialchars($campaign['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Ideia
                            <select class="c-input" name="idea_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($ideas as $idea): ?>
                                    <option value="<?= (int)$idea['id'] ?>"><?= htmlspecialchars($idea['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Publicação
                            <select class="c-input" name="publication_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($publications as $publication): ?>
                                    <option value="<?= (int)$publication['id'] ?>"><?= htmlspecialchars($publication['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Notas<textarea class="c-input" name="notes" rows="3"></textarea></label>
                        <div class="cs-actions">
                            <button class="c-btn-primary">Vincular imagem</button>
                            <a class="c-btn-secondary" href="/web/admin/media/">Biblioteca</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="c-card">
                <h3>Imagens vinculadas</h3>
                <?php if (empty($assets)): ?>
                    <p>Nenhuma imagem vinculada ao Content Studio.</p>
                <?php else: ?>
                    <div class="cs-asset-grid">
                        <?php foreach ($assets as $asset): ?>
                            <article class="cs-asset-card">
                                <img src="/web/media.php?id=<?= (int)$asset['media_id'] ?>&thumb=1" alt="<?= htmlspecialchars((string)$asset['file_name']) ?>">
                                <div>
                                    <strong><?= htmlspecialchars((string)($asset['title'] ?: $asset['file_name'])) ?></strong>
                                    <span><?= htmlspecialchars((string)$asset['usage_type']) ?></span>
                                    <small><?= htmlspecialchars((string)($asset['campaign_name'] ?: $asset['idea_title'] ?: $asset['publication_title'] ?: 'Sem vínculo específico')) ?></small>
                                </div>
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
$content .= '<style>.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-media-layout{display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:14px}.cs-form{display:grid;gap:10px}.cs-form label{display:grid;gap:5px;color:#9ca3af;font-size:12px}.cs-inline{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cs-muted{color:#9ca3af}.cs-actions{display:flex;gap:8px;flex-wrap:wrap}.cs-asset-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}.cs-asset-card{border:1px solid #334155;background:#0f172a;padding:8px;display:grid;gap:8px}.cs-asset-card img{width:100%;aspect-ratio:16/10;object-fit:cover;border:1px solid #334155}.cs-asset-card strong,.cs-asset-card span,.cs-asset-card small{display:block}.cs-asset-card span,.cs-asset-card small{color:#9ca3af;font-size:12px}@media(max-width:900px){.cs-media-layout,.cs-inline{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}}</style>';
require APP_PATH . '/views/layout_admin.php';
