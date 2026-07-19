<?php
declare(strict_types=1);

require_once PROJECT_PATH . '/web/admin/financeiro/helpers.php';

global $pdo;

if (!$pdo instanceof PDO) {
    return [
        'type' => 'panel',
        'module' => 'financeiro',
        'group' => 'financeiro',
        'group_label' => 'Financeiro',
        'order' => 20,
        'html' => '<section class="dash-module dash-module--finance"><div class="dash-module-head"><div><h3>Resumo financeiro</h3><p>Banco de dados indisponivel para este painel.</p></div></div></section>',
    ];
}

$summary = [
    'income' => 0.0,
    'expense' => 0.0,
    'pending_income' => 0.0,
    'pending_expense' => 0.0,
];

$row = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'paid' THEN amount ELSE 0 END), 0) AS income_total,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END), 0) AS expense_total,
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount ELSE 0 END), 0) AS pending_income_total,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'pending' THEN amount ELSE 0 END), 0) AS pending_expense_total
    FROM finance_entries
    WHERE COALESCE(source, 'manual') NOT IN ('balance_deposit', 'balance_usage')
")->fetch(PDO::FETCH_ASSOC) ?: [];

$summary['income'] = (float)($row['income_total'] ?? 0);
$summary['expense'] = (float)($row['expense_total'] ?? 0);
$summary['pending_income'] = (float)($row['pending_income_total'] ?? 0);
$summary['pending_expense'] = (float)($row['pending_expense_total'] ?? 0);

$balance = $summary['income'] - $summary['expense'];
$pendingBalance = $summary['pending_income'] - $summary['pending_expense'];
$advancedCategories = financeAdvancedCategoriesEnabled();

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $months[$key] = [
        'label' => date('m/Y', strtotime($key . '-01')),
        'income' => 0.0,
        'expense' => 0.0,
    ];
}

$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(COALESCE(paid_at, due_date, DATE(created_at)), '%Y-%m') AS month_key,
        type,
        COALESCE(SUM(amount), 0) AS total
    FROM finance_entries
    WHERE status = 'paid'
      AND COALESCE(source, 'manual') NOT IN ('balance_deposit', 'balance_usage')
      AND COALESCE(paid_at, due_date, DATE(created_at)) >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
    GROUP BY month_key, type
    ORDER BY month_key ASC
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $monthRow) {
    $key = (string)$monthRow['month_key'];
    $type = (string)$monthRow['type'];

    if (isset($months[$key]) && isset($months[$key][$type])) {
        $months[$key][$type] = (float)$monthRow['total'];
    }
}

$maxMonthly = 1.0;
foreach ($months as $monthData) {
    $maxMonthly = max($maxMonthly, $monthData['income'], $monthData['expense']);
}

$categoryNameExpression = $advancedCategories
    ? "COALESCE(CONCAT(pc.name, ' > ', c.name), c.name, 'Sem categoria')"
    : "COALESCE(pc.name, c.name, 'Sem categoria')";

$categories = $pdo->query("
    SELECT {$categoryNameExpression} AS category_name, COALESCE(SUM(e.amount), 0) AS total
    FROM finance_entries e
    LEFT JOIN finance_categories c ON c.id = e.category_id
    LEFT JOIN finance_categories pc ON pc.id = c.parent_id
    WHERE e.status = 'paid'
      AND e.type = 'expense'
      AND COALESCE(e.source, 'manual') NOT IN ('balance_deposit', 'balance_usage')
    GROUP BY category_name
    ORDER BY total DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

$subcategoryPies = [
    'income' => [
        'title' => 'Entradas',
        'empty' => 'Sem entradas por subcategoria.',
        'items' => [],
        'total' => 0.0,
        'stops' => [],
        'colors' => ['#22c55e', '#3b82f6', '#14b8a6', '#84cc16', '#06b6d4', '#a3e635'],
    ],
    'expense' => [
        'title' => 'Saidas',
        'empty' => 'Sem saidas por subcategoria.',
        'items' => [],
        'total' => 0.0,
        'stops' => [],
        'colors' => ['#ef4444', '#f59e0b', '#8b5cf6', '#ec4899', '#f97316', '#64748b'],
    ],
];

if ($advancedCategories) {
    $subcategoryRows = $pdo->query("
        SELECT
            e.type,
            c.name AS subcategory_name,
            COALESCE(pc.name, 'Sem categoria') AS parent_name,
            COALESCE(SUM(e.amount), 0) AS total
        FROM finance_entries e
        INNER JOIN finance_categories c ON c.id = e.category_id
        LEFT JOIN finance_categories pc ON pc.id = c.parent_id
        WHERE e.status = 'paid'
          AND e.type IN ('income', 'expense')
          AND c.parent_id IS NOT NULL
          AND COALESCE(e.source, 'manual') NOT IN ('balance_deposit', 'balance_usage')
        GROUP BY e.type, c.id, c.name, pc.name
        ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($subcategoryRows as $subcategoryRow) {
        $rowType = (string)($subcategoryRow['type'] ?? '');

        if (!isset($subcategoryPies[$rowType]) || count($subcategoryPies[$rowType]['items']) >= 6) {
            continue;
        }

        $subcategoryPies[$rowType]['items'][] = $subcategoryRow;
        $subcategoryPies[$rowType]['total'] += (float)$subcategoryRow['total'];
    }
}

$maxCategory = 1.0;
foreach ($categories as $category) {
    $maxCategory = max($maxCategory, (float)$category['total']);
}

foreach ($subcategoryPies as $pieType => $pieData) {
    $pieStart = 0.0;
    $pieColors = $pieData['colors'];

    if ($pieData['total'] <= 0) {
        continue;
    }

    foreach ($pieData['items'] as $index => $subcategory) {
        $percent = ((float)$subcategory['total'] / (float)$pieData['total']) * 100;
        $pieEnd = $pieStart + $percent;
        $color = $pieColors[$index % count($pieColors)];
        $subcategoryPies[$pieType]['stops'][] = $color . ' ' . round($pieStart, 2) . '% ' . round($pieEnd, 2) . '%';
        $pieStart = $pieEnd;
    }
}

function dashFinanceSparklinePoints(array $values, int $width = 210, int $height = 56): string
{
    if (count($values) === 0) {
        return '';
    }

    $min = min($values);
    $max = max($values);
    $range = max(1.0, (float)$max - (float)$min);
    $step = count($values) > 1 ? $width / (count($values) - 1) : 0;
    $points = [];

    foreach (array_values($values) as $index => $value) {
        $x = round($index * $step, 2);
        $y = round($height - ((((float)$value - (float)$min) / $range) * ($height - 6)) - 3, 2);
        $points[] = $x . ',' . $y;
    }

    return implode(' ', $points);
}

$balanceTrend = [];
$incomeTrend = [];
$expenseTrend = [];
$runningBalance = 0.0;

foreach ($months as $month) {
    $runningBalance += (float)$month['income'] - (float)$month['expense'];
    $balanceTrend[] = $runningBalance;
    $incomeTrend[] = (float)$month['income'];
    $expenseTrend[] = (float)$month['expense'];
}

$balanceSparkline = dashFinanceSparklinePoints($balanceTrend);
$incomeSparkline = dashFinanceSparklinePoints($incomeTrend, 120, 42);
$expenseSparkline = dashFinanceSparklinePoints($expenseTrend, 120, 42);

ob_start();
?>
<section class="dash-module dash-module--finance">
    <div class="dash-module-head">
        <div>
            <h3>Resumo financeiro</h3>
            <p>Saldo, movimento pago e pendencias do caixa.</p>
        </div>
        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/index.php">Abrir</a>
    </div>

    <div class="dash-finance-layout">
        <div class="dash-metric-card dash-metric-card--primary">
            <span>Saldo atual</span>
            <strong><?= financeMoney($balance) ?></strong>
            <small>Pendencias: <?= financeMoney($pendingBalance) ?></small>
            <svg class="dash-sparkline dash-sparkline--balance" viewBox="0 0 210 56" aria-hidden="true">
                <polyline points="<?= htmlspecialchars($balanceSparkline) ?>"></polyline>
            </svg>
        </div>

        <div class="dash-pair-card">
            <div>
                <span>Entradas pagas</span>
                <strong class="dash-good"><?= financeMoney($summary['income']) ?></strong>
                <svg class="dash-sparkline dash-sparkline--income" viewBox="0 0 120 42" aria-hidden="true">
                    <polyline points="<?= htmlspecialchars($incomeSparkline) ?>"></polyline>
                </svg>
            </div>
            <div>
                <span>Saidas pagas</span>
                <strong class="dash-bad"><?= financeMoney($summary['expense']) ?></strong>
                <svg class="dash-sparkline dash-sparkline--expense" viewBox="0 0 120 42" aria-hidden="true">
                    <polyline points="<?= htmlspecialchars($expenseSparkline) ?>"></polyline>
                </svg>
            </div>
        </div>

        <div class="dash-chart-card">
            <h4>Ultimos 6 meses</h4>
            <div class="dash-bars">
                <?php foreach ($months as $month): ?>
                    <div class="dash-bar-group">
                        <div class="dash-bar-pair">
                            <span class="dash-bar dash-bar--income" style="height:<?= max(4, (int)round(($month['income'] / $maxMonthly) * 84)) ?>px;"></span>
                            <span class="dash-bar dash-bar--expense" style="height:<?= max(4, (int)round(($month['expense'] / $maxMonthly) * 84)) ?>px;"></span>
                        </div>
                        <small><?= htmlspecialchars($month['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dash-chart-card">
            <h4>Despesas por categoria</h4>
            <?php if (!$categories): ?>
                <p>Nenhuma despesa paga ainda.</p>
            <?php else: ?>
                <div class="dash-rank">
                    <?php foreach ($categories as $category): ?>
                        <?php $percent = (int)round(((float)$category['total'] / $maxCategory) * 100); ?>
                        <div class="dash-rank-row">
                            <span><?= htmlspecialchars((string)$category['category_name']) ?></span>
                            <div><i style="width:<?= $percent ?>%;"></i></div>
                            <strong><?= financeMoney((float)$category['total']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php foreach ($subcategoryPies as $pieData): ?>
            <div class="dash-chart-card dash-pie-card">
                <h4>Subcategorias de <?= htmlspecialchars(strtolower($pieData['title'])) ?></h4>
            <?php if (!$advancedCategories): ?>
                <div class="dash-upgrade-box">
                    <strong>Recurso do Start e Plus</strong>
                    <p>Faça upgrade para liberar graficos por subcategoria.</p>
                    <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/upgrade/index.php">Ver upgrade</a>
                </div>
            <?php elseif ((float)$pieData['total'] <= 0): ?>
                <p><?= htmlspecialchars($pieData['empty']) ?></p>
            <?php else: ?>
                <div class="dash-pie-wrap">
                    <div class="dash-pie" style="background: conic-gradient(<?= htmlspecialchars(implode(', ', $pieData['stops'])) ?>);">
                        <span><?= financeMoney((float)$pieData['total']) ?></span>
                    </div>
                    <div class="dash-pie-legend">
                        <?php foreach ($pieData['items'] as $index => $subcategory): ?>
                            <?php
                                $pieColors = $pieData['colors'];
                                $color = $pieColors[$index % count($pieColors)];
                                $percent = (float)$pieData['total'] > 0 ? (int)round(((float)$subcategory['total'] / (float)$pieData['total']) * 100) : 0;
                            ?>
                            <div class="dash-pie-legend-row">
                                <i style="background: <?= htmlspecialchars($color) ?>;"></i>
                                <span><?= htmlspecialchars((string)$subcategory['subcategory_name']) ?></span>
                                <strong><?= $percent ?>%</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php

return [
    'type' => 'panel',
    'module' => 'financeiro',
    'group' => 'financeiro',
    'group_label' => 'Financeiro',
    'order' => 20,
    'size' => 'wide',
    'html' => ob_get_clean(),
];
