<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$today = date('Y-m-d');
$periodStart = $_GET['start'] ?? date('Y-m-01');
$periodEnd = $_GET['end'] ?? $today;
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$projectId = (int)($_GET['project_id'] ?? 0);
$planId = (int)($_GET['plan_id'] ?? 0);

$allowedTypes = ['all', 'entrada', 'saida', 'pendente'];
$allowedStatus = ['all', 'pending', 'approved', 'rejected', 'sent'];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$periodStart)) {
    $periodStart = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$periodEnd)) {
    $periodEnd = $today;
}

$rangeStart = $periodStart . ' 00:00:00';
$rangeEnd = $periodEnd . ' 23:59:59';

function moneyBr(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

$projects = $pdo->query("
    SELECT id, name, slug
    FROM projects
    WHERE status <> 'deleted'
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$plans = $pdo->query("
    SELECT id, name
    FROM plans
    WHERE status = 1
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$summaryParams = [
    'start' => $rangeStart,
    'end' => $rangeEnd,
];
$summaryProjectSql = '';
$summaryPlanSql = '';

if ($projectId > 0) {
    $summaryProjectSql = ' AND project_id = :project_id';
    $summaryParams['project_id'] = $projectId;
}

if ($planId > 0) {
    $summaryPlanSql = ' AND plan_id = :plan_id';
    $summaryParams['plan_id'] = $planId;
}

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM plan_upgrade_requests
    WHERE status = 'approved'
      AND reviewed_at BETWEEN :start AND :end
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute($summaryParams);
$approvedIncome = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM plan_refund_requests
    WHERE status = 'approved'
      AND sent_at IS NOT NULL
      AND sent_at BETWEEN :start AND :end
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute($summaryParams);
$sentRefunds = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM plan_refund_requests
    WHERE status = 'approved'
      AND sent_at IS NULL
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute(array_diff_key($summaryParams, ['start' => true, 'end' => true]));
$approvedRefundsToSend = (float)$stmt->fetchColumn();

$pendingParams = array_diff_key($summaryParams, ['start' => true, 'end' => true]);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM plan_upgrade_requests
    WHERE status = 'pending'
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute($pendingParams);
$pendingUpgrades = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM plan_upgrade_requests
    WHERE status = 'pending'
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute($pendingParams);
$pendingUpgradeAmount = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM plan_refund_requests
    WHERE status = 'pending'
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute($pendingParams);
$pendingRefunds = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM plan_refund_requests
    WHERE status = 'pending'
      {$summaryProjectSql}
      {$summaryPlanSql}
");
$stmt->execute($pendingParams);
$pendingRefundAmount = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM project_wallet_requests
    WHERE status = 'pending'
      AND created_at BETWEEN :start AND :end
");
$stmt->execute([
    'start' => $rangeStart,
    'end' => $rangeEnd,
]);
$pendingWalletRequests = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM project_wallet_requests
    WHERE status = 'pending'
      AND created_at BETWEEN :start AND :end
");
$stmt->execute([
    'start' => $rangeStart,
    'end' => $rangeEnd,
]);
$pendingWalletAmount = (float)$stmt->fetchColumn();

$totalWalletBalance = (float)$pdo->query("
    SELECT COALESCE(SUM(
        CASE
            WHEN movement_type = 'credit' THEN amount
            WHEN movement_type = 'debit' THEN -amount
            ELSE 0
        END
    ), 0)
    FROM project_wallet_movements
    WHERE status = 'applied'
")->fetchColumn();

$mrr = (float)$pdo->query("
    SELECT COALESCE(SUM(COALESCE(pp.price, pl.price)), 0)
    FROM projects p
    INNER JOIN plans pl ON pl.id = p.plan_id
    LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
    WHERE p.status = 'active'
      AND p.billing_status = 'active'
      AND (
          (pp.id IS NOT NULL AND pp.billing_cycle + 0 = 2)
          OR (pp.id IS NULL AND pl.billing_cycle + 0 = 2)
      )
")->fetchColumn();

$annualRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(COALESCE(pp.price, pl.price)), 0)
    FROM projects p
    INNER JOIN plans pl ON pl.id = p.plan_id
    LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
    WHERE p.status = 'active'
      AND p.billing_status = 'active'
      AND (
          (pp.id IS NOT NULL AND pp.billing_cycle + 0 = 3)
          OR (pp.id IS NULL AND pl.billing_cycle + 0 = 3)
      )
")->fetchColumn();

$entries = [];

$entryWhere = ['movement_date BETWEEN :start AND :end'];
$entryParams = [
    'start' => $rangeStart,
    'end' => $rangeEnd,
];

if ($type === 'entrada') {
    $entryWhere[] = "movement_type = 'entrada'";
} elseif ($type === 'saida') {
    $entryWhere[] = "movement_type = 'saida'";
} elseif ($type === 'pendente') {
    $entryWhere[] = "movement_status = 'pending'";
}

if ($status !== 'all') {
    if ($status === 'sent') {
        $entryWhere[] = "movement_status = 'sent'";
    } else {
        $entryWhere[] = "movement_status = :status";
        $entryParams['status'] = $status;
    }
}

if ($projectId > 0) {
    $entryWhere[] = 'project_id = :project_id';
    $entryParams['project_id'] = $projectId;
}

if ($planId > 0) {
    $entryWhere[] = 'plan_id = :plan_id';
    $entryParams['plan_id'] = $planId;
}

$entryWhereSql = implode(' AND ', $entryWhere);

$stmt = $pdo->prepare("
    SELECT *
    FROM (
        SELECT
            CAST('entrada' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS movement_type,
            r.id AS movement_id,
            r.project_id,
            r.plan_id,
            CONVERT(p.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS project_name,
            CONVERT(p.slug USING utf8mb4) COLLATE utf8mb4_unicode_ci AS project_slug,
            CONVERT(pl.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS plan_name,
            CONVERT(COALESCE(pp.billing_cycle, pl.billing_cycle) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS billing_cycle,
            r.amount,
            CONVERT(r.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_status,
            CONVERT(r.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS movement_status,
            COALESCE(r.reviewed_at, r.created_at) AS movement_date,
            CONVERT(r.receipt_path USING utf8mb4) COLLATE utf8mb4_unicode_ci AS receipt_path,
            CONVERT(r.notes USING utf8mb4) COLLATE utf8mb4_unicode_ci AS notes,
            CAST('/web/admin/projects/upgrade_requests.php?status=all' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS action_url
        FROM plan_upgrade_requests r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN plans pl ON pl.id = r.plan_id
        LEFT JOIN plan_prices pp ON pp.id = r.plan_price_id

        UNION ALL

        SELECT
            CAST('saida' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS movement_type,
            rf.id AS movement_id,
            rf.project_id,
            rf.plan_id,
            CONVERT(p.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS project_name,
            CONVERT(p.slug USING utf8mb4) COLLATE utf8mb4_unicode_ci AS project_slug,
            CONVERT(pl.name USING utf8mb4) COLLATE utf8mb4_unicode_ci AS plan_name,
            CONVERT(COALESCE(pp.billing_cycle, pl.billing_cycle) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS billing_cycle,
            rf.amount,
            CONVERT(rf.status USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_status,
            CONVERT(CASE
                WHEN rf.status = 'approved' AND rf.sent_at IS NOT NULL THEN 'sent'
                ELSE rf.status
            END USING utf8mb4) COLLATE utf8mb4_unicode_ci AS movement_status,
            COALESCE(rf.sent_at, rf.reviewed_at, rf.requested_at) AS movement_date,
            CONVERT(rf.sent_receipt_path USING utf8mb4) COLLATE utf8mb4_unicode_ci AS receipt_path,
            CONVERT(rf.reason USING utf8mb4) COLLATE utf8mb4_unicode_ci AS notes,
            CAST('/web/admin/projects/refund_requests.php?status=all' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS action_url
        FROM plan_refund_requests rf
        INNER JOIN projects p ON p.id = rf.project_id
        INNER JOIN plans pl ON pl.id = rf.plan_id
        LEFT JOIN plan_prices pp ON pp.id = rf.plan_price_id
    ) movements
    WHERE {$entryWhereSql}
    ORDER BY movement_date DESC, movement_id DESC
    LIMIT 120
");
$stmt->execute($entryParams);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$netBalance = $approvedIncome - $sentRefunds;

$title = 'Financeiro';
ob_start();
?>

<div class="c-page c-finance-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Financeiro</h1>
            <p class="c-page-subtitle">Controle de entradas, reembolsos e assinaturas do core</p>
        </div>

        <div class="c-page-actions">
            <a href="/web/admin/financeiro/saldo.php" class="c-btn-secondary">Carteira</a>
            <a href="/web/admin/projects/upgrade_requests.php" class="c-btn-secondary">Histórico de upgrades</a>
            <a href="/web/admin/projects/refund_requests.php" class="c-btn-secondary">Reembolsos</a>
            <a href="/web/admin/plans/payment.php" class="c-btn-secondary">Pix</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-finance-grid">
            <a class="c-dashboard-card c-finance-priority-card <?= $pendingWalletRequests > 0 ? 'c-card--warning' : 'c-card--success' ?>" href="/web/admin/financeiro/saldo.php?status=pending">
                <div>
                    <h4>Solicitações de crédito</h4>
                    <div class="c-metric">
                        <?= $pendingWalletRequests ?>
                        <span class="c-badge <?= $pendingWalletRequests > 0 ? 'c-badge--warning' : 'c-badge--success' ?>">
                            <?= $pendingWalletRequests > 0 ? 'Analisar' : 'Tudo em dia' ?>
                        </span>
                    </div>
                    <small><?= moneyBr($pendingWalletAmount) ?></small>
                </div>
            </a>

            <a class="c-dashboard-card c-finance-priority-card" href="/web/admin/financeiro/saldo.php">
                <div>
                    <h4>Carteira central</h4>
                    <div class="c-metric"><?= moneyBr($totalWalletBalance) ?></div>
                    <small>Disponivel para upgrades instantaneos</small>
                </div>
            </a>

            <a class="c-dashboard-card c-finance-priority-card <?= $pendingUpgrades > 0 ? 'c-card--warning' : 'c-card--success' ?>" href="/web/admin/projects/upgrade_requests.php?status=pending">
                <div>
                    <h4>Upgrades legados</h4>
                    <div class="c-metric">
                        <?= $pendingUpgrades ?>
                        <span class="c-badge <?= $pendingUpgrades > 0 ? 'c-badge--warning' : 'c-badge--success' ?>">
                            <?= $pendingUpgrades > 0 ? 'Analisar' : 'Tudo em dia' ?>
                        </span>
                    </div>
                    <small><?= moneyBr($pendingUpgradeAmount) ?></small>
                </div>
            </a>

            <a class="c-dashboard-card c-finance-priority-card <?= $pendingRefunds > 0 ? 'c-card--warning' : 'c-card--success' ?>" href="/web/admin/projects/refund_requests.php?status=pending">
                <div>
                    <h4>Solicitações de reembolso</h4>
                    <div class="c-metric">
                        <?= $pendingRefunds ?>
                        <span class="c-badge <?= $pendingRefunds > 0 ? 'c-badge--warning' : 'c-badge--success' ?>">
                            <?= $pendingRefunds > 0 ? 'Analisar' : 'Tudo em dia' ?>
                        </span>
                    </div>
                    <small><?= moneyBr($pendingRefundAmount) ?></small>
                </div>
            </a>

            <div class="c-dashboard-card c-card--success">
                <h4>Entradas aprovadas</h4>
                <div class="c-metric"><?= moneyBr($approvedIncome) ?></div>
            </div>

            <div class="c-dashboard-card c-card--danger">
                <h4>Reembolsos enviados</h4>
                <div class="c-metric"><?= moneyBr($sentRefunds) ?></div>
            </div>

            <div class="c-dashboard-card c-card--finance">
                <h4>Saldo líquido</h4>
                <div class="c-metric"><?= moneyBr($netBalance) ?></div>
            </div>

            <div class="c-dashboard-card c-card--finance">
                <h4>MRR estimado</h4>
                <div class="c-metric"><?= moneyBr($mrr) ?></div>
            </div>

            <div class="c-dashboard-card c-card--finance">
                <h4>Receita anual</h4>
                <div class="c-metric"><?= moneyBr(($mrr * 12) + $annualRevenue) ?></div>
            </div>
        </div>

        <?php if ($approvedRefundsToSend > 0): ?>
            <div class="c-card c-finance-alert">
                <strong>Reembolsos aprovados a enviar:</strong>
                <?= moneyBr($approvedRefundsToSend) ?>
                <a href="/web/admin/projects/refund_requests.php?status=approved" class="c-btn-secondary btn-sm">Ver reembolsos</a>
            </div>
        <?php endif; ?>

        <div class="c-card">
            <form method="get" class="c-finance-filter">
                <div class="c-form-group">
                    <label>Início</label>
                    <input class="c-input" type="date" name="start" value="<?= htmlspecialchars($periodStart) ?>">
                </div>

                <div class="c-form-group">
                    <label>Fim</label>
                    <input class="c-input" type="date" name="end" value="<?= htmlspecialchars($periodEnd) ?>">
                </div>

                <div class="c-form-group">
                    <label>Tipo</label>
                    <select class="c-input" name="type">
                        <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Todos</option>
                        <option value="entrada" <?= $type === 'entrada' ? 'selected' : '' ?>>Entradas</option>
                        <option value="saida" <?= $type === 'saida' ? 'selected' : '' ?>>Saídas</option>
                        <option value="pendente" <?= $type === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select class="c-input" name="status">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Enviado</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Projeto</label>
                    <select class="c-input" name="project_id">
                        <option value="0">Todos</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int)$project['id'] ?>" <?= $projectId === (int)$project['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($project['name'] . ' (' . $project['slug'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Plano</label>
                    <select class="c-input" name="plan_id">
                        <option value="0">Todos</option>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= (int)$plan['id'] ?>" <?= $planId === (int)$plan['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($plan['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button class="c-btn-secondary">Filtrar</button>
            </form>
        </div>

        <div class="c-table-wrapper">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Projeto</th>
                        <th>Plano</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Comprovante</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <?php
                            $isIncome = $entry['movement_type'] === 'entrada';
                            $movementStatus = (string)$entry['movement_status'];
                            $badgeClass = match ($movementStatus) {
                                'approved', 'sent' => 'c-badge--success',
                                'rejected' => 'c-badge--danger',
                                default => 'c-badge--warning',
                            };
                        ?>
                        <tr>
                            <td>
                                <span class="c-badge <?= $isIncome ? 'c-badge--success' : 'c-badge--danger' ?>">
                                    <?= $isIncome ? 'Entrada' : 'Saída' ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($entry['project_name']) ?></strong>
                                <span><?= htmlspecialchars($entry['project_slug']) ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($entry['plan_name']) ?>
                                <span><?= htmlspecialchars($entry['billing_cycle'] === 'annual' ? 'Anual' : ($entry['billing_cycle'] === 'monthly' ? 'Mensal' : 'Gratis')) ?></span>
                            </td>
                            <td class="<?= $isIncome ? 'c-finance-income' : 'c-finance-outcome' ?>">
                                <?= $isIncome ? '+' : '-' ?> <?= moneyBr((float)$entry['amount']) ?>
                            </td>
                            <td>
                                <span class="c-badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars(match ($movementStatus) {
                                        'approved' => 'Aprovado',
                                        'sent' => 'Enviado',
                                        'rejected' => 'Rejeitado',
                                        default => 'Pendente',
                                    }) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$entry['movement_date']))) ?></td>
                            <td>
                                <?php if (!empty($entry['receipt_path'])): ?>
                                    <a class="c-btn-secondary btn-sm" href="/<?= htmlspecialchars((string)$entry['receipt_path']) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a class="c-btn-secondary btn-sm" href="<?= htmlspecialchars((string)$entry['action_url']) ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($entries)): ?>
                        <tr><td colspan="8">Nenhum movimento encontrado para os filtros selecionados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
body:has(.c-finance-page) .c-layout {
    grid-template-columns: 220px minmax(0, 1fr);
}

.c-finance-page .c-finance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px;
}

.c-finance-page .c-dashboard-card {
    min-height: 78px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 12px 14px;
}

.c-finance-page .c-finance-priority-card {
    text-decoration: none;
    min-height: 96px;
    justify-content: space-between;
    border-color: rgba(245, 158, 11, .55);
    box-shadow: 0 0 0 1px rgba(245, 158, 11, .08);
}

.c-finance-page .c-finance-priority-card small {
    display: block;
    color: var(--text-secondary);
    font-weight: 800;
    margin-top: 6px;
}

.c-finance-page .c-dashboard-card h4 {
    margin: 0 0 6px;
    font-size: 12px;
}

.c-finance-page .c-metric {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 19px;
}

.c-finance-filter {
    display: grid;
    grid-template-columns: repeat(6, minmax(120px, 1fr)) auto;
    gap: 10px;
    align-items: end;
}

.c-finance-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    border-left: 4px solid #f59e0b;
}

.c-finance-income {
    color: #22c55e;
    font-weight: 800;
}

.c-finance-outcome {
    color: #ef4444;
    font-weight: 800;
}

.c-finance-page .c-table td span:not(.c-badge) {
    display: block;
    color: var(--text-secondary);
    margin-top: 3px;
}

@media (max-width: 1100px) {
    .c-finance-filter {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .c-finance-page .c-finance-grid {
        grid-template-columns: 1fr 1fr;
    }

    .c-finance-page .c-page-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
        gap: 8px;
    }

    .c-finance-page .c-page-actions .c-btn-secondary {
        width: 100%;
        justify-content: center;
        white-space: normal;
        min-height: 40px;
    }

    .c-finance-filter {
        grid-template-columns: 1fr 1fr;
    }

    .c-finance-filter button {
        grid-column: 1 / -1;
    }
}

@media (max-width: 520px) {
    .c-finance-filter {
        grid-template-columns: 1fr;
    }

    .c-finance-filter .c-input {
        width: 100%;
    }
}

@media (max-width: 390px) {
    .c-finance-page .c-finance-grid,
    .c-finance-page .c-page-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Financeiro</h3>
    <p>Entradas vêm de upgrades aprovados. Saídas vêm de reembolsos enviados.</p>
    <p>Use os filtros para conferir período, projeto, plano e status.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
