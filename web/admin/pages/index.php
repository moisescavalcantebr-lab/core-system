<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
*/

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$filter         = $_GET['type'] ?? 'all';
$filterCategory = $_GET['category'] ?? 'all';
$filterModel    = $_GET['model'] ?? 'all';
$filterStatus   = $_GET['status'] ?? 'all';

/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$where = "WHERE area='public' AND type IN ('page','blog')";
$params = [];

if ($filter === 'page') $where .= " AND type='page'";
if ($filter === 'blog') $where .= " AND type='blog'";

if ($filterCategory !== 'all') {
    $where .= " AND category = :category";
    $params['category'] = $filterCategory;
}

if ($filterModel !== 'all') {
    $where .= " AND model_slug = :model";
    $params['model'] = $filterModel;
}

if ($filterStatus !== 'all') {
    $where .= " AND status = :status";
    $params['status'] = $filterStatus;
}

/*
|--------------------------------------------------------------------------
| DADOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT id, title, slug, type, model_slug, status, category
FROM core_page_contents
{$where}
ORDER BY id DESC
");

$stmt->execute($params);
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("
SELECT DISTINCT category 
FROM core_page_contents
WHERE category IS NOT NULL AND category != ''
ORDER BY category
")->fetchAll(PDO::FETCH_COLUMN);

$statuses = ['draft','published'];

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Páginas';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Páginas</h1>
            <p class="c-page-subtitle">Gerenciamento de conteúdo</p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary" href="/public/admin/pages/create.php">
                + Nova Página
            </a>
        </div>

    </div>

    <div class="c-page-content">

        <!-- FILTROS -->
        <div class="c-card">

            <div class="c-page-actions">

                <a class="c-btn-secondary" href="?type=all">Todos</a>
                <a class="c-btn-secondary" href="?type=page">Páginas</a>
                <a class="c-btn-secondary" href="?type=blog">Blogs</a>

               <?php foreach ($statuses as $s): ?>
                    <a class="c-btn-secondary"
                       href="?type=<?= $filter ?>&category=<?= $filterCategory ?>&status=<?= $s ?>">
                        <?= ucfirst($s) ?>
                    </a>
                <?php endforeach; ?>

            </div>

            <form method="get" style="margin-top:10px;">

                <input type="hidden" name="type" value="<?= $filter ?>">
                <input type="hidden" name="status" value="<?= $filterStatus ?>">

                <select name="category" class="c-input" onchange="this.form.submit()">
                    <option value="all">Categorias</option>

                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= $filterCategory === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>

                </select>

            </form>

        </div>

        <!-- TABELA -->
        <?php if (!$pages): ?>

            <div class="c-card">
                Nenhuma página encontrada.
            </div>

        <?php else: ?>

            <div class="c-table-wrapper">

                <table class="c-table">

                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Slug</th>
                            <th>Tipo</th>
                            <th>Modelo</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($pages as $p): ?>

                        <tr>

                            <td><?= htmlspecialchars($p['title']) ?></td>

                            <td><?= htmlspecialchars($p['slug']) ?></td>

                            <td>
                                <span class="c-badge <?= $p['type']==='blog' ? 'c-badge--info' : 'c-badge--neutral' ?>">
                                    <?= $p['type']==='blog' ? 'Blog' : 'Página' ?>
                                </span>
                            </td>

                            <td><?= $p['model_slug'] ?: '-' ?></td>

                            <td>
                                <?= $p['category']
                                    ? '<span class="c-badge c-badge--neutral">'.htmlspecialchars($p['category']).'</span>'
                                    : '-' ?>
                            </td>

                            <td>
                                <span class="c-badge <?= $p['status']==='published' ? 'c-badge--success' : 'c-badge--warning' ?>">
                                    <?= $p['status'] ?>
                                </span>
                            </td>

                            <td style="text-align:right;">

                                <a class="c-btn-secondary btn-sm"
                                   href="/public/admin/pages/edit.php?id=<?= $p['id'] ?>">
                                    Editar
                                </a>

                                <a class="c-btn-secondary btn-sm"
                                   href="<?= $baseUrl ?>/public/p.php?slug=<?= urlencode($p['slug']) ?>"
                                   target="_blank">
                                    Ver
                                </a>

                                <button class="c-btn-secondary btn-sm"
                                        onclick="toggleStatus(<?= $p['id'] ?>)">
                                    <?= $p['status']==='published'?'Despublicar':'Publicar' ?>
                                </button>

                                <?php if ($p['status'] !== 'published'): ?>
                                    <button class="c-btn-secondary btn-sm"
                                            onclick="deletePage(<?= $p['id'] ?>)">
                                        Excluir
                                    </button>
                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<script>
function toggleStatus(id){
    fetch('/app/actions/pages/toggle_status.php?id=' + id)
    .then(() => location.reload())
    .catch(() => alert('Erro'));
}

function deletePage(id){
    if(!confirm('Excluir página?')) return;
    fetch('/app/actions/pages/delete.php?id=' + id)
    .then(() => location.reload())
    .catch(() => alert('Erro'));
}
</script>

<?php
$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Ajuda</h3>

<p>
Páginas usam modelos.<br><br>
Página = livre<br>
Blog = estruturado
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';