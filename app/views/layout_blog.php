<?php

$extraCss = '
<link rel="stylesheet" href="/public/assets/css/core_page.css">
<link rel="stylesheet" href="/public/assets/css/public/blog.css">
';

ob_start();
?>

<main class="c-site">

    <?php if (!empty($previewMode)): ?>
        <div class="c-preview-banner">
            Modo Preview — Blog não publicado
        </div>
    <?php endif; ?>

    <article class="c-blog">

        <?= $content ?>

    </article>

</main>

<?php
$content = ob_get_clean();

require APP_PATH . '/views/layout_base.php';