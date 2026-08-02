<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureCategoryAddonSchema($pdo);
$advancedCategories = financeAdvancedCategoriesEnabled();

if (!$advancedCategories) {
    flash('error', 'Editar categorias fica disponivel no plano Start.');
    header('Location: ' . PROJECT_URL . '/admin/financeiro/categories.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM finance_categories WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    http_response_code(404);
    exit('Categoria nao encontrada');
}

$title = 'Editar Categoria';
$parentCategories = $pdo->prepare("
    SELECT id, name
    FROM finance_categories
    WHERE parent_id IS NULL
      AND id <> ?
    ORDER BY sort_order, name
");
$parentCategories->execute([$id]);
$parentCategories = $parentCategories->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Editar Categoria</h1>
            <p class="c-page-subtitle">Atualize nome, tipo e status</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/financeiro/categories.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form method="POST" action="<?= PROJECT_URL ?>/admin/financeiro/category_update.php?id=<?= (int)$category['id'] ?>" class="c-card">
            <?= csrf_field(); ?>

            <div class="finance-category-edit-grid <?= $advancedCategories ? 'has-parent' : '' ?>">
                <div class="c-form-group">
                    <label>Nome</label>
                    <input type="text" name="name" class="c-input" value="<?= htmlspecialchars($category['name']) ?>" required>
                </div>

                <?php if ($advancedCategories): ?>
                    <div class="c-form-group">
                        <label>Categoria pai</label>
                        <select name="parent_id" class="c-input">
                            <option value="">Categoria principal</option>
                            <?php foreach ($parentCategories as $parentCategory): ?>
                                <option value="<?= (int)$parentCategory['id'] ?>" <?= (string)($category['parent_id'] ?? '') === (string)$parentCategory['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$parentCategory['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="c-form-group">
                    <label>Tipo</label>
                    <select name="type" class="c-input">
                        <option value="both" <?= $category['type'] === 'both' ? 'selected' : '' ?>>Ambos</option>
                        <option value="income" <?= $category['type'] === 'income' ? 'selected' : '' ?>>Entrada</option>
                        <option value="expense" <?= $category['type'] === 'expense' ? 'selected' : '' ?>>Saída</option>
                    </select>
                </div>

                <?php if ($advancedCategories): ?>
                    <div class="c-form-group">
                        <label>Modelo</label>
                        <select name="form_model" class="c-input">
                            <?php foreach (financeFormModelOptions() as $modelValue => $modelLabel): ?>
                                <option value="<?= htmlspecialchars($modelValue) ?>" <?= (string)($category['form_model'] ?? 'simple') === $modelValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($modelLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="active" <?= $category['status'] === 'active' ? 'selected' : '' ?>>Ativa</option>
                        <option value="inactive" <?= $category['status'] === 'inactive' ? 'selected' : '' ?>>Inativa</option>
                    </select>
                </div>
            </div>

            <button class="c-btn-secondary">
                Atualizar Categoria
            </button>
        </form>

    </div>

</div>

<style>
.finance-category-edit-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 150px 150px 160px;
    gap: 12px;
}

.finance-category-edit-grid.has-parent {
    grid-template-columns: minmax(220px, 1fr) minmax(180px, .7fr) 150px 150px 160px;
}

@media (max-width: 760px) {
    .finance-category-edit-grid,
    .finance-category-edit-grid.has-parent {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';

