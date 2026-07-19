<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$symbol = strtoupper(trim((string)($_POST['symbol'] ?? '')));
$name = trim((string)($_POST['name'] ?? ''));
$walletId = (int)($_POST['wallet_id'] ?? 0);
$groupId = (int)($_POST['group_id'] ?? 0);
$strategy = array_key_exists((string)($_POST['strategy_status'] ?? ''), cryptoDcaStrategyStatusOptions()) ? (string)$_POST['strategy_status'] : 'em_observacao';
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
$baseEntry = cryptoDcaDecimal((string)($_POST['base_entry_amount'] ?? '50'));
$maxDca = max(1, min(12, (int)($_POST['max_dca'] ?? 4)));
$pairUsdt = strtoupper(trim((string)($_POST['pair_usdt'] ?? '')));
$pairUsdc = strtoupper(trim((string)($_POST['pair_usdc'] ?? '')));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($symbol === '' || $name === '' || $walletId <= 0 || $groupId <= 0) {
    die('Dados obrigatorios incompletos.');
}

if ($pairUsdt === '') {
    $pairUsdt = $symbol . 'USDT';
}

try {
    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE crypto_assets
            SET symbol = ?, name = ?, pair_usdt = ?, pair_usdc = ?, wallet_id = ?, group_id = ?, strategy_status = ?,
                base_entry_amount = ?, current_entry_amount = ?, max_dca = ?, reference_asset_symbol = 'BTC', notes = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$symbol, $name, $pairUsdt, $pairUsdc ?: null, $walletId, $groupId, $strategy, $baseEntry > 0 ? $baseEntry : 50, $baseEntry > 0 ? $baseEntry : 50, $maxDca, $notes ?: null, $status, $id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO crypto_assets
            (symbol, name, pair_usdt, pair_usdc, wallet_id, group_id, strategy_status, base_entry_amount, current_entry_amount, max_dca, reference_asset_symbol, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BTC', ?, ?)
        ");
        $stmt->execute([$symbol, $name, $pairUsdt, $pairUsdc ?: null, $walletId, $groupId, $strategy, $baseEntry > 0 ? $baseEntry : 50, $baseEntry > 0 ? $baseEntry : 50, $maxDca, $notes ?: null, $status]);
    }
} catch (Throwable $e) {
    die('Nao foi possivel salvar. Verifique duplicidade de simbolo na mesma conta.');
}

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/assets.php');
exit;
