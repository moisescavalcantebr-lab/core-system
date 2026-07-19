<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
ContentStudioService::ensureSchema($pdo);

$ideas = ContentStudioService::listIdeas($pdo);
$campaigns = ContentStudioService::activeCampaigns($pdo);
$niches = ContentStudioService::references($pdo, 'content_studio_niches', true);
$personas = ContentStudioService::references($pdo, 'content_studio_personas', true);

$title = 'Ideias';
ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Ideias</h1>
            <p class="c-page-subtitle">Fila de ganchos, formatos e temas para produção</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-grid-2">
            <div class="c-card">
                <h3>Nova ideia</h3>
                <form method="post" action="/app/actions/content_studio/idea_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <label>Título<input class="c-input" name="title" required></label>
                    <label>Gancho<input class="c-input" name="hook" placeholder="Primeira frase ou promessa"></label>
                    <div class="cs-inline">
                        <label>Campanha
                            <select class="c-input" name="campaign_id">
                                <option value="">Sem campanha</option>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <option value="<?= (int)$campaign['id'] ?>"><?= htmlspecialchars($campaign['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Formato<input class="c-input" name="format" placeholder="Reels, Shorts, post, anúncio"></label>
                    </div>
                    <div class="cs-inline">
                        <label>Nicho
                            <select class="c-input" name="niche_id">
                                <option value="">Nenhum</option>
                                <?php foreach ($niches as $niche): ?>
                                    <option value="<?= (int)$niche['id'] ?>"><?= htmlspecialchars($niche['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Personagem
                            <select class="c-input" name="persona_id">
                                <option value="">Nenhum</option>
                                <?php foreach ($personas as $persona): ?>
                                    <option value="<?= (int)$persona['id'] ?>"><?= htmlspecialchars($persona['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="cs-inline">
                        <label>Prioridade
                            <select class="c-input" name="priority">
                                <option value="normal">Normal</option>
                                <option value="high">Alta</option>
                                <option value="low">Baixa</option>
                            </select>
                        </label>
                        <label>Status
                            <select class="c-input" name="status">
                                <option value="idea">Ideia</option>
                                <option value="draft">Rascunho</option>
                                <option value="review">Revisão</option>
                                <option value="approved">Aprovada</option>
                                <option value="published">Publicada</option>
                            </select>
                        </label>
                    </div>
                    <label>Notas<textarea class="c-input" name="notes" rows="4"></textarea></label>
                    <button class="c-btn-primary">Salvar ideia</button>
                </form>
            </div>

            <div class="c-card">
                <h3>Ideias registradas</h3>
                <?php if (empty($ideas)): ?>
                    <p>Nenhuma ideia criada.</p>
                <?php else: ?>
                    <div class="c-table-wrapper">
                        <table class="c-table">
                            <thead><tr><th>Ideia</th><th>Campanha</th><th>Nicho</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($ideas as $idea): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($idea['title']) ?></strong><br><small><?= htmlspecialchars((string)$idea['hook']) ?></small></td>
                                    <td><?= htmlspecialchars((string)($idea['campaign_name'] ?: '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($idea['niche_name'] ?: '-')) ?></td>
                                    <td><span class="c-badge"><?= htmlspecialchars($idea['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<style>.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-grid-2{display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:14px}.cs-form{display:grid;gap:10px}.cs-form label{display:grid;gap:5px;color:#9ca3af;font-size:12px}.cs-inline{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}@media(max-width:900px){.cs-grid-2,.cs-inline{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}}</style>';
require APP_PATH . '/views/layout_admin.php';
