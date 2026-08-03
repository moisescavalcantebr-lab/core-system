<?php

$title = $config['title'] ?? '';
$items = [];

$isRenderableImage = static function (string $src): bool {
    $src = trim($src);

    if ($src === '') {
        return false;
    }

    if (preg_match('#^https?://#i', $src)) {
        return true;
    }

    if ($src[0] !== '/') {
        return true;
    }

    $publicPath = PUBLIC_PATH . $src;
    $storagePath = ROOT_PATH . $src;

    return is_file($publicPath) || is_file($storagePath);
};

for ($i = 1; $i <= 3; $i++) {
    $image = trim((string)($config['image_' . $i] ?? ''));
    $name = trim((string)($config['title_' . $i] ?? ''));
    $description = trim((string)($config['description_' . $i] ?? ''));

    if ($image !== '' || $name !== '' || $description !== '') {
        $items[] = [
            'name' => $name,
            'description' => $description,
            'image' => $image,
        ];
    }
}

if (empty($items) && !empty($config['items']) && is_array($config['items'])) {
    $items = array_slice($config['items'], 0, 3);
}

if (empty($items)) {
    return;
}

?>

<section class="block block-catalog">

    <div class="c-container">

        <?php if ($title): ?>
            <h2 class="block-title text-center">
                <?= htmlspecialchars($title) ?>
            </h2>
        <?php endif; ?>

        <div class="catalog-grid">

            <?php foreach ($items as $item):

                $name        = $item['name'] ?? '';
                $description = $item['description'] ?? '';

                $image = trim((string)($item['image'] ?? ''));
                $hasImage = $image !== '' && $isRenderableImage($image);

            ?>

                <div class="catalog-card">

                    <?php if ($hasImage): ?>
                        <div class="catalog-image">
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($name) ?>">
                        </div>
                    <?php endif; ?>

                    <h3 class="catalog-title">
                        <?= htmlspecialchars($name) ?>
                    </h3>

                    <?php if ($description): ?>
                        <p class="catalog-description">
                            <?= htmlspecialchars($description) ?>
                        </p>
                    <?php endif; ?>

                </div>

            <?php endforeach ?>

        </div>

    </div>

</section>
