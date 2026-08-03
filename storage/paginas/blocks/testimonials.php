<?php

$title = $config['title'] ?? '';
$items = [];

for ($i = 1; $i <= 3; $i++) {
    $name = trim((string)($config['name_' . $i] ?? ''));
    $role = trim((string)($config['role_' . $i] ?? ''));
    $text = trim((string)($config['text_' . $i] ?? ''));
    $image = trim((string)($config['image_' . $i] ?? ''));

    if ($name !== '' || $role !== '' || $text !== '' || $image !== '') {
        $items[] = [
            'name' => $name,
            'role' => $role,
            'text' => $text,
            'image' => $image,
        ];
    }
}

if (empty($items) && !empty($config['items']) && is_array($config['items'])) {
    $items = array_slice(array_values($config['items']), 0, 3);
}

if (!$title && empty($items)) return;

?>

<section class="block block-testimonials">
    <div class="c-container">

        <?php if ($title): ?>
            <h2 class="block-title text-center">
                <?= htmlspecialchars($title) ?>
            </h2>
        <?php endif; ?>

        <?php if (!empty($items)): ?>
            <div class="testimonials-grid">
                <?php foreach ($items as $item): ?>
                    <div class="testimonial-card">
                        <?php if (!empty($item['image'])): ?>
                            <img
                                class="testimonial-avatar"
                                src="<?= htmlspecialchars($item['image']) ?>"
                                alt="<?= htmlspecialchars($item['name'] ?? '') ?>">
                        <?php endif; ?>

                        <div class="testimonial-author">
                            <strong><?= htmlspecialchars($item['name'] ?? '') ?></strong>

                            <?php if (!empty($item['role'])): ?>
                                <span><?= htmlspecialchars($item['role']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($item['text'])): ?>
                            <p class="testimonial-text">
                                <?= htmlspecialchars($item['text']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif; ?>

    </div>
</section>
