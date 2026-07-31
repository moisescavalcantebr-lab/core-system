<div class="builder-editor">

    <h3>Hero</h3>

    <form method="post" action="/app/actions/pages/block_update.php">

        <input type="hidden" name="page_id" value="<?= $pageId ?>">
        <input type="hidden" name="index" value="<?= $index ?>">
        <input type="hidden" name="type" value="hero">

        <div class="c-form-group">
            <label>Título</label>
            <input class="c-input" name="title"
                value="<?= htmlspecialchars($block['title'] ?? '') ?>">
        </div>

        <div class="c-form-group">
            <label>Subtítulo</label>
            <textarea class="c-input" name="subtitle"><?= htmlspecialchars($block['subtitle'] ?? '') ?></textarea>
        </div>

        <div class="c-form-group">
            <label>Texto do botão</label>
            <input class="c-input" name="cta_text"
                value="<?= htmlspecialchars($block['cta_text'] ?? '') ?>">
        </div>

        <div class="c-form-group">
            <label class="c-checkbox-line">
                <input type="hidden" name="cta_enabled" value="0">
                <input type="checkbox" name="cta_enabled" value="1" <?= (string)($block['cta_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                Exibir botão
            </label>
        </div>

        <div class="c-form-group">
            <label>URL do botão</label>
            <input class="c-input" name="cta_url"
                placeholder="/web/blog.php ou /web/p.php?slug=..."
                value="<?= htmlspecialchars($block['cta_url'] ?? '') ?>">
        </div>

        <div class="c-form-group">
            <label>Abrir botão</label>
            <select class="c-input" name="cta_target">
                <?php $ctaTarget = (string)($block['cta_target'] ?? '_self'); ?>
                <option value="_self" <?= $ctaTarget === '_self' ? 'selected' : '' ?>>Mesma aba</option>
                <option value="_blank" <?= $ctaTarget === '_blank' ? 'selected' : '' ?>>Nova aba</option>
            </select>
        </div>

        <button class="c-btn-primary">Salvar</button>

    </form>

</div>
