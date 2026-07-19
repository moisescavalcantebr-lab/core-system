<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$cycleId = (int)($_GET['id'] ?? 0);
$exitPrice = cryptoDcaDecimal((string)($_POST['exit_price'] ?? '0'));

if ($cycleId <= 0 || $exitPrice <= 0) {
    die('Preco de saida obrigatorio.');
}

$stmt = $pdo->prepare("
    SELECT c.*,
        COALESCE(SUM(CASE WHEN e.entry_type IN ('initial','dca','adjustment') THEN e.quantity ELSE 0 END), 0) AS quantity_total
    FROM crypto_cycles c
    LEFT JOIN crypto_entries e ON e.cycle_id = c.id
    WHERE c.id = ?
    GROUP BY c.id
    LIMIT 1
");
$stmt->execute([$cycleId]);
$cycle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cycle) {
    die('Ciclo nao encontrado.');
}

$quantity = (float)($cycle['quantity_total'] ?? 0);
$allocated = (float)($cycle['total_allocated'] ?? 0);
$exitValue = $quantity * $exitPrice;
$profitAmount = $exitValue - $allocated;
$profitPercent = $allocated > 0 ? ($profitAmount / $allocated) * 100 : 0;

$stmt = $pdo->prepare("
    UPDATE crypto_cycles
    SET exit_price = ?, profit_amount = ?, profit_percent = ?, status = 'closed', closed_at = ?
    WHERE id = ?
");
$stmt->execute([$exitPrice, $profitAmount, $profitPercent, date('Y-m-d'), $cycleId]);

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/cycles.php');
exit;
