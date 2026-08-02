<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);
financeEnsureCategoryAddonSchema($pdo);

$title = 'Financeiro';
$usesParticipants = financeUsesParticipants($pdo);
$simpleAdminMode = financeSimpleAdminMode($pdo);
$showParticipantFinance = $usesParticipants && !$simpleAdminMode;
$financeUserLabel = financeUserLabel();
$financeUserLabelPlural = financeUserLabel(true);
$advancedCategories = financeAdvancedCategoriesEnabled();

$summary = [
    'income' => 0.0,
    'expense' => 0.0,
    'pending_income' => 0.0,
    'pending_expense' => 0.0,
];

$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'paid' THEN amount ELSE 0 END), 0) AS income_total,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END), 0) AS expense_total,
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount ELSE 0 END), 0) AS pending_income_total,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'pending' THEN amount ELSE 0 END), 0) AS pending_expense_total
    FROM finance_entries
    WHERE COALESCE(source, 'manual') NOT IN ('balance_deposit', 'balance_usage')
");
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$summary['income'] = (float)($row['income_total'] ?? 0);
$summary['expense'] = (float)($row['expense_total'] ?? 0);
$summary['pending_income'] = (float)($row['pending_income_total'] ?? 0);
$summary['pending_expense'] = (float)($row['pending_expense_total'] ?? 0);
$balance = $summary['income'] - $summary['expense'];
$pendingBalance = $summary['pending_income'] - $summary['pending_expense'];
$allocatedBalance = financeAllocatedBalance($pdo);

$walletPending = ['total' => 0, 'amount' => 0];

if ($showParticipantFinance) {
    $walletPending = $pdo->query("
        SELECT COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
        FROM finance_wallet_requests
        WHERE status = 'pending'
    ")->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'amount' => 0];
}

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

    if (isset($months[$key], $months[$key][$type])) {
        $months[$key][$type] = (float)$monthRow['total'];
    }
}

$maxMonthly = 1.0;
foreach ($months as $month) {
    $maxMonthly = max($maxMonthly, (float)$month['income'], (float)$month['expense']);
}

$recentEntries = $pdo->query("
    SELECT e.*, c.name AS category_name, pc.name AS parent_category_name
    FROM finance_entries e
    LEFT JOIN finance_categories c ON c.id = e.category_id
    LEFT JOIN finance_categories pc ON pc.id = c.parent_id
    WHERE COALESCE(e.source, 'manual') NOT IN ('balance_deposit', 'balance_usage')
    ORDER BY COALESCE(e.due_date, e.created_at) DESC, e.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<style>
.finance-summary-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.finance-summary-card {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.finance-summary-card h3,
.finance-summary-card p {
    margin: 0;
}

.finance-summary-card p,
.finance-summary-item span,
.finance-summary-alert span {
    color: var(--text-secondary);
    font-size: 12px;
}

.finance-summary-main {
    display: grid;
    gap: 10px;
}

.finance-summary-main strong {
    display: block;
    font-size: 26px;
    margin-top: 4px;
}

.finance-summary-items {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.finance-summary-item {
    border: 1px solid rgba(148, 163, 184, .2);
    padding: 10px;
}

.finance-summary-item strong {
    display: block;
    margin-top: 4px;
    font-size: 16px;
}

.finance-good { color: #22c55e; }
.finance-bad { color: #ef4444; }
.finance-warn { color: #f59e0b; }

.c-btn-balance {
    border-color: rgba(34, 197, 94, .55);
    background: rgba(34, 197, 94, .14);
    color: #bbf7d0;
    font-weight: 800;
}

.finance-summary-alerts {
    display: grid;
    gap: 8px;
}

.finance-summary-alert {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, .2);
    padding: 10px;
}

.finance-summary-alert strong {
    white-space: nowrap;
}

.finance-overview-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.finance-recent-card {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.finance-recent-card h3,
.finance-recent-card p {
    margin: 0;
}

.finance-recent-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.finance-recent-item span,
.finance-empty-note {
    color: var(--text-secondary);
    font-size: 12px;
}

.finance-recent-list {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.finance-recent-item {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 10px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, .2);
    padding: 10px;
}

.finance-recent-item strong {
    display: block;
}

@media (min-width: 720px) {
    .finance-summary-grid {
        grid-template-columns: minmax(0, 1.2fr) minmax(280px, .8fr);
    }

}

@media (max-width: 700px) {
    .finance-recent-item {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Financeiro</h1>
            <p class="c-page-subtitle"><?= $showParticipantFinance ? 'Entradas, saidas, pendencias e saldo por ' . htmlspecialchars(strtolower($financeUserLabel)) : 'Caixa simples do time: entradas, saidas e pendencias' ?></p>
        </div>

        <div class="c-page-actions">
            <?php if ($showParticipantFinance): ?>
                <a href="<?= PROJECT_URL ?>/admin/financeiro/settings.php" class="c-btn-secondary">
                    Configurações
                </a>
            <?php endif; ?>
            <?php if ($showParticipantFinance): ?>
                <a href="<?= PROJECT_URL ?>/admin/financeiro/wallet_requests.php" class="c-btn-secondary">
                    Solicitações de saldo
                </a>
                <a href="<?= PROJECT_URL ?>/admin/financeiro/meu_saldo.php" class="c-btn-secondary c-btn-balance">
                    Adicionar Saldo
                </a>
            <?php endif; ?>
            <a href="<?= PROJECT_URL ?>/admin/financeiro/lancamentos.php" class="c-btn-secondary">
                Lancamentos
            </a>
            <a href="<?= PROJECT_URL ?>/admin/financeiro/create.php" class="c-btn-secondary">
                Novo Lancamento
            </a>
        </div>
    </div>

    <div class="c-page-content">

        <div class="finance-summary-grid">
            <div class="finance-summary-card c-card--info">
                <div class="finance-summary-main">
                    <div>
                        <p>Saldo atual</p>
                        <strong><?= financeMoney($balance) ?></strong>
                    </div>

                    <div class="finance-summary-items">
                        <div class="finance-summary-item">
                            <span>Entradas pagas</span>
                            <strong class="finance-good"><?= financeMoney($summary['income']) ?></strong>
                        </div>
                        <div class="finance-summary-item">
                            <span>Saidas pagas</span>
                            <strong class="finance-bad"><?= financeMoney($summary['expense']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="finance-summary-card c-card--warning">
                <h3><?= $showParticipantFinance ? 'Pendencias e saldos de ' . htmlspecialchars(strtolower($financeUserLabelPlural)) : 'Resumo pendente' ?></h3>
                <div class="finance-summary-alerts" style="margin-top:12px;">
                    <?php if (!$showParticipantFinance): ?>
                        <div class="finance-summary-alert">
                            <span>A receber</span>
                            <strong class="finance-good"><?= financeMoney($summary['pending_income']) ?></strong>
                        </div>

                        <div class="finance-summary-alert">
                            <span>A pagar</span>
                            <strong class="finance-bad"><?= financeMoney($summary['pending_expense']) ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="finance-summary-alert">
                        <span>Pendencias liquidas</span>
                        <strong class="finance-warn"><?= financeMoney($pendingBalance) ?></strong>
                    </div>

                    <?php if ($showParticipantFinance): ?>
                        <div class="finance-summary-alert">
                            <span>Saldo para pagamentos</span>
                            <strong><?= financeMoney($allocatedBalance) ?></strong>
                        </div>

                        <a href="<?= PROJECT_URL ?>/admin/financeiro/wallet_requests.php" class="finance-summary-alert" style="text-decoration:none;">
                            <span>Solicitacoes de saldo</span>
                            <strong>
                                <?= (int)$walletPending['total'] ?>
                                <small><?= (int)$walletPending['total'] > 0 ? financeMoney((float)$walletPending['amount']) : 'Tudo em dia' ?></small>
                            </strong>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="finance-overview-grid">
            <div class="finance-recent-card">
                <div class="finance-recent-head">
                    <div>
                        <h3>Ultimos lancamentos</h3>
                        <p class="finance-empty-note">A lista completa fica nos filtros.</p>
                    </div>
                    <a href="<?= PROJECT_URL ?>/admin/financeiro/lancamentos.php" class="c-btn-secondary">Ver todos</a>
                </div>

                <?php if (!$recentEntries): ?>
                    <p class="finance-empty-note" style="margin-top:12px;">Nenhum lancamento cadastrado.</p>
                <?php else: ?>
                    <div class="finance-recent-list">
                        <?php foreach ($recentEntries as $entry): ?>
                            <div class="finance-recent-item">
                                <div>
                                    <strong><?= htmlspecialchars((string)$entry['title']) ?></strong>
                                    <span><?= htmlspecialchars((string)($entry['category_name'] ?? $entry['parent_category_name'] ?? 'Sem categoria')) ?></span>
                                </div>
                                <strong class="<?= (string)$entry['type'] === 'income' ? 'finance-good' : 'finance-bad' ?>">
                                    <?= financeMoney((float)$entry['amount']) ?>
                                </strong>
                                <span class="c-badge <?= financeStatusBadge((string)$entry['status']) ?>">
                                    <?= financeStatusLabel((string)$entry['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';

