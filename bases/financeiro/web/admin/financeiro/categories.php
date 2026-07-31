<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureCategoryAddonSchema($pdo);

$title = 'Categorias Financeiras';
$advancedCategories = financeAdvancedCategoriesEnabled();
$where = $advancedCategories ? '' : 'WHERE c.parent_id IS NULL AND COALESCE(c.is_system, 0) = 0';
$categories = $pdo->query("
    SELECT c.*, p.name AS parent_name
    FROM finance_categories c
    LEFT JOIN finance_categories p ON p.id = c.parent_id
    {$where}
    ORDER BY COALESCE(p.sort_order, c.sort_order), COALESCE(p.name, c.name), c.parent_id IS NOT NULL, c.sort_order, c.name
")->fetchAll(PDO::FETCH_ASSOC);
$parentCategories = array_values(array_filter($categories, fn(array $category) => empty($category['parent_id'])));
$categoryTemplates = financeCategoryTemplates();
$recommendedTemplate = financeRecommendedCategoryTemplate($pdo);
$categoryTree = [];

foreach ($categories as $category) {
    $parentId = (int)($category['parent_id'] ?? 0);

    if ($parentId === 0) {
        $categoryTree[(int)$category['id']] = [
            'parent' => $category,
            'children' => [],
        ];
    }
}

foreach ($categories as $category) {
    $parentId = (int)($category['parent_id'] ?? 0);

    if ($parentId > 0) {
        if (!isset($categoryTree[$parentId])) {
            $categoryTree[$parentId] = [
                'parent' => [
                    'id' => $parentId,
                    'name' => (string)($category['parent_name'] ?? 'Categoria'),
                    'type' => $category['type'] ?? 'both',
                    'status' => 'active',
                ],
                'children' => [],
            ];
        }

        $categoryTree[$parentId]['children'][] = $category;
    }
}

if (!$advancedCategories) {
    foreach ($parentCategories as $category) {
        $categoryTree[(int)$category['id']] = [
            'parent' => $category,
            'children' => [],
        ];
    }
}

function financeCategoryTypeLabel(string $type): string
{
    return match($type) {
        'income' => 'Entrada',
        'expense' => 'Saida',
        default => 'Ambos'
    };
}

function financeCategoryActions(array $category): string
{
    ob_start();
    ?>
    <div class="finance-category-actions">
        <a class="c-btn-secondary"
           href="<?= PROJECT_URL ?>/admin/financeiro/category_edit.php?id=<?= (int)$category['id'] ?>">
            Editar
        </a>

        <form method="POST"
              action="<?= PROJECT_URL ?>/admin/financeiro/category_toggle.php?id=<?= (int)$category['id'] ?>">
            <?= csrf_field(); ?>
            <button class="c-btn-secondary" type="submit">
                <?= $category['status'] === 'active' ? 'Desativar' : 'Ativar' ?>
            </button>
        </form>

        <form method="POST"
              action="<?= PROJECT_URL ?>/admin/financeiro/category_delete.php?id=<?= (int)$category['id'] ?>"
              onsubmit="return confirm('Excluir esta categoria? Lancamentos com esta categoria ficarao sem categoria.');">
            <?= csrf_field(); ?>
            <button class="c-btn-secondary" type="submit">
                Excluir
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Categorias</h1>
            <p class="c-page-subtitle">Organize entradas e saidas</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/financeiro/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form method="POST" action="<?= PROJECT_URL ?>/admin/financeiro/category_store.php" class="c-card">
            <?= csrf_field(); ?>

            <div class="finance-category-form-grid <?= $advancedCategories ? 'has-parent' : '' ?>">
                <div class="c-form-group">
                    <label>Nome</label>
                    <input type="text" name="name" class="c-input" required>
                </div>

                <?php if ($advancedCategories): ?>
                    <div class="c-form-group">
                        <label>Categoria pai</label>
                        <select name="parent_id" class="c-input">
                            <option value="">Categoria principal</option>
                            <?php foreach ($parentCategories as $parentCategory): ?>
                                <option value="<?= (int)$parentCategory['id'] ?>">
                                    <?= htmlspecialchars((string)$parentCategory['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="c-form-group">
                    <label>Tipo</label>
                    <select name="type" class="c-input">
                        <option value="both">Ambos</option>
                        <option value="income">Entrada</option>
                        <option value="expense">Saida</option>
                    </select>
                </div>

                <?php if ($advancedCategories): ?>
                    <div class="c-form-group">
                        <label>Modelo</label>
                        <select name="form_model" class="c-input">
                            <?php foreach (financeFormModelOptions() as $modelValue => $modelLabel): ?>
                                <option value="<?= htmlspecialchars($modelValue) ?>">
                                    <?= htmlspecialchars($modelLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <button class="c-btn-secondary">Adicionar Categoria</button>
        </form>

        <?php if ($advancedCategories): ?>
            <div class="c-card">
                <h3>Modelos de categorias</h3>
                <p>Carregue um modelo pronto conforme o modo desta base. O sistema cria apenas categorias que ainda nao existem.</p>

                <div class="finance-category-template-grid">
                    <?php foreach ($categoryTemplates as $templateKey => $template): ?>
                        <?php $isRecommended = $templateKey === $recommendedTemplate; ?>
                        <form
                            method="POST"
                            action="<?= PROJECT_URL ?>/admin/financeiro/categories_seed.php"
                            class="finance-category-template-card <?= $isRecommended ? 'is-recommended' : '' ?>"
                            onsubmit="return confirm('Carregar este modelo de categorias? O sistema cria apenas categorias que ainda nao existem.');"
                        >
                            <?= csrf_field(); ?>
                            <input type="hidden" name="template" value="<?= htmlspecialchars($templateKey) ?>">
                            <div>
                                <strong><?= htmlspecialchars((string)$template['label']) ?></strong>
                                <?php if ($isRecommended): ?>
                                    <span class="c-badge c-badge--success">Recomendado</span>
                                <?php endif; ?>
                            </div>
                            <p><?= htmlspecialchars((string)$template['description']) ?></p>
                            <button class="c-btn-secondary">Carregar modelo</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="c-card finance-category-list-card">
            <?php if (empty($categories)): ?>
                <p>Nenhuma categoria cadastrada.</p>
            <?php else: ?>
                <div class="finance-category-accordion" data-category-accordion>
                    <?php foreach ($categoryTree as $index => $group): ?>
                        <?php
                        $parent = $group['parent'];
                        $children = $group['children'];
                        $activeChildren = count(array_filter($children, fn(array $child) => ($child['status'] ?? '') === 'active'));
                        ?>
                        <details class="finance-category-group">
                            <summary>
                                <span class="finance-category-summary-main">
                                    <strong><?= htmlspecialchars((string)$parent['name']) ?></strong>
                                    <span><?= count($children) ?> subcategoria<?= count($children) === 1 ? '' : 's' ?></span>
                                </span>

                                <span class="finance-category-summary-meta">
                                    <span><?= financeCategoryTypeLabel((string)($parent['type'] ?? 'both')) ?></span>
                                    <?php if ($advancedCategories): ?>
                                        <span class="c-badge c-badge--neutral"><?= htmlspecialchars(financeFormModelLabel((string)($parent['form_model'] ?? 'simple'))) ?></span>
                                    <?php endif; ?>
                                    <span class="c-badge <?= ($parent['status'] ?? 'active') === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>">
                                        <?= ($parent['status'] ?? 'active') === 'active' ? 'Ativa' : 'Inativa' ?>
                                    </span>
                                    <?php if ($children): ?>
                                        <span><?= $activeChildren ?>/<?= count($children) ?> ativa<?= $activeChildren === 1 ? '' : 's' ?></span>
                                    <?php endif; ?>
                                </span>
                            </summary>

                            <div class="finance-category-group-body">
                                <div class="finance-category-parent-row">
                                    <div>
                                        <span>Categoria principal</span>
                                        <strong><?= htmlspecialchars((string)$parent['name']) ?></strong>
                                    </div>
                                    <?= financeCategoryActions($parent) ?>
                                </div>

                                <?php if (empty($children)): ?>
                                    <div class="finance-category-empty">
                                        Nenhuma subcategoria cadastrada.
                                    </div>
                                <?php else: ?>
                                    <div class="c-table-wrapper finance-category-table-wrapper">
                                        <table class="c-table">
                                            <thead>
                                                <tr>
                                                    <th>Subcategoria</th>
                                                    <th>Tipo</th>
                                                    <?php if ($advancedCategories): ?>
                                                        <th>Modelo</th>
                                                    <?php endif; ?>
                                                    <th>Status</th>
                                                    <th style="text-align:right;">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($children as $child): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars((string)$child['name']) ?></strong></td>
                                                    <td><?= financeCategoryTypeLabel((string)$child['type']) ?></td>
                                                    <?php if ($advancedCategories): ?>
                                                        <td><?= htmlspecialchars(financeFormModelLabel((string)($child['form_model'] ?? 'simple'))) ?></td>
                                                    <?php endif; ?>
                                                    <td>
                                                            <span class="c-badge <?= $child['status'] === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>">
                                                                <?= $child['status'] === 'active' ? 'Ativa' : 'Inativa' ?>
                                                            </span>
                                                        </td>
                                                        <td style="text-align:right;">
                                                            <?= financeCategoryActions($child) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<style>
.finance-category-list-card {
    padding: 10px;
}

.finance-category-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px;
    margin-top: 12px;
}

.finance-category-template-card {
    display: grid;
    gap: 8px;
    align-content: start;
    border: 1px solid var(--border-color);
    background: rgba(15, 23, 42, .24);
    padding: 12px;
}

.finance-category-template-card.is-recommended {
    border-color: rgba(34, 197, 94, .45);
    background: rgba(34, 197, 94, .08);
}

.finance-category-template-card > div {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
}

.finance-category-template-card p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 12px;
}

.finance-category-form-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 150px;
    gap: 12px;
}

.finance-category-form-grid > *,
.finance-category-form-grid .c-input {
    min-width: 0;
    max-width: 100%;
}

.finance-category-form-grid.has-parent {
    grid-template-columns: minmax(220px, 1fr) minmax(180px, .7fr) 150px 160px;
}

.finance-category-accordion {
    display: grid;
    gap: 8px;
}

.finance-category-group {
    border: 1px solid var(--border-color);
    background: rgba(15, 23, 42, .2);
}

.finance-category-group summary {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
    padding: 10px 12px;
    cursor: pointer;
    list-style: none;
}

.finance-category-group summary::-webkit-details-marker {
    display: none;
}

.finance-category-group summary::before {
    content: '+';
    width: 22px;
    height: 22px;
    display: inline-grid;
    place-items: center;
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    grid-column: 1;
}

.finance-category-group[open] summary::before {
    content: '-';
}

.finance-category-summary-main {
    display: flex;
    gap: 10px;
    align-items: center;
    min-width: 0;
    grid-column: 2;
}

.finance-category-summary-main strong {
    font-size: 13px;
    white-space: nowrap;
}

.finance-category-summary-main span,
.finance-category-summary-meta,
.finance-category-parent-row span,
.finance-category-empty {
    color: var(--text-secondary);
    font-size: 12px;
}

.finance-category-summary-meta {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    grid-column: 3;
}

.finance-category-group-body {
    display: grid;
    gap: 10px;
    padding: 0 12px 12px;
}

.finance-category-parent-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px;
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(148, 163, 184, .06);
}

.finance-category-parent-row strong {
    display: block;
    margin-top: 3px;
}

.finance-category-actions {
    display: inline-flex;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: wrap;
}

.finance-category-actions form {
    display: inline;
    margin: 0;
}

.finance-category-table-wrapper {
    border-radius: 0;
}

.finance-category-empty {
    padding: 10px;
    border: 1px dashed var(--border-color);
}

@media (max-width: 760px) {
    .finance-category-form-grid,
    .finance-category-form-grid.has-parent {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .finance-category-form-grid .c-input,
    .finance-category-form-grid .c-btn-secondary {
        width: 100%;
        max-width: 100%;
        margin-right: 0;
    }

    .finance-category-list-card {
        padding: 8px;
    }

    .finance-category-group summary {
        grid-template-columns: auto minmax(0, 1fr);
        gap: 10px;
        padding: 10px;
    }

    .finance-category-summary-main {
        grid-column: 2;
        flex-wrap: wrap;
        gap: 5px 8px;
    }

    .finance-category-summary-main strong {
        white-space: normal;
        width: 100%;
    }

    .finance-category-summary-meta {
        grid-column: 1 / -1;
        padding-left: 32px;
        gap: 8px;
    }

    .finance-category-summary-meta,
    .finance-category-parent-row,
    .finance-category-actions {
        justify-content: flex-start;
    }

    .finance-category-parent-row {
        align-items: flex-start;
        flex-direction: column;
    }

    .finance-category-table-wrapper {
        overflow: visible;
    }

    .finance-category-table-wrapper table,
    .finance-category-table-wrapper thead,
    .finance-category-table-wrapper tbody,
    .finance-category-table-wrapper tr,
    .finance-category-table-wrapper th,
    .finance-category-table-wrapper td {
        display: block;
        width: 100%;
    }

    .finance-category-table-wrapper thead {
        display: none;
    }

    .finance-category-table-wrapper tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .finance-category-table-wrapper td {
        padding: 0;
        border: 0;
    }

    .finance-category-table-wrapper td:nth-child(1) {
        grid-column: 1 / -1;
    }

    .finance-category-table-wrapper td:nth-child(2),
    .finance-category-table-wrapper td:nth-child(3) {
        align-self: center;
    }

    .finance-category-table-wrapper td:nth-child(4) {
        grid-column: 1 / -1;
        text-align: left !important;
    }

    .finance-category-actions {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
    }

    .finance-category-actions .c-btn-secondary,
    .finance-category-actions form,
    .finance-category-actions button {
        width: 100%;
        min-width: 0;
        margin-right: 0;
        justify-content: center;
        white-space: normal;
    }
}

@media (max-width: 420px) {
    .finance-category-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .finance-category-actions form:last-child {
        grid-column: 1 / -1;
    }
}
</style>

<script>
document.querySelectorAll('[data-category-accordion]').forEach(function (accordion) {
    accordion.querySelectorAll('details').forEach(function (details) {
        details.addEventListener('toggle', function () {
            if (!details.open) {
                return;
            }

            accordion.querySelectorAll('details[open]').forEach(function (opened) {
                if (opened !== details) {
                    opened.open = false;
                }
            });
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';

