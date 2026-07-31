<?php

$webPath = dirname(APP_PATH) . '/web';
$corePageCssVersion = @filemtime($webPath . '/assets/css/core_page.css') ?: time();
$blogCssVersion = @filemtime($webPath . '/assets/css/public/blog.css') ?: time();
$publicJsVersion = @filemtime($webPath . '/assets/js/public.js') ?: time();

$extraCss = '
<link rel="stylesheet" href="/web/assets/css/core_page.css?v=' . $corePageCssVersion . '">
<link rel="stylesheet" href="/web/assets/css/public/blog.css?v=' . $blogCssVersion . '">
';

$extraJs = '<script src="/web/assets/js/public.js?v=' . $publicJsVersion . '"></script>';

$currentSlug = (string)($page['slug'] ?? '');
$relatedPosts = [];

try {
    $relatedStmt = $pdo->prepare("
        SELECT title, slug, category, sub_category
        FROM core_page_contents
        WHERE type = 'blog'
          AND status = 'published'
          AND area = 'public'
          AND slug <> :slug
        ORDER BY
          CASE WHEN category = :category THEN 0 ELSE 1 END,
          created_at DESC,
          id DESC
        LIMIT 3
    ");
    $relatedStmt->execute([
        'slug' => $currentSlug,
        'category' => (string)($page['category'] ?? ''),
    ]);
    $relatedPosts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $relatedPosts = [];
}

ob_start();
?>

<main class="c-site">

    <?php require APP_PATH . '/views/partials/header_blog.php'; ?>

    <?php if (!empty($previewMode)): ?>
        <div class="c-preview-banner">
            Modo Preview — Blog não publicado
        </div>
    <?php endif; ?>

    <div class="c-blog-layout">

        <aside class="c-blog-sidebar c-blog-sidebar--left">
            <?php require APP_PATH . '/views/partials/sidebar_left_blog.php'; ?>
        </aside>

        <article class="c-blog">

            <?= $content ?>

            <?php if ($relatedPosts): ?>
                <section class="c-blog-related" aria-label="Outros artigos">
                    <div class="c-blog-related-head">
                        <span>Continue lendo</span>
                        <h2>Outros artigos</h2>
                    </div>

                    <div class="c-blog-related-grid">
                        <?php foreach ($relatedPosts as $post): ?>
                            <a class="c-blog-related-card" href="/web/p.php?slug=<?= urlencode((string)$post['slug']) ?>">
                                <small>
                                    <?= htmlspecialchars(trim((string)($post['category'] ?? '')) ?: 'Blog') ?>
                                    <?php if (!empty($post['sub_category'])): ?>
                                        / <?= htmlspecialchars((string)$post['sub_category']) ?>
                                    <?php endif; ?>
                                </small>
                                <strong><?= htmlspecialchars((string)$post['title']) ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </article>

        <aside class="c-blog-sidebar c-blog-sidebar--right">
            <?php require APP_PATH . '/views/partials/sidebar_right_blog.php'; ?>
        </aside>

    </div>

</main>

<?php
$content = ob_get_clean();

require APP_PATH . '/views/layout_base.php';
