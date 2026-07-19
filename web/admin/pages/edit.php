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

$blogCategories = [];
$blogSubCategoriesByCategory = [];

if ($type === 'blog') {
    $blogCategories = $pdo->query("
        SELECT DISTINCT category
        FROM core_page_contents
        WHERE area='public'
          AND type='blog'
          AND category IS NOT NULL
          AND category != ''
        ORDER BY category
    ")->fetchAll(PDO::FETCH_COLUMN);

    $currentCategory = trim((string)($pageData['category'] ?? ''));
    if ($currentCategory !== '' && !in_array($currentCategory, $blogCategories, true)) {
        $blogCategories[] = $currentCategory;
    }

    $stmt = $pdo->query("
        SELECT DISTINCT category, sub_category
        FROM core_page_contents
        WHERE area='public'
          AND type='blog'
          AND category IS NOT NULL
          AND category != ''
          AND sub_category IS NOT NULL
          AND sub_category != ''
        ORDER BY category, sub_category
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cat = trim((string)($row['category'] ?? ''));
        $sub = trim((string)($row['sub_category'] ?? ''));

        if ($cat === '' || $sub === '') {
            continue;
        }

        $blogSubCategoriesByCategory[$cat][] = $sub;
    }

    $currentSubCategory = trim((string)($pageData['sub_category'] ?? ''));
    if ($currentCategory !== '' && $currentSubCategory !== '') {
        $blogSubCategoriesByCategory[$currentCategory] ??= [];

        if (!in_array($currentSubCategory, $blogSubCategoriesByCategory[$currentCategory], true)) {
            $blogSubCategoriesByCategory[$currentCategory][] = $currentSubCategory;
        }
    }
}

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
               href="<?= $baseUrl ?>/web/admin/pages/index.php">
                Voltar
            </a>

            <a class="c-btn-secondary"
               href="<?= $baseUrl ?>/web/p.php?slug=<?= urlencode($pageData['slug']) ?>"
               target="_blank">
                Ver página
            </a>

            <button class="c-btn-secondary"
                    type="button"
                    onclick="toggleStatus(<?= (int)$pageData['id'] ?>)">
                <?= $pageData['status'] === 'published' ? 'Despublicar' : 'Publicar' ?>
            </button>

            <?php if ($pageData['status'] !== 'published'): ?>
                <button class="c-btn-secondary"
                        type="button"
                        onclick="deletePage(<?= (int)$pageData['id'] ?>)">
                    Excluir
                </button>
            <?php endif; ?>
        </div>

    </div>

    <!-- CONTENT -->
    <div class="c-page-content">

        <?php if ($type === 'blog'): ?>
            <div class="c-card c-blog-taxonomy-config">
                <h3>Categoria do blog</h3>
                <p class="c-text-muted">Use apenas categorias e subcategorias ja existentes em outros posts.</p>

                <?php if (!$blogCategories): ?>
                    <p>Nenhuma categoria disponivel.</p>
                <?php else: ?>
                    <form method="post" action="/app/actions/pages/taxonomy_update.php" class="c-blog-taxonomy-form">
                        <input type="hidden" name="id" value="<?= (int)$pageData['id'] ?>">

                        <div class="c-form-group">
                            <label>Categoria</label>
                            <select class="c-input" name="category" id="blogCategorySelect" required>
                                <?php foreach ($blogCategories as $category): ?>
                                    <option value="<?= htmlspecialchars((string)$category) ?>" <?= (string)$pageData['category'] === (string)$category ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="c-form-group">
                            <label>Subcategoria</label>
                            <select class="c-input" name="sub_category" id="blogSubCategorySelect">
                                <option value="">Sem subcategoria</option>
                            </select>
                        </div>

                        <button class="c-btn-secondary" type="submit">Salvar categoria</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="c-card c-page-studio-config">
                <h3>Content Studio</h3>
                <p class="c-text-muted">Use esta página como destino de uma campanha e gere links rastreáveis pelo Studio.</p>
                <a class="c-btn-secondary" href="/web/admin/content_studio/campaigns.php?page_id=<?= (int)$pageData['id'] ?>">
                    Criar campanha com esta página
                </a>
            </div>
        <?php endif; ?>

        <div class="c-page-builder-grid">

            <!-- BLOCOS -->
            <div class="c-card">

                <h3>Blocos da Página</h3>

                <div class="c-page-block-add">
                    <strong>Adicionar bloco</strong>

                    <?php foreach ($groupedBlocks as $category => $items): ?>
                        <div class="c-page-block-group">
                            <span><?= htmlspecialchars($category) ?></span>
                            <div class="c-page-block-buttons">
                                <?php foreach ($items as $typeBlock => $block): ?>
                                    <a class="c-btn-secondary btn-sm"
                                       href="/app/actions/pages/block_add.php?page_id=<?= (int)$pageData['id'] ?>&type=<?= urlencode((string)$typeBlock) ?>">
                                        <?= htmlspecialchars((string)$block['label']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

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
           href="<?= $baseUrl ?>/web/admin/pages/block_edit.php?page_id=<?= $pageData['id'] ?>&index=<?= $i ?>">
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
                        src="<?= $baseUrl ?>/web/p.php?slug=<?= urlencode($pageData['slug']) ?>&preview=1"
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

function toggleStatus(id){
    fetch('/app/actions/pages/toggle_status.php?id=' + id)
    .then(() => location.reload())
    .catch(() => alert('Erro'));
}

function deletePage(id){
    if(!confirm('Excluir página?')) return;
    fetch('/app/actions/pages/delete.php?id=' + id)
    .then(() => window.location.href = '/web/admin/pages/index.php')
    .catch(() => alert('Erro'));
}

const blogSubCategories = <?= json_encode($blogSubCategoriesByCategory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const currentBlogSubCategory = <?= json_encode((string)($pageData['sub_category'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const blogCategorySelect = document.getElementById('blogCategorySelect');
const blogSubCategorySelect = document.getElementById('blogSubCategorySelect');

function refreshBlogSubCategories(preserveCurrent = true) {
    if (!blogCategorySelect || !blogSubCategorySelect) return;

    const category = blogCategorySelect.value;
    const subs = blogSubCategories[category] || [];
    const previous = preserveCurrent ? (blogSubCategorySelect.value || currentBlogSubCategory) : '';

    blogSubCategorySelect.innerHTML = '<option value="">Sem subcategoria</option>';

    subs.forEach(function(sub) {
        const option = document.createElement('option');
        option.value = sub;
        option.textContent = sub;
        option.selected = sub === previous;
        blogSubCategorySelect.appendChild(option);
    });
}

if (blogCategorySelect && blogSubCategorySelect) {
    blogCategorySelect.addEventListener('change', function() {
        blogSubCategorySelect.value = '';
        refreshBlogSubCategories(false);
    });
    refreshBlogSubCategories();
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

if (el && window.Sortable) {
    Sortable.create(el, {
        handle: '.builder-handle',
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
}
</script>

<style>
.c-page-block-add {
    margin: 12px 0 18px;
    padding: 12px;
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-card) 70%, transparent);
}

.c-page-block-group {
    margin-top: 12px;
}

.c-page-block-group > span {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 700;
}

.c-page-block-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.builder-handle {
    cursor: grab;
}

.c-blog-taxonomy-config {
    margin-bottom: 16px;
}

.c-blog-taxonomy-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
    gap: 10px;
    align-items: end;
}

@media (max-width: 760px) {
    .c-blog-taxonomy-form {
        grid-template-columns: 1fr;
    }

    .c-page-block-buttons .btn-sm {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '
<div class="c-card">
    <h3>Configuração</h3>
    <p>Use esta tela para adicionar blocos, ordenar, editar conteúdo e controlar publicação.</p>
</div>';

require APP_PATH . '/views/layout_admin.php';
