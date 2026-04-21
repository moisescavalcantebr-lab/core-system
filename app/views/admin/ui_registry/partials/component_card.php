<div class="c-card">

    <div class="preview-box">

        <style>
        <?php include $item['full_path'] . '/style.css'; ?>
        </style>

        <?php include $item['full_path'] . '/code.php'; ?>

    </div>

    <strong><?= $item['name'] ?></strong>

    <a href="/public/admin/ui_registry?component=<?= $item['path'] ?>">
        Detalhes
    </a>

</div>