<?php
$currentCategory = trim((string)($page['category'] ?? ''));

if ($currentCategory !== '') {
    $stmt = $pdo->prepare("
        SELECT title, slug, category, sub_category
        FROM core_page_contents
        WHERE type = 'blog'
          AND status = 'published'
          AND area = 'public'
          AND category = :category
          AND slug <> :slug
        ORDER BY created_at DESC, id DESC
        LIMIT 6
    ");
    $stmt->execute([
        'category' => $currentCategory,
        'slug' => $currentSlug,
    ]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT title, slug, category, sub_category
        FROM core_page_contents
        WHERE type = 'blog'
          AND status = 'published'
          AND area = 'public'
          AND slug <> :slug
        ORDER BY created_at DESC, id DESC
        LIMIT 6
    ");
    $stmt->execute(['slug' => $currentSlug]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (!$posts) {
    $stmt = $pdo->prepare("
        SELECT title, slug, category, sub_category
        FROM core_page_contents
        WHERE type = 'blog'
          AND status = 'published'
          AND area = 'public'
          AND slug <> :slug
        ORDER BY created_at DESC, id DESC
        LIMIT 6
    ");
    $stmt->execute(['slug' => $currentSlug]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="sidebar-block">
    <h3 class="sidebar-title">
        <?= $currentCategory !== '' ? 'Artigos de ' . htmlspecialchars($currentCategory) : 'Artigos' ?>
    </h3>

    <ul class="sidebar-list">
        <?php foreach($posts as $post): ?>
            <li>
                <a href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>" class="<?= $currentSlug === $post['slug'] ? 'active' : '' ?>">
                    <?= htmlspecialchars((string)$post['title']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
