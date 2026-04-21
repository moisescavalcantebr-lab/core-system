<?php require APP_PATH . '/helpers/form_renderer.php'; ?>

<div class="c-page">

    <div class="c-page-header">
        <h1>Benefícios</h1>
    </div>

    <div class="c-card">

        <form method="post" action="/app/actions/pages/block_update.php">

            <input type="hidden" name="page_id" value="<?= $pageId ?>">
            <input type="hidden" name="index" value="<?= $index ?>">
            <input type="hidden" name="type" value="benefits_cards">

            <?php
            $schema = require APP_PATH . '/config/blocks.php';
            $fields = $schema['benefits_cards']['fields'];

            foreach ($fields as $name => $config) {
                $value = $block[$name] ?? null;
                renderField($name, $config, $value);
            }
            ?>

            <button class="c-btn-primary">Salvar</button>

        </form>

    </div>

</div>