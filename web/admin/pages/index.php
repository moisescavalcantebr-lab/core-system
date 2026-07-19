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

<div class="c-page c-pages-admin">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Páginas</h1>
            <p class="c-page-subtitle">Gerenciamento de conteúdo</p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary" href="/web/admin/pages/create.php">
                + Nova Página
            </a>
        </div>

    </div>

    <div class="c-page-content">

        <!-- FILTROS -->
        <div class="c-card c-pages-filter-card">

            <div class="c-page-actions c-pages-filter-actions">

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

            <div class="c-table-wrapper c-pages-table-wrapper">

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

                            <td data-label="Título"><?= htmlspecialchars($p['title']) ?></td>

                            <td data-label="Slug"><?= htmlspecialchars($p['slug']) ?></td>

                            <td data-label="Tipo">
                                <span class="c-badge <?= $p['type']==='blog' ? 'c-badge--info' : 'c-badge--neutral' ?>">
                                    <?= $p['type']==='blog' ? 'Blog' : 'Página' ?>
                                </span>
                            </td>

                            <td data-label="Modelo"><?= $p['model_slug'] ?: '-' ?></td>

                            <td data-label="Categoria">
                                <?= $p['category']
                                    ? '<span class="c-badge c-badge--neutral">'.htmlspecialchars($p['category']).'</span>'
                                    : '-' ?>
                            </td>

                            <td data-label="Status">
                                <span class="c-badge <?= $p['status']==='published' ? 'c-badge--success' : 'c-badge--warning' ?>">
                                    <?= $p['status'] ?>
                                </span>
                            </td>

                            <td data-label="Ações" class="c-pages-row-actions" style="text-align:right;">

                                <a class="c-btn-secondary btn-sm"
                                   href="/web/admin/pages/edit.php?id=<?= $p['id'] ?>">
                                    Configurar
                                </a>

                                <a class="c-btn-secondary btn-sm"
                                   href="<?= $baseUrl ?>/web/p.php?slug=<?= urlencode($p['slug']) ?>"
                                   target="_blank">
                                    Ver
                                </a>

                            </td>

                        </tr>

                    <?php endforeach ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<style>
@media (max-width: 760px) {
    .c-pages-admin .c-page-header {
        align-items: flex-start;
    }

    .c-pages-admin .c-page-header > div:first-child {
        min-width: 0;
        flex: 1 1 170px;
    }

    .c-pages-admin .c-page-header > .c-page-actions {
        flex: 0 0 auto;
    }

    .c-pages-filter-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .c-pages-filter-actions .c-btn-secondary,
    .c-pages-filter-card select {
        width: 100%;
    }

    .c-pages-table-wrapper {
        overflow: visible;
        background: transparent;
        box-shadow: none;
    }

    .c-pages-table-wrapper table,
    .c-pages-table-wrapper thead,
    .c-pages-table-wrapper tbody,
    .c-pages-table-wrapper tr,
    .c-pages-table-wrapper td {
        display: block;
        width: 100%;
    }

    .c-pages-table-wrapper thead {
        display: none;
    }

    .c-pages-table-wrapper tr {
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }

    .c-pages-table-wrapper td {
        display: grid;
        grid-template-columns: 88px minmax(0, 1fr);
        gap: 10px;
        align-items: center;
        padding: 7px 0;
        border: 0;
        word-break: break-word;
    }

    .c-pages-table-wrapper td::before {
        content: attr(data-label);
        color: var(--text-secondary);
        font-weight: 700;
    }

    .c-pages-row-actions {
        display: block !important;
        text-align: left !important;
    }

    .c-pages-row-actions::before {
        display: block;
        margin-bottom: 8px;
    }

    .c-pages-row-actions .btn-sm {
        width: calc(50% - 4px);
        margin: 0 4px 8px 0;
        justify-content: center;
        min-height: 38px;
    }
}

@media (min-width: 761px) {
    .c-pages-table-wrapper table {
        table-layout: fixed;
    }

    .c-pages-table-wrapper th:nth-child(1),
    .c-pages-table-wrapper td:nth-child(1) {
        width: 34%;
    }

    .c-pages-table-wrapper th:nth-child(2),
    .c-pages-table-wrapper td:nth-child(2) {
        width: 18%;
    }

    .c-pages-table-wrapper th:nth-child(3),
    .c-pages-table-wrapper td:nth-child(3) {
        width: 8%;
    }

    .c-pages-table-wrapper th:nth-child(4),
    .c-pages-table-wrapper td:nth-child(4) {
        width: 10%;
    }

    .c-pages-table-wrapper th:nth-child(5),
    .c-pages-table-wrapper td:nth-child(5) {
        width: 12%;
    }

    .c-pages-table-wrapper th:nth-child(6),
    .c-pages-table-wrapper td:nth-child(6) {
        width: 9%;
    }

    .c-pages-table-wrapper th:nth-child(7),
    .c-pages-table-wrapper td:nth-child(7) {
        width: 115px;
    }

    .c-pages-table-wrapper td {
        vertical-align: middle;
        white-space: normal;
        word-break: normal;
        overflow-wrap: anywhere;
    }

    .c-pages-row-actions {
        display: flex;
        justify-content: flex-end;
        gap: 6px;
    }

    .c-pages-row-actions .btn-sm {
        margin: 0;
        white-space: nowrap;
    }
}

@media (max-width: 420px) {
    .c-pages-filter-actions {
        grid-template-columns: 1fr;
    }

    .c-pages-row-actions .btn-sm {
        width: 100%;
        margin-right: 0;
    }
}
</style>

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
