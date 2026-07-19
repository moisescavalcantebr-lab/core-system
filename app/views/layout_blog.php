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

        </article>

        <aside class="c-blog-sidebar c-blog-sidebar--right">
            <?php require APP_PATH . '/views/partials/sidebar_right_blog.php'; ?>
        </aside>

    </div>

</main>

<?php
$content = ob_get_clean();

require APP_PATH . '/views/layout_base.php';
