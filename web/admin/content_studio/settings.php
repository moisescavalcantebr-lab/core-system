<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
ContentStudioService::ensureSchema($pdo);

$channels = ContentStudioService::references($pdo, 'content_studio_channels');
$niches = ContentStudioService::references($pdo, 'content_studio_niches');
$personas = ContentStudioService::references($pdo, 'content_studio_personas');

$statusOptions = [
    'active' => 'Ativo',
    'inactive' => 'Inativo',
];

$title = 'Configurações do Content Studio';
ob_start();
?>

<div class="c-page c-content-studio">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configurações</h1>
            <p class="c-page-subtitle">Canais, nichos e personagens usados na produção</p>
        </div>
        <?php require __DIR__ . '/_nav.php'; ?>
    </div>

    <div class="c-page-content">
        <div class="cs-grid-3">
            <div class="c-card">
                <h3>Canais</h3>
                <form method="post" action="/app/actions/content_studio/reference_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="channel">
                    <label>Nome<input class="c-input" name="name" placeholder="Instagram principal" required></label>
                    <label>Plataforma<input class="c-input" name="platform" placeholder="Instagram, TikTok, YouTube"></label>
                    <label>Perfil<input class="c-input" name="handle" placeholder="@perfil"></label>
                    <label>URL<input class="c-input" name="url" placeholder="https://..."></label>
                    <button class="c-btn-primary">Adicionar canal</button>
                </form>
                <div class="cs-reference-list">
                    <?php foreach ($channels as $channel): ?>
                        <details class="cs-reference-item">
                            <summary>
                                <span>
                                    <strong><?= htmlspecialchars($channel['name']) ?></strong>
                                    <small><?= htmlspecialchars($channel['platform']) ?></small>
                                </span>
                                <em><?= htmlspecialchars($statusOptions[$channel['status']] ?? (string)$channel['status']) ?></em>
                            </summary>
                            <form method="post" action="/app/actions/content_studio/reference_save.php" class="cs-form cs-edit-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="channel">
                                <input type="hidden" name="id" value="<?= (int)$channel['id'] ?>">
                                <label>Nome<input class="c-input" name="name" value="<?= htmlspecialchars($channel['name']) ?>" required></label>
                                <label>Plataforma<input class="c-input" name="platform" value="<?= htmlspecialchars($channel['platform']) ?>"></label>
                                <label>Perfil<input class="c-input" name="handle" value="<?= htmlspecialchars((string)($channel['handle'] ?? '')) ?>"></label>
                                <label>URL<input class="c-input" name="url" value="<?= htmlspecialchars((string)($channel['url'] ?? '')) ?>"></label>
                                <label>Status
                                    <select class="c-input" name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= (string)$channel['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="cs-actions">
                                    <button class="c-btn-primary">Salvar</button>
                                </div>
                            </form>
                            <form method="post" action="/app/actions/content_studio/reference_delete.php" class="cs-delete-form" onsubmit="return confirm('Remover este canal? Se estiver em uso, ele sera apenas desativado.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="channel">
                                <input type="hidden" name="id" value="<?= (int)$channel['id'] ?>">
                                <button class="c-btn-danger">Apagar</button>
                            </form>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="c-card">
                <h3>Nichos</h3>
                <form method="post" action="/app/actions/content_studio/reference_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="niche">
                    <label>Nome<input class="c-input" name="name" placeholder="Finanças, futebol, afiliados..." required></label>
                    <label>Descrição<textarea class="c-input" name="description" rows="4"></textarea></label>
                    <button class="c-btn-primary">Adicionar nicho</button>
                </form>
                <div class="cs-reference-list">
                    <?php foreach ($niches as $niche): ?>
                        <details class="cs-reference-item">
                            <summary>
                                <span><strong><?= htmlspecialchars($niche['name']) ?></strong></span>
                                <em><?= htmlspecialchars($statusOptions[$niche['status']] ?? (string)$niche['status']) ?></em>
                            </summary>
                            <form method="post" action="/app/actions/content_studio/reference_save.php" class="cs-form cs-edit-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="niche">
                                <input type="hidden" name="id" value="<?= (int)$niche['id'] ?>">
                                <label>Nome<input class="c-input" name="name" value="<?= htmlspecialchars($niche['name']) ?>" required></label>
                                <label>Descrição<textarea class="c-input" name="description" rows="3"><?= htmlspecialchars((string)($niche['description'] ?? '')) ?></textarea></label>
                                <label>Status
                                    <select class="c-input" name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= (string)$niche['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="cs-actions">
                                    <button class="c-btn-primary">Salvar</button>
                                </div>
                            </form>
                            <form method="post" action="/app/actions/content_studio/reference_delete.php" class="cs-delete-form" onsubmit="return confirm('Remover este nicho? Se estiver em uso, ele sera apenas desativado.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="niche">
                                <input type="hidden" name="id" value="<?= (int)$niche['id'] ?>">
                                <button class="c-btn-danger">Apagar</button>
                            </form>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="c-card">
                <h3>Personagens</h3>
                <form method="post" action="/app/actions/content_studio/reference_save.php" class="cs-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="persona">
                    <label>Nome<input class="c-input" name="name" placeholder="Narrador, especialista, marca..." required></label>
                    <label>Descrição<textarea class="c-input" name="description" rows="3"></textarea></label>
                    <label>Tom de voz<textarea class="c-input" name="voice_notes" rows="3"></textarea></label>
                    <button class="c-btn-primary">Adicionar personagem</button>
                </form>
                <div class="cs-reference-list">
                    <?php foreach ($personas as $persona): ?>
                        <details class="cs-reference-item">
                            <summary>
                                <span><strong><?= htmlspecialchars($persona['name']) ?></strong></span>
                                <em><?= htmlspecialchars($statusOptions[$persona['status']] ?? (string)$persona['status']) ?></em>
                            </summary>
                            <form method="post" action="/app/actions/content_studio/reference_save.php" class="cs-form cs-edit-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="persona">
                                <input type="hidden" name="id" value="<?= (int)$persona['id'] ?>">
                                <label>Nome<input class="c-input" name="name" value="<?= htmlspecialchars($persona['name']) ?>" required></label>
                                <label>Descrição<textarea class="c-input" name="description" rows="3"><?= htmlspecialchars((string)($persona['description'] ?? '')) ?></textarea></label>
                                <label>Tom de voz<textarea class="c-input" name="voice_notes" rows="3"><?= htmlspecialchars((string)($persona['voice_notes'] ?? '')) ?></textarea></label>
                                <label>Status
                                    <select class="c-input" name="status">
                                        <?php foreach ($statusOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= (string)$persona['status'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="cs-actions">
                                    <button class="c-btn-primary">Salvar</button>
                                </div>
                            </form>
                            <form method="post" action="/app/actions/content_studio/reference_delete.php" class="cs-delete-form" onsubmit="return confirm('Remover este personagem? Se estiver em uso, ele sera apenas desativado.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="type" value="persona">
                                <input type="hidden" name="id" value="<?= (int)$persona['id'] ?>">
                                <button class="c-btn-danger">Apagar</button>
                            </form>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$content .= '<style>.cs-tabs{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.cs-tabs .is-active{border-color:#3b82f6;color:#fff}.cs-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.cs-form{display:grid;gap:10px}.cs-form label{display:grid;gap:5px;color:#9ca3af;font-size:12px}.cs-reference-list{display:grid;gap:8px;margin-top:12px}.cs-reference-item{border:1px solid #334155;background:#111827;border-radius:6px;padding:0}.cs-reference-item summary{cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;list-style:none}.cs-reference-item summary::-webkit-details-marker{display:none}.cs-reference-item summary strong{display:block}.cs-reference-item summary small{color:#38bdf8}.cs-reference-item summary em{font-style:normal;color:#9ca3af;font-size:11px;border:1px solid #334155;border-radius:4px;padding:2px 6px}.cs-edit-form{border-top:1px solid #273449;padding:10px}.cs-delete-form{display:flex;justify-content:flex-end;padding:0 10px 10px}.cs-actions{display:flex;justify-content:flex-end}.c-btn-danger{border:1px solid #7f1d1d;background:#451a1a;color:#fecaca;border-radius:6px;padding:8px 12px;font-weight:700}.c-btn-danger:hover{background:#7f1d1d}@media(max-width:900px){.cs-grid-3{grid-template-columns:1fr}.cs-tabs{justify-content:flex-start}}</style>';
require APP_PATH . '/views/layout_admin.php';
