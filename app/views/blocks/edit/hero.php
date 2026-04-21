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

        <button class="c-btn-primary">Salvar</button>

    </form>

</div>