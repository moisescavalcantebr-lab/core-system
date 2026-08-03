<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);
financeEnsureCategoryAddonSchema($pdo);

$title = 'Lancamentos';
$advancedCategories = financeAdvancedCategoriesEnabled();

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'type' => trim((string)($_GET['type'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'category_id' => (int)($_GET['category_id'] ?? 0),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];

$where = ["COALESCE(e.source, 'manual') NOT IN ('balance_deposit', 'balance_usage')"];
$params = [];

if ($filters['q'] !== '') {
    $where[] = "(e.title LIKE ? OR e.description LIKE ? OR e.party_name LIKE ?)";
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like);
}

if (in_array($filters['type'], ['income', 'expense'], true)) {
    $where[] = 'e.type = ?';
    $params[] = $filters['type'];
}

if (in_array($filters['status'], ['pending', 'paid', 'canceled'], true)) {
    $where[] = 'e.status = ?';
    $params[] = $filters['status'];
}

if ($filters['category_id'] > 0) {
    $where[] = '(e.category_id = ? OR c.parent_id = ?)';
    $params[] = $filters['category_id'];
    $params[] = $filters['category_id'];
}

if ($filters['date_from'] !== '') {
    $where[] = "COALESCE(e.due_date, DATE(e.created_at)) >= ?";
    $params[] = $filters['date_from'];
}

if ($filters['date_to'] !== '') {
    $where[] = "COALESCE(e.due_date, DATE(e.created_at)) <= ?";
    $params[] = $filters['date_to'];
}

$whereSql = implode(' AND ', $where);

$categories = $pdo->query("
    SELECT id, name, parent_id
    FROM finance_categories
    WHERE status = 'active'
    ORDER BY COALESCE(parent_id, id), parent_id IS NOT NULL, name
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT e.*, c.name AS category_name,
        pc.name AS parent_category_name,
        e.party_name AS party_display,
        created_user.name AS created_by_name,
        updated_user.name AS updated_by_name,
        GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tags_text
    FROM finance_entries e
    LEFT JOIN finance_categories c ON c.id = e.category_id
    LEFT JOIN finance_categories pc ON pc.id = c.parent_id
    LEFT JOIN project_users created_user ON created_user.id = e.created_by_user_id
    LEFT JOIN project_users updated_user ON updated_user.id = e.updated_by_user_id
    LEFT JOIN finance_entry_tags et ON et.entry_id = e.id
    LEFT JOIN finance_tags t ON t.id = et.tag_id
    WHERE {$whereSql}
    GROUP BY e.id
    ORDER BY COALESCE(e.due_date, e.created_at) DESC, e.id DESC
    LIMIT 200
");
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
.finance-filter-card {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.finance-filter-card,
.finance-filter-card * {
    box-sizing: border-box;
    min-width: 0;
}

.finance-filter-card .c-input {
    width: 100%;
    max-width: 100%;
}

.finance-filter-card input[type="date"] {
    appearance: none;
    -webkit-appearance: none;
}

.finance-filter-grid {
    display: grid;
    gap: 10px;
}

.finance-filter-actions {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.finance-entry-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 6px;
    min-width: 112px;
}

.finance-entry-actions form {
    margin: 0;
}

.finance-entry-actions .c-btn-secondary {
    min-height: 28px;
    padding: 6px 10px;
    white-space: nowrap;
}

@media (max-width: 700px) {
    .finance-filter-actions {
        flex-wrap: wrap;
    }

    .finance-filter-actions .c-btn-secondary {
        flex: 1 1 120px;
        text-align: center;
    }

    .finance-entry-actions {
        justify-content: flex-start;
    }

    .finance-entry-table-wrapper {
        overflow: visible;
    }

    .finance-entry-table-wrapper table,
    .finance-entry-table-wrapper thead,
    .finance-entry-table-wrapper tbody,
    .finance-entry-table-wrapper tr,
    .finance-entry-table-wrapper th,
    .finance-entry-table-wrapper td {
        display: block;
        width: 100%;
    }

    .finance-entry-table-wrapper thead {
        display: none;
    }

    .finance-entry-table-wrapper tr {
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        padding: 12px;
        margin-bottom: 10px;
    }

    .finance-entry-table-wrapper td {
        border: 0;
        padding: 6px 0;
        overflow-wrap: anywhere;
    }

    .finance-entry-table-wrapper td::before {
        content: attr(data-label);
        display: block;
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 2px;
    }

    .finance-entry-table-wrapper td:first-child::before {
        display: none;
    }
}

@media (min-width: 1100px) {
    .finance-filter-grid {
        grid-template-columns: minmax(220px, 1.6fr) minmax(120px, .7fr) minmax(120px, .7fr) minmax(160px, 1fr) minmax(130px, .8fr) minmax(130px, .8fr) auto;
        align-items: end;
    }

    .finance-filter-actions {
        padding-bottom: 1px;
    }
}
</style>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Lancamentos</h1>
            <p class="c-page-subtitle">Filtre, conclua e edite os registros financeiros</p>
        </div>

        <div class="c-page-actions">
            <a href="<?= PROJECT_URL ?>/admin/financeiro/index.php" class="c-btn-secondary">Financeiro</a>
            <a href="<?= PROJECT_URL ?>/admin/financeiro/create.php" class="c-btn-secondary">Novo Lancamento</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="GET" class="finance-filter-card">
            <div class="finance-filter-grid">
                <div class="c-form-group">
                    <label>Buscar</label>
                    <input type="text" name="q" class="c-input" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Titulo, descricao ou parte...">
                </div>
                <div class="c-form-group">
                    <label>Tipo</label>
                    <select name="type" class="c-input">
                        <option value="">Todos</option>
                        <option value="income" <?= $filters['type'] === 'income' ? 'selected' : '' ?>>Entrada</option>
                        <option value="expense" <?= $filters['type'] === 'expense' ? 'selected' : '' ?>>Saida</option>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="">Todos</option>
                        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Pago</option>
                        <option value="canceled" <?= $filters['status'] === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Categoria</label>
                    <select name="category_id" class="c-input">
                        <option value="0">Todas</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= $filters['category_id'] === (int)$category['id'] ? 'selected' : '' ?>>
                                <?= !empty($category['parent_id']) ? ' - ' : '' ?><?= htmlspecialchars((string)$category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Inicio</label>
                    <input type="date" name="date_from" class="c-input" value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                <div class="c-form-group">
                    <label>Fim</label>
                    <input type="date" name="date_to" class="c-input" value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
                <div class="finance-filter-actions">
                    <button class="c-btn-secondary">Filtrar</button>
                    <a href="<?= PROJECT_URL ?>/admin/financeiro/lancamentos.php" class="c-btn-secondary">Limpar</a>
                </div>
            </div>
        </form>

        <div class="c-card">
            <h3>Lancamentos</h3>

            <?php if (empty($entries)): ?>
                <p>Nenhum lancamento encontrado.</p>
            <?php else: ?>
                <div class="c-table-wrapper finance-entry-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Titulo</th>
                                <th>Tipo</th>
                                <th>Parte</th>
                                <th>Categoria</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Responsavel</th>
                                <th>Status</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <?php
                                    $isPending = (string)$entry['status'] === 'pending';
                                    $isOverdue = $isPending
                                        && !empty($entry['due_date'])
                                        && (string)$entry['due_date'] < date('Y-m-d');
                                ?>
                                <tr>
                                    <td data-label="Titulo">
                                        <strong><?= htmlspecialchars((string)$entry['title']) ?></strong>
                                        <?php if (!empty($entry['payment_method'])): ?>
                                            <div>Pagamento: <?= htmlspecialchars(financePaymentMethodLabel($entry['payment_method'] ?? null)) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($entry['receipt_path'])): ?>
                                            <a href="<?= PROJECT_URL ?>/<?= htmlspecialchars((string)$entry['receipt_path']) ?>" target="_blank" rel="noopener noreferrer">Ver comprovante</a>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Tipo"><?= financeEntryTypeLabel($entry) ?></td>
                                    <td data-label="Parte">
                                        <?= htmlspecialchars(financePartyTypeLabel($entry['party_type'] ?? 'other')) ?>
                                        <?php if (!empty($entry['party_display'])): ?>
                                            <div><?= htmlspecialchars((string)$entry['party_display']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Categoria">
                                        <?php if (!empty($entry['parent_category_name'])): ?>
                                            <span style="color:var(--text-secondary);"><?= htmlspecialchars((string)$entry['parent_category_name']) ?> &gt;</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars((string)($entry['category_name'] ?? '-')) ?>
                                        <?php if ($advancedCategories && !empty($entry['tags_text'])): ?>
                                            <div style="color:var(--text-secondary); font-size:12px;"><?= htmlspecialchars((string)$entry['tags_text']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Valor"><?= financeMoney((float)$entry['amount']) ?></td>
                                    <td data-label="Vencimento">
                                        <?= htmlspecialchars($entry['due_date'] ?? '-') ?>
                                        <?php if ($isOverdue): ?>
                                            <div><span class="c-badge c-badge--danger">Atrasado</span></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Responsavel">
                                        <?= htmlspecialchars($entry['created_by_name'] ?? '-') ?>
                                        <?php if (!empty($entry['updated_by_name']) && $entry['updated_by_name'] !== $entry['created_by_name']): ?>
                                            <div>Alt.: <?= htmlspecialchars($entry['updated_by_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status">
                                        <span class="c-badge <?= financeStatusBadge((string)$entry['status']) ?>">
                                            <?= financeStatusLabel((string)$entry['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Acoes">
                                        <div class="finance-entry-actions">
                                            <?php if ($isPending): ?>
                                                <form method="post" action="<?= PROJECT_URL ?>/admin/financeiro/complete.php">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$entry['id'] ?>">
                                                    <button class="c-btn-secondary" type="submit">Concluir</button>
                                                </form>
                                            <?php endif; ?>
                                            <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/edit.php?id=<?= (int)$entry['id'] ?>">Editar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
