<?php
declare(strict_types=1);

/* =========================
CSS CORE (ESTRUTURA APENAS)
========================= */

$extraCss = '
<link rel="stylesheet" href="/public/assets/css/core_page.css">
';

/* =========================
CONTENT
========================= */

ob_start();
?>

<?php if (!empty($previewMode)): ?>
    <div class="c-preview-banner">
        Modo Preview — Página não publicada
    </div>
<?php endif; ?>

<main class="c-page">

    <div class="c-container">

        <?= $content ?>

    </div>

</main>

<?php
$content = ob_get_clean();

/* =========================
RENDER BASE
========================= */

require APP_PATH . '/views/layout_base.php';