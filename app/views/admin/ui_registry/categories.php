<?php foreach ($components as $category => $items): ?>

<div class="c-card">

    <h3><?= ucfirst($category) ?></h3>

    <div class="c-grid">

        <?php foreach ($items as $item): ?>

            <?php require __DIR__ . '/partials/component_card.php'; ?>

        <?php endforeach; ?>

    </div>

</div>

<?php endforeach; ?>