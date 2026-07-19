<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$status  = $_GET['status'] ?? '';
$billing = $_GET['billing'] ?? '';
$search  = trim($_GET['search'] ?? '');
$order   = $_GET['order'] ?? 'p.id';
$dir     = $_GET['dir'] ?? 'DESC';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$allowedOrder = [
    'p.name','b.name','pl.price',
    'p.status','p.billing_status',
    'p.expires_at','p.created_at'
];

if (!in_array($order, $allowedOrder)) {
    $order = 'p.id';
}

$dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

$where  = ["p.status != 'deleted'"];
$params = [];

if ($status && in_array($status, ['pending','active','blocked'])) {
    $where[] = "p.status = :status";
    $params['status'] = $status;
}

if ($billing && in_array($billing, ['pending','active','suspended'])) {
    $where[] = "p.billing_status = :billing";
    $params['billing'] = $billing;
}

if ($search !== '') {
    $where[] = "(p.name LIKE :search OR p.slug LIKE :search)";
    $params['search'] = "%$search%";
}

$whereSql = implode(' AND ', $where);

/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE $whereSql");
$stmt->execute($params);

$totalProjects = $stmt->fetchColumn();
$totalPages = max(1, ceil($totalProjects / $limit));

$pendingRequests = (int)$pdo->query("
    SELECT COUNT(*)
    FROM leads
    WHERE implementation_status = 'ready'
")->fetchColumn();

$pendingUpgradeRequests = (int)$pdo->query("
    SELECT COUNT(*)
    FROM plan_upgrade_requests
    WHERE status = 'pending'
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| LISTAGEM
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT p.*, 
           b.name AS base_name,
           COALESCE(pp.price, pl.price) AS plan_price,
           pl.name AS plan_name,
           COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle
    FROM projects p
    LEFT JOIN bases b ON b.id = p.base_id
    LEFT JOIN plans pl ON pl.id = p.plan_id
    LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
    WHERE $whereSql
    ORDER BY $order $dir
    LIMIT $limit OFFSET $offset
");

$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Projetos';

ob_start();
?>

<div class="c-page c-projects-admin">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Projetos</h1>
            <p class="c-page-subtitle">Gerenciamento de projetos</p>
        </div>

        <div class="c-page-actions">
            <a href="/web/admin/projects/create.php" class="c-btn-secondary">
                + Criar Projeto
            </a>

            <a href="/web/admin/projects/requests.php" class="c-btn-secondary">
                Solicitações<?= $pendingRequests > 0 ? ' (' . $pendingRequests . ')' : '' ?>
            </a>

            <a href="/web/admin/projects/upgrade_requests.php" class="c-btn-secondary">
                Upgrades<?= $pendingUpgradeRequests > 0 ? ' (' . $pendingUpgradeRequests . ')' : '' ?>
            </a>

            <a href="/web/admin/projects/health.php" class="c-btn-secondary">
                Saude
            </a>

        </div>

    </div>

    <div class="c-page-content">

        <div class="c-card c-projects-filter-card">
            <form method="get">
                <div class="c-projects-filter-grid">
                    <div class="c-form-group">
                        <label>Buscar</label>
                        <input class="c-input" type="text" name="search"
                               placeholder="Buscar projeto ou slug..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Status</label>
                        <select class="c-input" name="status">
                            <option value="">Todos</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendente</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Bloqueado</option>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Billing</label>
                        <select class="c-input" name="billing">
                            <option value="">Todos</option>
                            <option value="pending" <?= $billing === 'pending' ? 'selected' : '' ?>>Pendente</option>
                            <option value="active" <?= $billing === 'active' ? 'selected' : '' ?>>Ativo</option>
                            <option value="suspended" <?= $billing === 'suspended' ? 'selected' : '' ?>>Suspenso</option>
                        </select>
                    </div>
                </div>

                <button class="c-btn-secondary">
                    Filtrar
                </button>
            </form>
        </div>

        <?php if (empty($projects)): ?>

            <div class="c-card">
                Nenhum projeto encontrado.
            </div>

        <?php else: ?>

            <div class="c-table-wrapper c-projects-table-wrapper">

                <table class="c-table">

                    <thead>
                        <tr>
                            <th>Projeto</th>
                            <th>Base</th>
                            <th>Slug</th>
                            <th>Plano</th>
                            <th>Status</th>
                            <th>Billing</th>
                            <th>Expira</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($projects as $project):

                        $statusClass = match($project['status']) {
                            'active'  => 'c-badge--success',
                            'blocked' => 'c-badge--danger',
                            'pending' => 'c-badge--warning',
                            default   => 'c-badge--neutral'
                        };

                        $billingClass = $project['billing_status'] === 'active'
                            ? 'c-badge--success'
                            : 'c-badge--danger';
                    ?>

                        <tr>

                            <td data-label="Projeto">
                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                            </td>

                            <td data-label="Base"><?= htmlspecialchars($project['base_name']) ?></td>

                            <td data-label="Slug"><?= htmlspecialchars($project['slug']) ?></td>

                            <td data-label="Plano"><?= htmlspecialchars($project['plan_name']) ?></td>

                            <td data-label="Status">
                                <span class="c-badge <?= $statusClass ?>">
                                    <?= ucfirst($project['status']) ?>
                                </span>
                            </td>

                            <td data-label="Billing">
                                <span class="c-badge <?= $billingClass ?>">
                                    <?= ucfirst($project['billing_status']) ?>
                                </span>
                            </td>

                            <td data-label="Expira">
                                <?= $project['expires_at'] ?: '—' ?>
                            </td>

                            <td data-label="Ações" class="c-projects-row-actions" style="text-align:right;">

                                <a href="/web/admin/projects/view.php?id=<?= $project['id'] ?>"
                                   class="c-btn-secondary">
                                    Ver Detalhes
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<style>
.c-projects-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 160px 160px;
    gap: 12px;
}

@media (max-width: 760px) {
    .c-projects-admin .c-page-header {
        align-items: flex-start;
    }

    .c-projects-admin .c-page-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
        gap: 8px;
    }

    .c-projects-admin .c-page-actions .c-btn-secondary {
        width: 100%;
        justify-content: center;
        min-height: 40px;
    }

    .c-projects-filter-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .c-projects-filter-card .c-btn-secondary {
        width: 100%;
        justify-content: center;
    }

    .c-projects-table-wrapper {
        overflow: visible;
        background: transparent;
        box-shadow: none;
    }

    .c-projects-table-wrapper table,
    .c-projects-table-wrapper thead,
    .c-projects-table-wrapper tbody,
    .c-projects-table-wrapper tr,
    .c-projects-table-wrapper td {
        display: block;
        width: 100%;
    }

    .c-projects-table-wrapper thead {
        display: none;
    }

    .c-projects-table-wrapper tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 10px 12px;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }

    .c-projects-table-wrapper td {
        padding: 0;
        border: 0;
        min-width: 0;
        word-break: break-word;
    }

    .c-projects-table-wrapper td::before {
        content: attr(data-label);
        display: block;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .c-projects-row-actions {
        grid-column: 1 / -1;
        text-align: left !important;
    }

    .c-projects-row-actions .c-btn-secondary {
        width: 100%;
        justify-content: center;
        min-height: 40px;
    }
}

@media (max-width: 420px) {
    .c-projects-admin .c-page-actions,
    .c-projects-table-wrapper tr {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR DIREITA
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '
<div class="c-card">
    <h3>Informações</h3>
    <p>Total: '.$totalProjects.'</p>
    <p>Página '.$page.' de '.$totalPages.'</p>
    <p>Solicitações são revisadas antes da criação do projeto.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
