<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    http_response_code(404);
    exit('Base nao encontrada.');
}

function base_showcase_asset_url(?string $path): string
{
    $path = trim((string)$path);
    return $path !== '' ? '/web/assets/uploads/' . str_replace('%2F', '/', rawurlencode($path)) : '';
}

$coverUrl = base_showcase_asset_url($base['showcase_cover_image'] ?? null);
$bannerUrl = base_showcase_asset_url($base['showcase_banner_image'] ?? null);
$isLandingVisible = base_is_published($base) && (int)($base['showcase_status'] ?? 0) === 1;

$title = 'Landing da Base';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Landing da Base</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$base['name']) ?> em /web/base.php?slug=<?= htmlspecialchars((string)$base['slug']) ?></p>
        </div>

        <div class="c-page-actions">
            <?php if ($isLandingVisible): ?>
                <a href="/web/base.php?slug=<?= urlencode((string)$base['slug']) ?>" target="_blank" class="c-btn-secondary">Ver landing</a>
            <?php endif; ?>
            <a href="/web/admin/bases/vitrines.php" class="c-btn-secondary">Vitrines</a>
            <a href="/web/admin/bases/index.php" class="c-btn-secondary">Bases</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="post" action="/app/actions/bases/vitrine_save.php" enctype="multipart/form-data" class="c-card">
            <?= csrf_field() ?>
            <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Titulo da landing</label>
                    <input class="c-input"
                           name="showcase_title"
                           maxlength="150"
                           placeholder="<?= htmlspecialchars((string)$base['name']) ?>"
                           value="<?= htmlspecialchars((string)($base['showcase_title'] ?? '')) ?>">
                </div>

                <div class="c-form-group c-form-group-full">
                    <label>Subtitulo da landing</label>
                    <textarea class="c-input"
                              name="showcase_summary"
                              rows="3"
                              placeholder="Texto curto para explicar a base na pagina publica."><?= htmlspecialchars((string)($base['showcase_summary'] ?? '')) ?></textarea>
                </div>

                <div class="c-form-group c-form-group-full">
                    <label>Destaques dos cards</label>
                    <textarea class="c-input"
                              name="showcase_features"
                              rows="4"
                              placeholder="Um destaque por linha. Ex: Gestao de campanhas simples&#10;Cadastro rapido por e-mail&#10;Painel pronto para acompanhar leads"><?= htmlspecialchars((string)($base['showcase_features'] ?? '')) ?></textarea>
                </div>

                <div class="c-form-group">
                    <label>Imagem de capa</label>
                    <input class="c-input" type="file" name="showcase_cover_image" accept="image/png,image/jpeg,image/webp">
                    <?php if ($coverUrl): ?>
                        <label class="c-checkbox-line">
                            <input type="checkbox" name="remove_cover" value="1">
                            Remover capa atual
                        </label>
                    <?php endif; ?>
                </div>

                <div class="c-form-group">
                    <label>Banner da home</label>
                    <input class="c-input" type="file" name="showcase_banner_image" accept="image/png,image/jpeg,image/webp">
                    <?php if ($bannerUrl): ?>
                        <label class="c-checkbox-line">
                            <input type="checkbox" name="remove_banner" value="1">
                            Remover banner atual
                        </label>
                    <?php endif; ?>
                </div>

                <div class="c-form-group">
                    <label>Link opcional do blog/conteudo</label>
                    <input class="c-input"
                           name="showcase_detail_url"
                           placeholder="/web/p.php?slug=meu-post ou https://..."
                           value="<?= htmlspecialchars((string)($base['showcase_detail_url'] ?? '')) ?>">
                </div>

                <div class="c-form-group">
                    <label>Texto do botao opcional</label>
                    <input class="c-input"
                           name="showcase_cta_text"
                           maxlength="80"
                           placeholder="Quero saber mais"
                           value="<?= htmlspecialchars((string)($base['showcase_cta_text'] ?? '')) ?>">
                </div>

                <label class="c-checkbox-line">
                    <input type="checkbox" name="showcase_featured" value="1" <?= (int)($base['showcase_featured'] ?? 0) === 1 ? 'checked' : '' ?>>
                    Destacar na home
                </label>

                <label class="c-checkbox-line">
                    <input type="checkbox" name="showcase_status" value="1" <?= (int)($base['showcase_status'] ?? 1) === 1 ? 'checked' : '' ?>>
                    Exibir na vitrine pública
                </label>
            </div>

            <div class="c-showcase-preview-grid">
                <div>
                    <h3>Capa atual</h3>
                    <div class="c-showcase-preview">
                        <?php if ($coverUrl): ?>
                            <img src="<?= htmlspecialchars($coverUrl) ?>" alt="Capa da base">
                        <?php else: ?>
                            <span>Sem capa cadastrada</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h3>Banner atual</h3>
                    <div class="c-showcase-preview">
                        <?php if ($bannerUrl): ?>
                            <img src="<?= htmlspecialchars($bannerUrl) ?>" alt="Banner da base">
                        <?php else: ?>
                            <span>Sem banner cadastrado</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <button class="c-btn-secondary">Salvar Vitrine</button>
        </form>
    </div>
</div>

<style>
.c-form-group-full {
    grid-column: 1 / -1;
}

.c-checkbox-line {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
    font-weight: 700;
}

.c-showcase-preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin: 20px 0;
}

.c-showcase-preview-grid h3 {
    margin: 0 0 8px;
    font-size: 1rem;
}

.c-showcase-preview {
    min-height: 170px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    background: var(--bg-input);
    display: grid;
    place-items: center;
    overflow: hidden;
    color: var(--text-secondary);
}

.c-showcase-preview img {
    width: 100%;
    height: 100%;
    min-height: 170px;
    object-fit: cover;
}

@media (max-width: 760px) {
    .c-showcase-preview-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
