<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

/* =========================
ID
========================= */

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    exit('Página invalida.');
}

/* =========================
BUSCAR
========================= */

$stmt = $pdo->prepare("
SELECT *
FROM core_page_contents
WHERE id=:id
AND type IN ('page','blog')
");

$stmt->execute(['id'=>$id]);
$pageData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pageData) {
    exit('Página não encontrada.');
}

/* =========================
BASE URL
========================= */

$baseUrl = '';

/* =========================
BLOCKS (SCHEMA)
========================= */

$blocks = require APP_PATH . '/config/blocks.php';

/* =========================
JSON
========================= */

$blocksPage = [];

$jsonPath = STORAGE_PATH . '/paginas/pages/' . $pageData['content_path'];

if (file_exists($jsonPath)) {

    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        $blocksPage = $data['blocks'] ?? [];
        $blocksPage = array_values($blocksPage); // ?? CRiTICO
    }
}

/* =========================
TIPO
========================= */

$type = $pageData['type'] ?? 'page';

/* ?? CORRETO */
$allowedBlocks = getAllowedBlocks($type);

/* =========================
AGRUPAR
========================= */

$groupedBlocks = [];

foreach ($blocks as $typeBlock => $block) {

    if (!in_array($typeBlock, $allowedBlocks)) continue;

    $category = $block['category'] ?? 'other';

    $groupedBlocks[$category][$typeBlock] = $block;
}

ob_start();
?>

<div class="c-page">

    <!-- HEADER -->
    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Editar Página</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($pageData['title']) ?></p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary"
               href="<?= $baseUrl ?>/public/admin/pages/index.php">
                + Voltar
            </a>

            <a class="c-btn-secondary"
               href="<?= $baseUrl ?>/public/p.php?slug=<?= urlencode($pageData['slug']) ?>"
               target="_blank">
                Ver página
            </a>
        </div>

    </div>

    <!-- CONTENT -->
    <div class="c-page-content">

        <div class="c-page-builder-grid">

            <!-- BLOCOS -->
            <div class="c-card">

                <h3>Blocos da Página</h3>

                <?php if (!$blocksPage): ?>

                    <p>Nenhum bloco adicionado.</p>

                <?php else: ?>

                    <ul class="builder-list" id="blocksSortable">

<?php foreach ($blocksPage as $i => $block): ?>

<li class="builder-item block-item" data-index="<?= $i ?>">

    <div class="builder-left">

        <span class="builder-handle">⋮⋮</span>

        <div class="builder-info">
            <span class="builder-type">
                <?= htmlspecialchars(str_replace('_',' ', $block['type'])) ?>
            </span>
        </div>

    </div>

    <div class="builder-actions">

        <a class="btn-edit"
           href="<?= $baseUrl ?>/public/admin/pages/block_edit.php?page_id=<?= $pageData['id'] ?>&index=<?= $i ?>">
            Editar
        </a>

        <button class="btn-delete"
                onclick="deleteBlock(<?= $pageData['id'] ?>, <?= $i ?>)">
            Remover
        </button>

    </div>

</li>

<?php endforeach ?>

</ul>

                <?php endif ?>

            </div>

            <!-- PREVIEW -->
            <div class="c-card">

                <h3>Preview</h3>

                <div style="margin-bottom:10px;display:flex;gap:10px;">
                    <button class="c-btn-secondary" onclick="setPreview('desktop')">Desktop</button>
                    <button class="c-btn-secondary" onclick="setPreview('tablet')">Tablet</button>
                    <button class="c-btn-secondary" onclick="setPreview('mobile')">Mobile</button>
                </div>

                <div class="c-preview-wrapper" id="previewWrapper">

                    <div class="c-preview-topbar">
                        <div class="c-preview-dot red"></div>
                        <div class="c-preview-dot yellow"></div>
                        <div class="c-preview-dot green"></div>
                    </div>

                    <iframe
                        src="<?= $baseUrl ?>/public/p.php?slug=<?= urlencode($pageData['slug']) ?>&preview=1"
                        class="c-preview-frame"
                        loading="lazy">
                    </iframe>

                </div>

            </div>

        </div>

    </div>

</div>

<script>
function deleteBlock(pageId, index){
    if(!confirm('Remover bloco?')) return;

    fetch('/app/actions/pages/block_delete.php?page_id='+pageId+'&index='+index)
    .then(()=>location.reload())
    .catch(()=>alert('Erro'));
}

function setPreview(type){
    const wrapper = document.getElementById('previewWrapper');

    wrapper.classList.remove('mobile','tablet');

    if(type === 'mobile') wrapper.classList.add('mobile');
    if(type === 'tablet') wrapper.classList.add('tablet');
}
</script>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
const el = document.getElementById('blocksSortable');

Sortable.create(el, {
    handle: '.block-handle',
    animation: 150,

    onEnd: function () {

        const order = [];

        document.querySelectorAll('.block-item').forEach(item => {
            order.push(parseInt(item.dataset.index));
        });

        fetch('/app/actions/pages/block_reorder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                page_id: <?= $pageData['id'] ?>,
                order: order
            })
        })
        .then(() => location.reload())
        .catch(() => alert('Erro ao reordenar'));
    }
});
</script>

<?php
$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '<div class="c-card"><h3>Adicionar Bloco</h3>';

foreach ($groupedBlocks as $category => $items) {

    $rightSidebarContent .= '<div style="margin-bottom:15px">';
    $rightSidebarContent .= '<strong>'.htmlspecialchars($category).'</strong><br><br>';

    foreach ($items as $typeBlock => $block) {

        $rightSidebarContent .= '
        <a class="c-btn-secondary"
        style="display:block;margin-bottom:5px"
        href="/app/actions/pages/block_add.php?page_id='.$pageData['id'].'&type='.$typeBlock.'">
            '.htmlspecialchars($block['label']).'
        </a>';
    }

    $rightSidebarContent .= '</div>';
}

$rightSidebarContent .= '</div>';

require APP_PATH . '/views/layout_admin.php';