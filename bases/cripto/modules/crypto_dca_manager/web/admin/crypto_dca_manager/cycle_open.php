<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$assetId = (int)($_POST['asset_id'] ?? 0);
$entryAmount = cryptoDcaDecimal((string)($_POST['entry_amount'] ?? '50'));
$price = cryptoDcaDecimal((string)($_POST['price'] ?? '0'));
$quantity = cryptoDcaDecimal((string)($_POST['quantity'] ?? '0'));
$openedAt = !empty($_POST['opened_at']) ? (string)$_POST['opened_at'] : date('Y-m-d');

if ($assetId <= 0 || $entryAmount <= 0) {
    die('Dados invalidos.');
}

$stmt = $pdo->prepare("SELECT * FROM crypto_assets WHERE id = ? LIMIT 1");
$stmt->execute([$assetId]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$asset) {
    die('Ativo nao encontrado.');
}

if ($quantity <= 0 && $price > 0) {
    $quantity = $entryAmount / $price;
}

$stmt = $pdo->prepare("SELECT COALESCE(MAX(cycle_number), 0) + 1 FROM crypto_cycles WHERE asset_id = ?");
$stmt->execute([$assetId]);
$cycleNumber = (int)$stmt->fetchColumn();

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        INSERT INTO crypto_cycles (asset_id, wallet_id, group_id, cycle_number, entry_amount, opened_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$assetId, (int)$asset['wallet_id'], (int)$asset['group_id'], $cycleNumber, $entryAmount, $openedAt]);
    $cycleId = (int)$pdo->lastInsertId();

    if ($price > 0 || $quantity > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO crypto_entries (cycle_id, asset_id, entry_type, amount_usd, price, quantity, dca_level, executed_at)
            VALUES (?, ?, 'initial', ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$cycleId, $assetId, $entryAmount, $price, $quantity, $openedAt]);
    }

    cryptoDcaRecalculateCycle($pdo, $cycleId);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    die($e->getMessage());
}

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/cycles.php');
exit;
