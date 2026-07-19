<div class="c-page-header">
    <div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p>Configure o conteudo da landing page e publique quando estiver pronta.</p>
    </div>
    <div class="c-page-actions">
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/divulgacao/index.php">Voltar</a>
    </div>
</div>

<?php flash_show(); ?>

<form method="post" action="<?= htmlspecialchars($action) ?>" class="c-card divulgacao-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="c-form-grid">
        <label>
            Titulo
            <input type="text" name="title" value="<?= htmlspecialchars((string)$page['title']) ?>" required>
        </label>
        <label>
            Slug
            <input type="text" name="slug" value="<?= htmlspecialchars((string)$page['slug']) ?>" placeholder="gerado automaticamente se vazio">
        </label>
        <label>
            Modelo
            <select name="template_key">
                <?php foreach ($templates as $key => $item): ?>
                    <option value="<?= htmlspecialchars($key) ?>" <?= (string)$page['template_key'] === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$item['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Tema
            <select name="theme">
                <option value="dark" <?= (string)$page['theme'] === 'dark' ? 'selected' : '' ?>>Escuro</option>
                <option value="clean" <?= (string)$page['theme'] === 'clean' ? 'selected' : '' ?>>Claro simples</option>
                <option value="contrast" <?= (string)$page['theme'] === 'contrast' ? 'selected' : '' ?>>Contraste</option>
            </select>
        </label>
        <label>
            Idioma do formulario
            <select name="form_language">
                <?php foreach (divulgacaoFormLanguages() as $languageKey => $languageLabel): ?>
                    <option value="<?= htmlspecialchars($languageKey) ?>" <?= divulgacaoFormLanguage((string)($page['form_language'] ?? 'pt')) === $languageKey ? 'selected' : '' ?>>
                        <?= htmlspecialchars($languageLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Status
            <select name="status">
                <option value="draft" <?= (string)$page['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                <option value="published" <?= (string)$page['status'] === 'published' ? 'selected' : '' ?>>Publicado</option>
            </select>
        </label>
        <label>
            WhatsApp
            <input type="text" name="whatsapp" value="<?= htmlspecialchars((string)($page['whatsapp'] ?? '')) ?>" placeholder="5599999999999">
        </label>
    </div>

    <label>
        Headline
        <input type="text" name="headline" value="<?= htmlspecialchars((string)$page['headline']) ?>" required>
    </label>
    <label>
        Subtitulo
        <textarea name="subtitle" rows="3"><?= htmlspecialchars((string)($page['subtitle'] ?? '')) ?></textarea>
    </label>
    <label>
        Conteudo
        <textarea name="body" rows="6"><?= htmlspecialchars((string)($page['body'] ?? '')) ?></textarea>
    </label>
    <div class="divulgacao-image-field">
        <div class="c-form-grid">
            <label>
                Imagem principal
                <input type="file" name="offer_image" accept="image/jpeg,image/png,image/webp">
            </label>
            <label>
                Imagem complementar
                <input type="file" name="offer_image_2" accept="image/jpeg,image/png,image/webp">
            </label>
        </div>
        <div class="divulgacao-image-preview-grid">
            <?php if (!empty($page['offer_image'])): ?>
                <div class="divulgacao-image-preview">
                    <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim((string)$page['offer_image'], '/')) ?>" alt="">
                    <label class="divulgacao-remove-image">
                        <input type="checkbox" name="remove_offer_image" value="1">
                        Remover principal
                    </label>
                </div>
            <?php endif; ?>
            <?php if (!empty($page['offer_image_2'])): ?>
                <div class="divulgacao-image-preview">
                    <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars(ltrim((string)$page['offer_image_2'], '/')) ?>" alt="">
                    <label class="divulgacao-remove-image">
                        <input type="checkbox" name="remove_offer_image_2" value="1">
                        Remover complementar
                    </label>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <label>
        Texto do botao
        <input type="text" name="cta_text" value="<?= htmlspecialchars((string)$page['cta_text']) ?>">
    </label>

    <div class="c-card divulgacao-flow">
        <h3>Depois de captar o lead</h3>
        <p>Use para pre-venda, afiliado, checkout externo ou atendimento direto.</p>
        <div class="c-form-grid">
            <label>
                Acao
                <select name="action_type">
                    <?php foreach (divulgacaoActionOptions() as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= (string)($page['action_type'] ?? 'capture') === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Link externo
                <input type="url" name="destination_url" value="<?= htmlspecialchars((string)($page['destination_url'] ?? '')) ?>" placeholder="https://...">
            </label>
            <label>
                Mensagem de sucesso
                <?php $formTexts = divulgacaoFormTexts((string)($page['form_language'] ?? 'pt')); ?>
                <input type="text" name="success_message" value="<?= htmlspecialchars((string)($page['success_message'] ?? $formTexts['success'])) ?>">
            </label>
        </div>
    </div>

    <button class="c-btn-primary" type="submit">Salvar pagina</button>
</form>

<style>
.divulgacao-form { display: grid; gap: 14px; }
.divulgacao-form label { display: grid; gap: 6px; }
.divulgacao-flow { display:grid; gap:10px; }
.divulgacao-flow h3 { margin:0; }
.divulgacao-flow p { margin:0; color:var(--muted); }
.divulgacao-image-field { display:grid; gap:10px; }
.divulgacao-image-preview-grid { display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.divulgacao-image-preview { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.divulgacao-image-preview img { width:150px; aspect-ratio:16/10; object-fit:cover; border:1px solid var(--border); border-radius:8px; }
.divulgacao-remove-image { display:flex !important; grid-template-columns:auto 1fr !important; align-items:center; color:var(--muted); }
.c-form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 760px) { .c-form-grid { grid-template-columns: 1fr; } }
</style>
