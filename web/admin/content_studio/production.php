<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
ContentStudioService::ensureSchema($pdo);

$ideas = ContentStudioService::activeIdeas($pdo);
$scripts = ContentStudioService::listScripts($pdo);
$prompts = ContentStudioService::listPrompts($pdo);
$activeScripts = ContentStudioService::activeScripts($pdo);

$scriptStatusOptions = [
    'draft' => 'Rascunho',
    'review' => 'Revisao',
    'approved' => 'Aprovado',
    'published' => 'Publicado',
    'archived' => 'Arquivado',
];

$promptStatusOptions = [
    'active' => 'Ativo',
    'draft' => 'Rascunho',
    'archived' => 'Arquivado',
];

$title = 'Produção';
ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Produção</h1>
            <p class="c-page-subtitle">Roteiros e prompts ligados às ideias do Content Studio</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-grid-2">
            <div class="c-card">
                <h3>Novo roteiro</h3>
                <form method="post" action="/app/actions/content_studio/script_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <label>Ideia
                        <select class="c-input" name="idea_id">
                            <option value="">Sem ideia vinculada</option>
                            <?php foreach ($ideas as $idea): ?>
                                <option value="<?= (int)$idea['id'] ?>"><?= htmlspecialchars($idea['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Título<input class="c-input" name="title" required></label>
                    <div class="cs-inline">
                        <label>Formato<input class="c-input" name="format" placeholder="Reels, Shorts, anúncio..."></label>
                        <label>Status
                            <select class="c-input" name="status">
                                <option value="draft">Rascunho</option>
                                <option value="review">Revisão</option>
                                <option value="approved">Aprovado</option>
                                <option value="published">Publicado</option>
                            </select>
                        </label>
                    </div>
                    <label>Roteiro<textarea class="c-input" name="script_body" rows="7" placeholder="Estrutura, falas, cenas ou sequência do conteúdo"></textarea></label>
                    <label>CTA<textarea class="c-input" name="cta" rows="2" placeholder="Chamada final, link, WhatsApp, página ou próxima ação"></textarea></label>
                    <button class="c-btn-primary">Salvar roteiro</button>
                </form>
            </div>

            <div class="c-card">
                <h3>Novo prompt</h3>
                <form method="post" action="/app/actions/content_studio/prompt_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <div class="cs-inline">
                        <label>Ideia
                            <select class="c-input" name="idea_id">
                                <option value="">Nenhuma</option>
                                <?php foreach ($ideas as $idea): ?>
                                    <option value="<?= (int)$idea['id'] ?>"><?= htmlspecialchars($idea['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Roteiro
                            <select class="c-input" name="script_id">
                                <option value="">Nenhum</option>
                                <?php foreach ($activeScripts as $script): ?>
                                    <option value="<?= (int)$script['id'] ?>"><?= htmlspecialchars($script['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label>Título<input class="c-input" name="title" required></label>
                    <label>Contexto<input class="c-input" name="context" placeholder="Imagem, vídeo, legenda, anúncio..."></label>
                    <label>Prompt<textarea class="c-input" name="prompt_text" rows="8" required></textarea></label>
                    <button class="c-btn-primary">Salvar prompt</button>
                </form>
            </div>
        </div>

        <div class="cs-grid-2 cs-output-grid">
            <div class="c-card">
                <h3>Roteiros</h3>
                <?php if (empty($scripts)): ?>
                    <p>Nenhum roteiro criado.</p>
                <?php else: ?>
                    <div class="cs-reference-list">
                        <?php foreach ($scripts as $script): ?>
                            <details class="cs-reference-item">
                                <summary>
                                    <span>
                                        <strong><?= htmlspecialchars($script['title']) ?></strong>
                                        <small><?= htmlspecialchars((string)($script['idea_title'] ?: $script['campaign_name'] ?: 'Sem vinculo')) ?></small>
                                    </span>
                                    <em><?= htmlspecialchars($scriptStatusOptions[(string)$script['status']] ?? (string)$script['status']) ?></em>
                                </summary>
                                <form method="post" action="/app/actions/content_studio/script_save.php" class="cs-form cs-edit-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
                                    <label>Ideia
                                        <select class="c-input" name="idea_id">
                                            <option value="">Sem ideia vinculada</option>
                                            <?php foreach ($ideas as $idea): ?>
                                                <option value="<?= (int)$idea['id'] ?>" <?= (int)($script['idea_id'] ?? 0) === (int)$idea['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($idea['title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Titulo<input class="c-input" name="title" value="<?= htmlspecialchars($script['title']) ?>" required></label>
                                    <div class="cs-inline">
                                        <label>Formato<input class="c-input" name="format" value="<?= htmlspecialchars((string)($script['format'] ?? '')) ?>"></label>
                                        <label>Status
                                            <select class="c-input" name="status">
                                                <?php foreach ($scriptStatusOptions as $value => $label): ?>
                                                    <option value="<?= htmlspecialchars($value) ?>" <?= (string)$script['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <label>Roteiro<textarea class="c-input" name="script_body" rows="6"><?= htmlspecialchars((string)($script['script_body'] ?? '')) ?></textarea></label>
                                    <label>CTA<textarea class="c-input" name="cta" rows="2"><?= htmlspecialchars((string)($script['cta'] ?? '')) ?></textarea></label>
                                    <div class="cs-actions">
                                        <button class="c-btn-primary">Salvar</button>
                                    </div>
                                </form>
                                <form method="post" action="/app/actions/content_studio/script_delete.php" class="cs-delete-form" onsubmit="return confirm('Excluir este roteiro? Os prompts vinculados serao mantidos sem roteiro.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
                                    <button class="c-btn-danger">Apagar</button>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-card">
                <h3>Prompts</h3>
                <?php if (empty($prompts)): ?>
                    <p>Nenhum prompt salvo.</p>
                <?php else: ?>
                    <div class="cs-reference-list">
                        <?php foreach ($prompts as $prompt): ?>
                            <details class="cs-reference-item">
                                <summary>
                                    <span>
                                        <strong><?= htmlspecialchars($prompt['title']) ?></strong>
                                        <small><?= htmlspecialchars((string)($prompt['context'] ?: $prompt['script_title'] ?: $prompt['idea_title'] ?: 'Sem contexto')) ?></small>
                                    </span>
                                    <em><?= htmlspecialchars($promptStatusOptions[(string)$prompt['status']] ?? (string)$prompt['status']) ?></em>
                                </summary>
                                <form method="post" action="/app/actions/content_studio/prompt_save.php" class="cs-form cs-edit-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$prompt['id'] ?>">
                                    <div class="cs-inline">
                                        <label>Ideia
                                            <select class="c-input" name="idea_id">
                                                <option value="">Nenhuma</option>
                                                <?php foreach ($ideas as $idea): ?>
                                                    <option value="<?= (int)$idea['id'] ?>" <?= (int)($prompt['idea_id'] ?? 0) === (int)$idea['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($idea['title']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label>Roteiro
                                            <select class="c-input" name="script_id">
                                                <option value="">Nenhum</option>
                                                <?php foreach ($activeScripts as $script): ?>
                                                    <option value="<?= (int)$script['id'] ?>" <?= (int)($prompt['script_id'] ?? 0) === (int)$script['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($script['title']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <label>Titulo<input class="c-input" name="title" value="<?= htmlspecialchars($prompt['title']) ?>" required></label>
                                    <div class="cs-inline">
                                        <label>Contexto<input class="c-input" name="context" value="<?= htmlspecialchars((string)($prompt['context'] ?? '')) ?>"></label>
                                        <label>Status
                                            <select class="c-input" name="status">
                                                <?php foreach ($promptStatusOptions as $value => $label): ?>
                                                    <option value="<?= htmlspecialchars($value) ?>" <?= (string)$prompt['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    </div>
                                    <label>Prompt<textarea class="c-input" name="prompt_text" rows="7" required><?= htmlspecialchars((string)($prompt['prompt_text'] ?? '')) ?></textarea></label>
                                    <div class="cs-actions">
                                        <button class="c-btn-primary">Salvar</button>
                                    </div>
                                </form>
                                <form method="post" action="/app/actions/content_studio/prompt_delete.php" class="cs-delete-form" onsubmit="return confirm('Excluir este prompt?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$prompt['id'] ?>">
                                    <button class="c-btn-danger">Apagar</button>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<style>.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.cs-output-grid{margin-top:14px}.cs-form{display:grid;gap:10px}.cs-form label{display:grid;gap:5px;color:#9ca3af;font-size:12px}.cs-inline{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cs-reference-list{display:grid;gap:8px}.cs-reference-item{border:1px solid #334155;background:#111827;border-radius:6px;padding:0}.cs-reference-item summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;list-style:none}.cs-reference-item summary::-webkit-details-marker{display:none}.cs-reference-item summary strong{display:block}.cs-reference-item summary small{display:block;color:#9ca3af;font-size:12px;margin-top:3px}.cs-reference-item summary em{font-style:normal;color:#9ca3af;font-size:11px;border:1px solid #334155;border-radius:4px;padding:2px 6px;white-space:nowrap}.cs-edit-form{border-top:1px solid #273449;padding:10px}.cs-delete-form{display:flex;justify-content:flex-end;padding:0 10px 10px}.cs-actions{display:flex;justify-content:flex-end}.c-btn-danger{border:1px solid #7f1d1d;background:#451a1a;color:#fecaca;border-radius:6px;padding:8px 12px;font-weight:700}.c-btn-danger:hover{background:#7f1d1d}@media(max-width:900px){.cs-grid-2,.cs-inline{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}}</style>';

require APP_PATH . '/views/layout_admin.php';
