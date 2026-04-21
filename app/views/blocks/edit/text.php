<?php
?>

<div class="c-page">

    <div class="c-page-header">
        <h1>Texto</h1>
    </div>

    <div class="c-card">

        <form method="post" action="/app/actions/pages/block_update.php">

            <input type="hidden" name="page_id" value="<?= $pageId ?>">
            <input type="hidden" name="index" value="<?= $index ?>">
            <input type="hidden" name="type" value="text">

            <!-- TÍTULO -->
            <div class="c-form-group">
                <label>Título</label>
                <input class="c-input"
                       name="title"
                       value="<?= htmlspecialchars($block['title'] ?? '') ?>">
            </div>

            <!-- CONTEÚDO -->
            <div class="c-form-group">
                <label>Conteúdo</label>
                <textarea class="c-input"
                          name="content"
                          rows="6"><?= htmlspecialchars($block['content'] ?? '') ?></textarea>
            </div>

            <!-- ALIGN -->
            <div class="c-form-group">
                <label>Alinhamento</label>

                <select class="c-input" name="align">

                    <?php
                    $align = $block['align'] ?? 'left';
                    ?>

                    <option value="left" <?= $align === 'left' ? 'selected' : '' ?>>Esquerda</option>
                    <option value="center" <?= $align === 'center' ? 'selected' : '' ?>>Centro</option>
                    <option value="right" <?= $align === 'right' ? 'selected' : '' ?>>Direita</option>

                </select>
            </div>

            <br>

            <button class="c-btn-primary">
                Salvar
            </button>

        </form>

    </div>

</div>