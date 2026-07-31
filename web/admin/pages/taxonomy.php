<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$categories = $pdo->query("
    SELECT c.*,
           (
               SELECT COUNT(*)
               FROM core_page_contents p
               WHERE p.type = 'blog'
                 AND CONVERT(p.category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(c.name USING utf8mb4) COLLATE utf8mb4_unicode_ci
           ) AS total_posts
    FROM blog_categories c
    ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$subcategories = $pdo->query("
    SELECT s.*, c.name AS category_name,
           (
               SELECT COUNT(*)
               FROM core_page_contents p
               WHERE p.type = 'blog'
                 AND CONVERT(p.category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(c.name USING utf8mb4) COLLATE utf8mb4_unicode_ci
                 AND CONVERT(p.sub_category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(s.name USING utf8mb4) COLLATE utf8mb4_unicode_ci
           ) AS total_posts
    FROM blog_subcategories s
    INNER JOIN blog_categories c ON c.id = s.category_id
    ORDER BY c.name ASC, s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Categorias do Blog';
ob_start();
?>

<div class="c-page c-blog-taxonomy-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Categorias do Blog</h1>
            <p class="c-page-subtitle">Organize categorias e subcategorias usadas nos artigos</p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary" href="/web/admin/pages/create.php">Nova página</a>
            <a class="c-btn-secondary" href="/web/admin/pages/index.php">Páginas</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-grid c-grid-2">
            <div class="c-card">
                <h3>Nova categoria</h3>
                <form method="post" action="/app/actions/pages/taxonomy_save.php" class="c-taxonomy-form">
                    <input type="hidden" name="kind" value="category">
                    <div class="c-form-group">
                        <label>Nome</label>
                        <input class="c-input" name="name" placeholder="Ex: Economia" required maxlength="100">
                    </div>
                    <button class="c-btn-secondary">Criar categoria</button>
                </form>
            </div>

            <div class="c-card">
                <h3>Nova subcategoria</h3>
                <form method="post" action="/app/actions/pages/taxonomy_save.php" class="c-taxonomy-form">
                    <input type="hidden" name="kind" value="subcategory">
                    <div class="c-form-group">
                        <label>Categoria</label>
                        <select class="c-input" name="category_id" required>
                            <option value="">Selecione</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="c-form-group">
                        <label>Nome</label>
                        <input class="c-input" name="name" placeholder="Ex: Financas pessoais" required maxlength="100">
                    </div>
                    <button class="c-btn-secondary">Criar subcategoria</button>
                </form>
            </div>
        </div>

        <div class="c-card">
            <h3>Categorias</h3>
            <div class="c-table-wrapper">
                <table class="c-table">
                    <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Slug</th>
                        <th>Artigos</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$categories): ?>
                        <tr><td colspan="4">Nenhuma categoria cadastrada.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td>
                                <form method="post" action="/app/actions/pages/taxonomy_save.php" class="c-taxonomy-inline-form">
                                    <input type="hidden" name="kind" value="category">
                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                                    <input class="c-input" name="name" value="<?= htmlspecialchars((string)$category['name']) ?>" required maxlength="100">
                                    <button class="c-btn-secondary btn-sm">Salvar</button>
                                </form>
                            </td>
                            <td><?= htmlspecialchars((string)$category['slug']) ?></td>
                            <td><span class="c-badge c-badge--neutral"><?= (int)$category['total_posts'] ?></span></td>
                            <td style="text-align:right;">
                                <?php if ((int)$category['total_posts'] === 0): ?>
                                    <a class="c-btn-danger btn-sm" href="/app/actions/pages/taxonomy_delete.php?kind=category&id=<?= (int)$category['id'] ?>" onclick="return confirm('Excluir esta categoria?');">Excluir</a>
                                <?php else: ?>
                                    <span class="c-badge c-badge--warning">Em uso</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="c-card">
            <h3>Subcategorias</h3>
            <div class="c-table-wrapper">
                <table class="c-table">
                    <thead>
                    <tr>
                        <th>Categoria</th>
                        <th>Nome</th>
                        <th>Artigos</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$subcategories): ?>
                        <tr><td colspan="4">Nenhuma subcategoria cadastrada.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($subcategories as $subcategory): ?>
                        <?php $subFormId = 'subcategory-form-' . (int)$subcategory['id']; ?>
                        <tr>
                            <td>
                                <form id="<?= htmlspecialchars($subFormId) ?>" method="post" action="/app/actions/pages/taxonomy_save.php"></form>
                                <input form="<?= htmlspecialchars($subFormId) ?>" type="hidden" name="kind" value="subcategory">
                                <input form="<?= htmlspecialchars($subFormId) ?>" type="hidden" name="id" value="<?= (int)$subcategory['id'] ?>">
                                <select form="<?= htmlspecialchars($subFormId) ?>" class="c-input" name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int)$category['id'] ?>" <?= (int)$subcategory['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div class="c-taxonomy-inline-form">
                                    <input form="<?= htmlspecialchars($subFormId) ?>" class="c-input" name="name" value="<?= htmlspecialchars((string)$subcategory['name']) ?>" required maxlength="100">
                                    <button form="<?= htmlspecialchars($subFormId) ?>" class="c-btn-secondary btn-sm">Salvar</button>
                                </div>
                            </td>
                            <td><span class="c-badge c-badge--neutral"><?= (int)$subcategory['total_posts'] ?></span></td>
                            <td style="text-align:right;">
                                <?php if ((int)$subcategory['total_posts'] === 0): ?>
                                    <a class="c-btn-danger btn-sm" href="/app/actions/pages/taxonomy_delete.php?kind=subcategory&id=<?= (int)$subcategory['id'] ?>" onclick="return confirm('Excluir esta subcategoria?');">Excluir</a>
                                <?php else: ?>
                                    <span class="c-badge c-badge--warning">Em uso</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.c-taxonomy-form {
    display: grid;
    gap: 12px;
}

.c-taxonomy-inline-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
}

@media (max-width: 760px) {
    .c-taxonomy-inline-form {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
