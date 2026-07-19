<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$cycleId = (int)($_POST['cycle_id'] ?? 0);
$amount = cryptoDcaDecimal((string)($_POST['amount_usd'] ?? '0'));
$price = cryptoDcaDecimal((string)($_POST['price'] ?? '0'));
$quantity = cryptoDcaDecimal((string)($_POST['quantity'] ?? '0'));
$executedAt = !empty($_POST['executed_at']) ? (string)$_POST['executed_at'] : date('Y-m-d');

if ($cycleId <= 0 || $amount <= 0 || $price <= 0) {
    die('Dados invalidos.');
}

$stmt = $pdo->prepare("
    SELECT c.*, a.max_dca
    FROM crypto_cycles c
    INNER JOIN crypto_assets a ON a.id = c.asset_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$cycleId]);
$cycle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cycle || !in_array((string)$cycle['status'], ['open', 'x2_candidate'], true)) {
    die('Ciclo invalido.');
}

$quantity = $quantity > 0 ? $quantity : $amount / $price;
$dcaLevel = (int)$cycle['dca_count'] + 1;

$stmt = $pdo->prepare("
    INSERT INTO crypto_entries (cycle_id, asset_id, entry_type, amount_usd, price, quantity, dca_level, executed_at)
    VALUES (?, ?, 'dca', ?, ?, ?, ?, ?)
");
$stmt->execute([$cycleId, (int)$cycle['asset_id'], $amount, $price, $quantity, $dcaLevel, $executedAt]);

cryptoDcaRecalculateCycle($pdo, $cycleId);

if ($dcaLevel >= (int)$cycle['max_dca']) {
    $pdo->prepare("UPDATE crypto_cycles SET status = 'x2_candidate' WHERE id = ? AND status = 'open'")->execute([$cycleId]);
    $pdo->prepare("UPDATE crypto_assets SET strategy_status = 'x2_recuperacao' WHERE id = ?")->execute([(int)$cycle['asset_id']]);
}

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/cycles.php');
exit;
