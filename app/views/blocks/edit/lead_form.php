<?php require_once APP_PATH . '/helpers/form_renderer.php'; ?>

<div class="c-page">

    <div class="c-page-header">
        <h1>Formulário</h1>
    </div>

    <div class="c-card">

        <form method="post" action="/app/actions/pages/block_update.php">

            <input type="hidden" name="page_id" value="<?= $pageId ?>">
            <input type="hidden" name="index" value="<?= $index ?>">
            <input type="hidden" name="type" value="lead_form">

            <?php
            $schema = require APP_PATH . '/config/blocks.php';
            $fields = $schema['lead_form']['fields'];

            $baseOptions = ['' => 'Nenhuma'];

            $stmt = $pdo->query("
                SELECT id, name, slug
                FROM bases
                WHERE status = 1
                AND slug != 'base'
                AND base_stage = 'published'
                ORDER BY name ASC
            ");

            $selectedBaseId = (string)($block['base_id'] ?? '');
            $selectedBaseSlug = (string)($block['base_slug'] ?? '');

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $base) {
                $baseOptions[(string)$base['id']] = $base['name'];

                if ($selectedBaseId === '' && $selectedBaseSlug !== '' && $selectedBaseSlug === (string)$base['slug']) {
                    $selectedBaseId = (string)$base['id'];
                }
            }

            $fields['base_id']['options'] = $baseOptions;

            foreach ($fields as $name => $config) {
                $value = $name === 'base_id'
                    ? $selectedBaseId
                    : ($block[$name] ?? null);
                renderField($name, $config, $value);
            }
            ?>

            <br>

            <button class="c-btn-primary">
                Salvar
            </button>

        </form>

    </div>

</div>
