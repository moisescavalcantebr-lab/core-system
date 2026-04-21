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

/*
|--------------------------------------------------------------------------
| LISTAGEM
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT p.*, 
           b.name AS base_name,
           pl.price AS plan_price,
           pl.name AS plan_name,
           pl.billing_cycle
    FROM projects p
    LEFT JOIN bases b ON b.id = p.base_id
    LEFT JOIN plans pl ON pl.id = p.plan_id
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

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Projetos</h1>
            <p class="c-page-subtitle">Gerenciamento de projetos</p>
        </div>

        <div class="c-page-actions">
            <a href="/public/admin/projects/create.php" class="c-btn-secondary">
                + Criar Projeto
            </a>

            <a href="/public/admin/projects/requests.php" class="c-btn-secondary">
                Solicitações
            </a>
			            <a href="/public/admin/projects/health.php" class="c-btn-secondary">
                Saude
            </a>

        </div>

    </div>

    <div class="c-page-content">

        <?php if (empty($projects)): ?>

            <div class="c-card">
                Nenhum projeto encontrado.
            </div>

        <?php else: ?>

            <div class="c-table-wrapper">

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

                            <td>
                                <strong><?= htmlspecialchars($project['name']) ?></strong>
                            </td>

                            <td><?= htmlspecialchars($project['base_name']) ?></td>

                            <td><?= htmlspecialchars($project['slug']) ?></td>

                            <td><?= htmlspecialchars($project['plan_name']) ?></td>

                            <td>
                                <span class="c-badge <?= $statusClass ?>">
                                    <?= ucfirst($project['status']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="c-badge <?= $billingClass ?>">
                                    <?= ucfirst($project['billing_status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= $project['expires_at'] ?: '—' ?>
                            </td>

                            <td style="text-align:right;">

                                <a href="/public/admin/projects/view.php?id=<?= $project['id'] ?>"
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
    <h3>Filtro</h3>

    <form method="get">

        <input class="c-input" type="text" name="search"
               placeholder="Buscar..."
               value="'.htmlspecialchars($search).'">

        <select class="c-input" name="status">
            <option value="">Status</option>
            <option value="pending" '.($status==='pending'?'selected':'').'>Pendente</option>
            <option value="active" '.($status==='active'?'selected':'').'>Ativo</option>
            <option value="blocked" '.($status==='blocked'?'selected':'').'>Bloqueado</option>
        </select>

        <select class="c-input" name="billing">
            <option value="">Billing</option>
            <option value="pending" '.($billing==='pending'?'selected':'').'>Pendente</option>
            <option value="active" '.($billing==='active'?'selected':'').'>Ativo</option>
            <option value="suspended" '.($billing==='suspended'?'selected':'').'>Suspenso</option>
        </select>

        <br><br>

        <button class="c-btn-secondary c-btn-block">
            Filtrar
        </button>

    </form>
</div>

<br>

<div class="c-card">
    <h3>Resumo</h3>
    <p>Total: '.$totalProjects.'</p>
    <p>Página '.$page.' de '.$totalPages.'</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';