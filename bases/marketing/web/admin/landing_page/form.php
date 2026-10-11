<div class="c-page-header">
    <div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p>Configure o conteudo da landing page e publique quando estiver pronta.</p>
    </div>
    <div class="c-page-actions">
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/landing_page/index.php">Voltar</a>
    </div>
</div>

<?php flash_show(); ?>

<form method="post" action="<?= htmlspecialchars($action) ?>" class="c-card landing_page-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="c-form-grid">
        <label>
            Titulo
            <input class="c-input" type="text" name="title" value="<?= htmlspecialchars((string)$page['title']) ?>" required>
        </label>
        <label>
            Slug
            <input class="c-input" type="text" name="slug" value="<?= htmlspecialchars((string)$page['slug']) ?>" placeholder="gerado automaticamente se vazio">
        </label>
        <label>
            Modelo
            <select class="c-input" name="template_key">
                <?php foreach ($templates as $key => $item): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= (string)$page['template_key'] === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$item['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Tema
            <select class="c-input" name="theme">
                <option value="dark" <?= (string)$page['theme'] === 'dark' ? 'selected' : '' ?>>Escuro</option>
                <option value="clean" <?= (string)$page['theme'] === 'clean' ? 'selected' : '' ?>>Claro simples</option>
                <option value="contrast" <?= (string)$page['theme'] === 'contrast' ? 'selected' : '' ?>>Contraste</option>
            </select>
        </label>
        <label>
            Idioma do formulario
            <select class="c-input" name="form_language">
                <?php foreach (landingPageFormLanguages() as $languageKey => $languageLabel): ?>
                    <option value="<?= htmlspecialchars($languageKey) ?>" <?= landingPageFormLanguage((string)($page['form_language'] ?? 'pt')) === $languageKey ? 'selected' : '' ?>>
                        <?= htmlspecialchars($languageLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Status
            <select class="c-input" name="status">
                <option value="draft" <?= (string)$page['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                <option value="published" <?= (string)$page['status'] === 'published' ? 'selected' : '' ?>>Publicado</option>
            </select>
        </label>
        <label>
            WhatsApp
            <input class="c-input" type="text" name="whatsapp" value="<?= htmlspecialchars((string)($page['whatsapp'] ?? '')) ?>" placeholder="5599999999999">
        </label>
    </div>

    <label>
        Headline
        <input class="c-input" type="text" name="headline" value="<?= htmlspecialchars((string)$page['headline']) ?>" required>
    </label>
    <label>
        Subtitulo
        <textarea class="c-input" name="subtitle" rows="3"><?= htmlspecialchars((string)($page['subtitle'] ?? '')) ?></textarea>
    </label>
    <label>
        Conteudo
        <textarea class="c-input" name="body" rows="6"><?= htmlspecialchars((string)($page['body'] ?? '')) ?></textarea>
    </label>
    <div class="landing_page-image-field">
        <div class="c-form-grid">
            <label>
                Imagem principal
                <input class="c-input" type="file" name="offer_image" accept="image/jpeg,image/png,image/webp">
            </label>
        </div>
        <div class="landing_page-image-preview-grid">
            <?php if (!empty($page['offer_image'])): ?>
                <div class="landing_page-image-preview">
                    <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim((string)$page['offer_image'], '/')) ?>" alt="">
                    <label class="landing_page-remove-image">
                        <input type="checkbox" name="remove_offer_image" value="1">
                        Remover principal
                    </label>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <label>
        Texto do botao
        <input class="c-input" type="text" name="cta_text" value="<?= htmlspecialchars((string)$page['cta_text']) ?>">
    </label>

    <div class="landing_page-flow">
        <h3>Depois de captar o lead</h3>
        <p>Use para pre-venda, afiliado, checkout externo ou atendimento direto.</p>
        <div class="c-form-grid">
            <label>
                Acao
                <select class="c-input" name="action_type">
                    <?php foreach (landingPageActionOptions() as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= (string)($page['action_type'] ?? 'capture') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Link externo
                <input class="c-input" type="url" name="destination_url" value="<?= htmlspecialchars((string)($page['destination_url'] ?? '')) ?>" placeholder="https://...">
            </label>
            <label>
                Mensagem de sucesso
                <?php $formTexts = landingPageFormTexts((string)($page['form_language'] ?? 'pt')); ?>
                <input class="c-input" type="text" name="success_message" value="<?= htmlspecialchars((string)($page['success_message'] ?? $formTexts['success'])) ?>">
            </label>
        </div>
    </div>

    <section class="landing_page-flow">
        <h3>Divulgacao complementar</h3>
        <?php foreach (landingPageBanners($page) as $i => $banner): ?>
            <fieldset class="landing_page-banner-fields">
                <legend>Banner <?= $i + 1 ?></legend>
                <div class="c-form-grid">
                    <label>Link
                        <input class="c-input" type="url" name="banners[<?= $i ?>][url]" value="<?= htmlspecialchars($banner['url']) ?>" placeholder="https://...">
                    </label>
                    <label>Titulo
                        <input class="c-input" type="text" name="banners[<?= $i ?>][title]" value="<?= htmlspecialchars($banner['title']) ?>">
                    </label>
                    <label>Imagem
                        <input class="c-input" type="file" name="banner_image_<?= $i ?>" accept="image/jpeg,image/png,image/webp">
                    </label>
                </div>
                <?php if ($banner['image'] !== ''): ?>
                    <div class="landing_page-image-preview">
                        <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim($banner['image'], '/')) ?>" alt="<?= htmlspecialchars($banner['title']) ?>">
                        <label class="landing_page-remove-image">
                            <input type="checkbox" name="banners[<?= $i ?>][remove_image]" value="1"> Remover imagem
                        </label>
                    </div>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>
    </section>
    <button class="c-btn-primary" type="submit">Salvar pagina</button>
</form>

<style>
.landing_page-form { display: grid; gap: 14px; }
.landing_page-form label { display: grid; gap: 6px; min-width:0; }
.landing_page-form .c-input { box-sizing:border-box; min-width:0; min-height:36px; margin-bottom:0; font-family:inherit; }
.landing_page-form .c-input:focus-visible { outline:2px solid var(--color-primary, #3b82f6); outline-offset:2px; }
.landing_page-form textarea { resize:vertical; }
.landing_page-form input[type="file"] { overflow:hidden; }
.landing_page-form input[type="file"]::file-selector-button { border:1px solid var(--border-color); background:var(--bg-hover); color:var(--text-primary); padding:5px 8px; margin-right:8px; border-radius:4px; cursor:pointer; }
.landing_page-form > .c-btn-primary { justify-self:start; min-height:36px; padding:8px 14px; margin-right:0; background:var(--primary-color, var(--color-primary, #3b82f6)); border:1px solid var(--primary-color, var(--color-primary, #3b82f6)); }
.landing_page-flow { display:grid; gap:10px; padding-top:16px; border-top:1px solid var(--border-color); }
.landing_page-flow h3 { margin:0; }
.landing_page-banner-fields { min-width:0; margin:0; padding:12px 0; border:0; border-bottom:1px solid var(--border-color); }
.landing_page-banner-fields legend { padding:0; color:var(--text-secondary); }
.landing_page-banner-fields .landing_page-image-preview { margin-top:10px; }
.landing_page-flow p { margin:0; color:var(--text-secondary); }
.landing_page-image-field { display:grid; gap:10px; }
.landing_page-image-preview-grid { display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.landing_page-image-preview { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.landing_page-image-preview img { width:150px; max-width:100%; aspect-ratio:16/10; object-fit:cover; border:1px solid var(--border-color); border-radius:8px; }
.landing_page-remove-image { display:flex !important; grid-template-columns:auto 1fr !important; align-items:center; color:var(--text-secondary); }
.landing_page-form .c-form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 760px) { .landing_page-form .c-form-grid { grid-template-columns: 1fr; } .landing_page-image-preview { flex-wrap:wrap; } }
</style>
