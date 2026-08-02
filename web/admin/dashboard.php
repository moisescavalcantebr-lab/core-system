<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

function dashboardMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function dashboardQueryInt(PDO $pdo, string $sql): int
{
    return (int)$pdo->query($sql)->fetchColumn();
}

function dashboardQueryFloat(PDO $pdo, string $sql): float
{
    return (float)$pdo->query($sql)->fetchColumn();
}

function dashboardIcon(string $name): string
{
    $icons = [
        'requests' => '<path d="M4 8h16v10H4Z"/><path d="m8 8 2-3h4l2 3"/><path d="M8 13h8"/>',
        'projects' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 5V3h8v2"/><path d="M3 11h18"/>',
        'active' => '<path d="m4 14 4-4 4 4 8-8"/><path d="M14 6h6v6"/>',
        'pending' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/>',
        'suspended' => '<circle cx="12" cy="12" r="8"/><path d="M9 9l6 6"/><path d="m15 9-6 6"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 11h5v4h-5a2 2 0 0 1 0-4Z"/><path d="M6 6V4h12v2"/>',
        'refund' => '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 5v7h-7"/><path d="M12 8v8"/><path d="M9 11h5a2 2 0 0 1 0 4H9"/>',
        'upgrade' => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/><path d="M5 21h14"/>',
        'plans' => '<path d="m12 3 8 4.5-8 4.5-8-4.5Z"/><path d="m4 12 8 4.5 8-4.5"/><path d="m4 16.5 8 4.5 8-4.5"/>',
        'free' => '<path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7"/><path d="M2 7h20v5H2Z"/><path d="M12 7v14"/><path d="M12 7H8.5a2.5 2.5 0 1 1 2.2-3.7L12 7Z"/><path d="M12 7h3.5a2.5 2.5 0 1 0-2.2-3.7L12 7Z"/>',
        'finance' => '<path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/>',
        'modules' => '<path d="m12 3 8 4.5-8 4.5-8-4.5Z"/><path d="m4 12 8 4.5 8-4.5"/><path d="m4 16.5 8 4.5 8-4.5"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'growth' => '<path d="M4 19V5"/><path d="M4 19h16"/><path d="m7 15 4-4 3 3 5-7"/>',
        'leads' => '<path d="M4 6h16v12H4Z"/><path d="m4 7 8 6 8-6"/>',
        'clients' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
    ];

    $paths = $icons[$name] ?? '<circle cx="12" cy="12" r="8"/>';

    return '<span class="c-dash-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg></span>';
}

function dashboardSparkline(array $values, string $color = '#3b82f6'): string
{
    if (!$values) {
        $values = [0, 0, 0, 0, 0, 0];
    }

    $max = max($values);
    $min = min($values);
    $range = max(1, $max - $min);
    $count = count($values);
    $points = [];

    foreach ($values as $index => $value) {
        $x = $count === 1 ? 0 : ($index * (100 / ($count - 1)));
        $y = 34 - ((($value - $min) / $range) * 28);
        $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    }

    $escapedColor = htmlspecialchars($color);
    $pointsString = implode(' ', $points);

    return '<svg class="c-sparkline" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">'
        . '<polyline points="' . $pointsString . '" fill="none" stroke="' . $escapedColor . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}

function dashboardMonthSeries(PDO $pdo, string $sql): array
{
    $start = new DateTimeImmutable('first day of this month');
    $months = [];

    for ($i = 5; $i >= 0; $i--) {
        $month = $start->modify('-' . $i . ' months');
        $months[$month->format('Y-m')] = [
            'label' => $month->format('M'),
            'value' => 0.0,
        ];
    }

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $key = (string)($row['ym'] ?? '');

        if (isset($months[$key])) {
            $months[$key]['value'] = (float)($row['total'] ?? 0);
        }
    }

    return $months;
}

/*
|--------------------------------------------------------------------------
| MÉTRICAS
|--------------------------------------------------------------------------
*/

$total = dashboardQueryInt($pdo, "SELECT COUNT(*) FROM projects");

$active = dashboardQueryInt($pdo, "
    SELECT COUNT(*) FROM projects
    WHERE status = 'active'
    AND billing_status = 'active'
");

$pending = dashboardQueryInt($pdo, "
    SELECT COUNT(*) FROM projects
    WHERE status = 'pending'
");

$suspended = dashboardQueryInt($pdo, "
    SELECT COUNT(*) FROM projects
    WHERE billing_status = 'suspended'
");

$freeProjects = dashboardQueryInt($pdo, "
SELECT COUNT(*)
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
WHERE (pp.id IS NOT NULL AND pp.billing_cycle + 0 = 1)
   OR (pp.id IS NULL AND pl.billing_cycle + 0 = 1)
");

$paidProjects = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM projects p
    JOIN plans pl ON pl.id = p.plan_id
    LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
    WHERE (pp.id IS NOT NULL AND pp.billing_cycle + 0 != 1)
       OR (pp.id IS NULL AND pl.billing_cycle + 0 != 1)
");

$startProjects = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM projects p
    JOIN plans pl ON pl.id = p.plan_id
    WHERE LOWER(pl.name) LIKE '%start%'
");

$pendingRequests = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM leads
    WHERE implementation_status = 'ready'
");

$totalLeads = dashboardQueryInt($pdo, "SELECT COUNT(*) FROM leads");

$projectsCreatedThisMonth = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM projects
    WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
");

$activeClients = dashboardQueryInt($pdo, "
    SELECT COUNT(DISTINCT owner_email)
    FROM projects
    WHERE status = 'active'
");

$conversionRate = $totalLeads > 0 ? round(($total / $totalLeads) * 100) : 0;

$totalPlans = dashboardQueryInt($pdo, "SELECT COUNT(*) FROM plans");

$activePlans = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM plans
    WHERE status = 1
");

$pendingUpgradeRequests = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM plan_upgrade_requests
    WHERE status = 'pending'
");

$pendingWalletRequests = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM project_wallet_requests
    WHERE status = 'pending'
");

$totalWalletBalance = dashboardQueryFloat($pdo, "
    SELECT COALESCE(SUM(
        CASE
            WHEN movement_type = 'credit' THEN amount
            WHEN movement_type = 'debit' THEN -amount
            ELSE 0
        END
    ), 0)
    FROM project_wallet_movements
    WHERE status = 'applied'
");

$approvedWalletCredits = dashboardQueryFloat($pdo, "
    SELECT COALESCE(SUM(amount), 0)
    FROM project_wallet_movements
    WHERE status = 'applied'
    AND movement_type = 'credit'
");

$pendingRefundRequests = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM plan_refund_requests
    WHERE status = 'pending'
");

$baseReadyCount = dashboardQueryInt($pdo, "
    SELECT COUNT(*)
    FROM bases
    WHERE status = 1
");

$totalModules = 0;
$mainModules = 0;
$addonModules = 0;
$moduleConnections = 0;
$basesWithModules = [];

if (is_dir(ROOT_PATH . '/modules')) {
    foreach (scandir(ROOT_PATH . '/modules') as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $manifestPath = ROOT_PATH . '/modules/' . $moduleSlug . '/module.json';

        if (!is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
        $totalModules++;

        if (($manifest['kind'] ?? '') === 'addon') {
            $addonModules++;
        } else {
            $mainModules++;
        }

        if (is_dir(BASES_PATH)) {
            foreach (scandir(BASES_PATH) as $baseSlug) {
                if ($baseSlug === '.' || $baseSlug === '..' || $baseSlug === 'base') {
                    continue;
                }

                if (is_dir(BASES_PATH . '/' . $baseSlug . '/modules/' . $moduleSlug)) {
                    $moduleConnections++;
                    $basesWithModules[$baseSlug] = true;
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| FINANCEIRO
|--------------------------------------------------------------------------
*/

$mrr = dashboardQueryFloat($pdo, "
SELECT COALESCE(SUM(COALESCE(pp.price, pl.price)),0)
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
WHERE p.status='active'
AND p.billing_status='active'
AND (
    (pp.id IS NOT NULL AND pp.billing_cycle + 0 = 2)
    OR (pp.id IS NULL AND pl.billing_cycle + 0 = 2)
)
");

$arr = dashboardQueryFloat($pdo, "
SELECT COALESCE(SUM(COALESCE(pp.price, pl.price)),0)
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
WHERE p.status='active'
AND p.billing_status='active'
AND (
    (pp.id IS NOT NULL AND pp.billing_cycle + 0 = 3)
    OR (pp.id IS NULL AND pl.billing_cycle + 0 = 3)
)
");

$annual = ($mrr * 12) + $arr;

$projectSeries = dashboardMonthSeries($pdo, "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
    FROM projects
    WHERE created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY ym
");

$revenueSeries = dashboardMonthSeries($pdo, "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(amount), 0) AS total
    FROM project_wallet_movements
    WHERE status = 'applied'
    AND movement_type = 'debit'
    AND source = 'upgrade'
    AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY ym
");

$projectSpark = dashboardSparkline(array_column($projectSeries, 'value'), '#3b82f6');
$revenueSpark = dashboardSparkline(array_column($revenueSeries, 'value'), '#8b5cf6');

$adminName = trim((string)($_SESSION['core_user']['name'] ?? 'Administrador'));
$firstName = $adminName !== '' ? explode(' ', $adminName)[0] : 'Administrador';

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Dashboard';

ob_start();
?>

<div class="c-page c-dashboard-page">

    <div class="c-page-header c-dashboard-hero">
        <div>
            <h1>Olá, <?= htmlspecialchars($firstName) ?>!</h1>
            <p>Aqui está o resumo geral do Core e dos projetos.</p>
        </div>

        <div class="c-page-actions c-dashboard-hero-actions"></div>
    </div>

    <div class="c-page-content">

        <section class="c-dashboard-section">
            <div class="c-dashboard-summary-grid">
                <a class="c-dashboard-card c-dashboard-stat <?= $pendingRequests > 0 ? 'c-card--warning' : 'c-card--success' ?>"
                   href="/web/admin/projects/requests.php">
                    <?= dashboardIcon('requests') ?>
                    <div class="c-dashboard-stat-body">
                        <span>Solicitações</span>
                        <strong><?= $pendingRequests ?></strong>
                        <small class="<?= $pendingRequests > 0 ? 'is-warning' : 'is-success' ?>">
                            <?= $pendingRequests > 0 ? 'Analisar' : 'Tudo em dia' ?>
                        </small>
                    </div>
                    <?= dashboardSparkline([$pendingRequests, $pendingRequests + 1, $pendingRequests, $pendingRequests + 2, $pendingRequests + 1, $pendingRequests], '#8b5cf6') ?>
                </a>

                <a class="c-dashboard-card c-dashboard-stat c-card--info"
                   href="/web/admin/projects/index.php">
                    <?= dashboardIcon('projects') ?>
                    <div class="c-dashboard-stat-body">
                        <span>Total de Projetos</span>
                        <strong><?= $total ?></strong>
                        <small><?= $projectsCreatedThisMonth ?> neste mês</small>
                    </div>
                    <?= $projectSpark ?>
                </a>

                <a class="c-dashboard-card c-dashboard-stat c-card--success"
                   href="/web/admin/projects/index.php?status=active">
                    <?= dashboardIcon('active') ?>
                    <div class="c-dashboard-stat-body">
                        <span>Ativos</span>
                        <strong><?= $active ?></strong>
                        <small>Projetos operando</small>
                    </div>
                </a>

                <a class="c-dashboard-card c-dashboard-stat c-card--warning"
                   href="/web/admin/projects/index.php?status=pending">
                    <?= dashboardIcon('pending') ?>
                    <div class="c-dashboard-stat-body">
                        <span>Pendentes</span>
                        <strong><?= $pending ?></strong>
                        <small>Aguardando ativação</small>
                    </div>
                </a>

                <a class="c-dashboard-card c-dashboard-stat c-card--danger"
                   href="/web/admin/projects/index.php?billing=suspended">
                    <?= dashboardIcon('suspended') ?>
                    <div class="c-dashboard-stat-body">
                        <span>Suspensos</span>
                        <strong><?= $suspended ?></strong>
                        <small>Billing suspenso</small>
                    </div>
                </a>
            </div>
        </section>

        <section class="c-dashboard-section">
            <h2>Planos</h2>

            <div class="c-dashboard-plan-grid">
                <a class="c-dashboard-mini-card <?= $pendingWalletRequests > 0 ? 'c-card--warning' : 'c-card--success' ?>"
                   href="/web/admin/financeiro/saldo.php?status=pending">
                    <span>Solicitações de crédito</span>
                    <strong><?= $pendingWalletRequests ?></strong>
                    <small><?= $pendingWalletRequests > 0 ? 'Analisar' : 'Tudo em dia' ?></small>
                    <?= dashboardIcon('requests') ?>
                </a>

                <a class="c-dashboard-mini-card c-card--finance"
                   href="/web/admin/financeiro/saldo.php">
                    <span>Carteira central</span>
                    <strong><?= dashboardMoney($totalWalletBalance) ?></strong>
                    <small>Crédito disponível</small>
                    <?= dashboardIcon('wallet') ?>
                </a>

                <a class="c-dashboard-mini-card <?= $pendingRefundRequests > 0 ? 'c-card--warning' : 'c-card--success' ?>"
                   href="/web/admin/projects/refund_requests.php">
                    <span>Solicitações de reembolso</span>
                    <strong><?= $pendingRefundRequests ?></strong>
                    <small><?= $pendingRefundRequests > 0 ? 'Analisar' : 'Tudo em dia' ?></small>
                    <?= dashboardIcon('refund') ?>
                </a>

                <a class="c-dashboard-mini-card c-card--neutral"
                   href="/web/admin/projects/upgrade_requests.php">
                    <span>Upgrades legados</span>
                    <strong><?= $pendingUpgradeRequests ?></strong>
                    <small>Pendentes</small>
                    <?= dashboardIcon('upgrade') ?>
                </a>

                <a class="c-dashboard-mini-card c-card--info"
                   href="/web/admin/plans/index.php">
                    <span>Total de planos</span>
                    <strong><?= $totalPlans ?></strong>
                    <small><?= $activePlans ?> ativos</small>
                    <?= dashboardIcon('plans') ?>
                </a>

                <a class="c-dashboard-mini-card c-card--success"
                   href="/web/admin/projects/index.php">
                    <span>Projetos pagos</span>
                    <strong><?= $paidProjects ?></strong>
                    <small><?= $freeProjects ?> gratuitos</small>
                    <?= dashboardIcon('free') ?>
                </a>
            </div>
        </section>

        <section class="c-dashboard-split">
            <div class="c-dashboard-panel">
                <div class="c-dashboard-panel-header">
                    <h2>Financeiro</h2>
                </div>

                <div class="c-dashboard-finance-grid">
                    <div class="c-dashboard-metric-card">
                        <?= dashboardIcon('finance') ?>
                        <span>MRR</span>
                        <strong><?= dashboardMoney($mrr) ?></strong>
                        <small>Receita mensal ativa</small>
                    </div>

                    <div class="c-dashboard-metric-card">
                        <?= dashboardIcon('finance') ?>
                        <span>ARR</span>
                        <strong><?= dashboardMoney($arr) ?></strong>
                        <small>Receita anual ativa</small>
                    </div>

                    <div class="c-dashboard-metric-card">
                        <?= dashboardIcon('wallet') ?>
                        <span>Receita anual</span>
                        <strong><?= dashboardMoney($annual) ?></strong>
                        <small>MRR x 12 + anual</small>
                    </div>
                </div>
            </div>

            <div class="c-dashboard-panel">
                <div class="c-dashboard-panel-header">
                    <h2>Receita nos últimos 6 meses</h2>
                </div>

                <div class="c-dashboard-chart">
                    <div class="c-dashboard-chart-area">
                        <?= $revenueSpark ?>
                    </div>
                    <div class="c-dashboard-chart-labels">
                        <?php foreach ($revenueSeries as $month): ?>
                            <span><?= htmlspecialchars($month['label']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="c-dashboard-split c-dashboard-split--modules">
            <div class="c-dashboard-panel">
                <div class="c-dashboard-panel-header">
                    <h2>Módulos</h2>
                </div>

                <div class="c-dashboard-module-row">
                    <div class="c-dashboard-module-item">
                        <?= dashboardIcon('modules') ?>
                        <div>
                            <span>Total de módulos</span>
                            <strong><?= $totalModules ?></strong>
                            <small><?= $mainModules ?> principais, <?= $addonModules ?> addons</small>
                        </div>
                    </div>

                    <div class="c-dashboard-module-item">
                        <?= dashboardIcon('database') ?>
                        <div>
                            <span>Instalações em bases</span>
                            <strong><?= $moduleConnections ?></strong>
                            <small><?= count($basesWithModules) ?> bases com módulos</small>
                        </div>
                    </div>

                    <div class="c-dashboard-module-item">
                        <?= dashboardIcon('active') ?>
                        <div>
                            <span>Bases prontas</span>
                            <strong><?= $baseReadyCount ?></strong>
                            <small>Disponíveis no Core</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="c-dashboard-panel">
                <div class="c-dashboard-panel-header">
                    <h2>Crescimento</h2>
                </div>

                <div class="c-dashboard-growth-grid">
                    <div>
                        <?= dashboardIcon('leads') ?>
                        <span>Leads</span>
                        <strong><?= $totalLeads ?></strong>
                    </div>
                    <div>
                        <?= dashboardIcon('projects') ?>
                        <span>Projetos criados</span>
                        <strong><?= $total ?></strong>
                    </div>
                    <div>
                        <?= dashboardIcon('growth') ?>
                        <span>Conversão</span>
                        <strong><?= $conversionRate ?>%</strong>
                    </div>
                    <div>
                        <?= dashboardIcon('clients') ?>
                        <span>Clientes ativos</span>
                        <strong><?= $activeClients ?></strong>
                    </div>
                </div>
            </div>
        </section>

    </div>

</div>

<style>
body:has(.c-dashboard-page) .c-layout {
    grid-template-columns: 220px minmax(0, 1fr);
}

.c-dashboard-page {
    gap: 18px;
}

.c-dashboard-page a {
    color: inherit;
    text-decoration: none;
}

.c-dashboard-hero {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    padding: 2px 0 4px;
}

.c-dashboard-hero h1 {
    margin: 0 0 8px;
    font-size: 24px;
    line-height: 1.1;
}

.c-dashboard-hero p,
.c-dashboard-section h2,
.c-dashboard-panel h2 {
    margin: 0;
}

.c-dashboard-hero p {
    color: var(--text-secondary);
}

.c-dashboard-hero-actions {
    align-items: center;
    justify-content: flex-end;
}

.c-dashboard-section,
.c-dashboard-split {
    width: 100%;
}

.c-dashboard-section h2,
.c-dashboard-panel h2 {
    font-size: 16px;
}

.c-dashboard-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    gap: 12px;
}

.c-dashboard-stat {
    min-height: 104px;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    grid-template-rows: 1fr auto;
    align-items: center;
    gap: 10px 12px;
    padding: 14px;
    overflow: hidden;
}

.c-dashboard-stat .c-sparkline {
    grid-column: 1 / -1;
    height: 26px;
    opacity: .9;
}

.c-dashboard-stat-body {
    min-width: 0;
}

.c-dashboard-stat-body span,
.c-dashboard-mini-card span,
.c-dashboard-metric-card span,
.c-dashboard-module-item span,
.c-dashboard-growth-grid span {
    display: block;
    color: var(--text-secondary);
    font-size: 12px;
    line-height: 1.2;
}

.c-dashboard-stat-body strong,
.c-dashboard-mini-card strong,
.c-dashboard-metric-card strong,
.c-dashboard-module-item strong,
.c-dashboard-growth-grid strong {
    display: block;
    margin-top: 4px;
    font-size: 22px;
    line-height: 1.05;
}

.c-dashboard-stat-body small,
.c-dashboard-mini-card small,
.c-dashboard-metric-card small,
.c-dashboard-module-item small {
    display: block;
    margin-top: 7px;
    color: var(--text-secondary);
    font-size: 11px;
    line-height: 1.2;
}

.c-dashboard-stat-body small.is-success,
.c-dashboard-mini-card.c-card--success small {
    color: #22c55e;
}

.c-dashboard-stat-body small.is-warning,
.c-dashboard-mini-card.c-card--warning small {
    color: #f59e0b;
}

.c-dash-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color) 16%, transparent);
    flex: 0 0 auto;
}

.c-dash-icon svg {
    width: 21px;
    height: 21px;
}

.c-card--success .c-dash-icon {
    color: #22c55e;
    background: rgba(34, 197, 94, .13);
}

.c-card--warning .c-dash-icon {
    color: #f59e0b;
    background: rgba(245, 158, 11, .13);
}

.c-card--danger .c-dash-icon {
    color: #ef4444;
    background: rgba(239, 68, 68, .13);
}

.c-card--info .c-dash-icon {
    color: #3b82f6;
    background: rgba(59, 130, 246, .13);
}

.c-card--finance .c-dash-icon {
    color: #8b5cf6;
    background: rgba(139, 92, 246, .13);
}

.c-dashboard-plan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    margin-top: 12px;
}

.c-dashboard-mini-card {
    position: relative;
    min-height: 88px;
    padding: 14px 58px 14px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
}

.c-dashboard-mini-card .c-dash-icon {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
}

.c-dashboard-mini-card .c-dash-icon svg {
    width: 19px;
    height: 19px;
}

.c-dashboard-split {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(320px, .85fr);
    gap: 10px;
}

.c-dashboard-split--modules {
    grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
}

.c-dashboard-panel {
    min-width: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 14px;
}

.c-dashboard-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 14px;
}

.c-dashboard-finance-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.c-dashboard-metric-card {
    min-height: 126px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 5px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 14px;
}

.c-dashboard-metric-card .c-dash-icon {
    margin-bottom: 6px;
}

.c-dashboard-chart {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.c-dashboard-chart-area {
    min-height: 136px;
    display: flex;
    align-items: stretch;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    background:
        linear-gradient(to right, color-mix(in srgb, var(--border-color) 34%, transparent) 1px, transparent 1px),
        linear-gradient(to bottom, color-mix(in srgb, var(--border-color) 34%, transparent) 1px, transparent 1px);
    background-size: 20% 50%;
}

.c-dashboard-chart-area .c-sparkline {
    width: 100%;
    height: 136px;
}

.c-dashboard-chart-labels {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    color: var(--text-secondary);
    font-size: 11px;
    text-align: center;
}

.c-dashboard-module-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.c-dashboard-module-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 12px;
    align-items: center;
    min-height: 96px;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 14px;
}

.c-dashboard-growth-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.c-dashboard-growth-grid > div {
    min-height: 86px;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 8px 10px;
    align-items: center;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 12px;
}

.c-dashboard-growth-grid > div span,
.c-dashboard-growth-grid > div strong {
    grid-column: 2;
}

.c-dashboard-growth-grid .c-dash-icon {
    grid-row: 1 / span 2;
    width: 34px;
    height: 34px;
}

.c-sparkline {
    width: 100%;
}

@media (max-width: 1180px) {
    .c-dashboard-split,
    .c-dashboard-split--modules {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .c-dashboard-hero h1 {
        font-size: 21px;
    }

    .c-dashboard-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-dashboard-stat {
        grid-template-columns: 1fr;
        min-height: 118px;
    }

    .c-dashboard-stat .c-dash-icon {
        width: 36px;
        height: 36px;
    }

    .c-dashboard-finance-grid,
    .c-dashboard-module-row,
    .c-dashboard-growth-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 430px) {
    .c-dashboard-summary-grid,
    .c-dashboard-plan-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Informações</h3>
    <p>Dashboard operacional do core.</p>
    <p>Os indicadores usam dados reais de projetos, planos, carteira e módulos.</p>
</div>

<br>

<div class="c-card">
    <h3>Avisos</h3>
    <p>Quando novos módulos enviarem métricas, a dashboard pode receber painéis específicos sem mudar a base neutra.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
