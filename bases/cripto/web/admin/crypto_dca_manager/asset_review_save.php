<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$reviewId = (int)($_POST['monthly_review_id'] ?? 0);
$assetId = (int)($_POST['asset_id'] ?? 0);
$assetPerformance = cryptoDcaDecimal((string)($_POST['asset_performance_percent'] ?? '0'));
$recommendation = (string)($_POST['recommendation'] ?? 'manter');
$notes = trim((string)($_POST['notes'] ?? ''));

if ($reviewId <= 0 || $assetId <= 0 || !array_key_exists($recommendation, cryptoDcaRecommendationOptions())) {
    die('Dados invalidos.');
}

$stmt = $pdo->prepare("
    SELECT r.btc_performance_percent, a.wallet_id, a.group_id
    FROM crypto_monthly_reviews r
    CROSS JOIN crypto_assets a
    WHERE r.id = ? AND a.id = ?
    LIMIT 1
");
$stmt->execute([$reviewId, $assetId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Revisao ou ativo nao encontrado.');
}

$btcPerformance = (float)$data['btc_performance_percent'];
$relativeStrength = $assetPerformance - $btcPerformance;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM crypto_cycles WHERE asset_id = ? AND status = 'closed'");
$stmt->execute([$assetId]);
$cyclesClosed = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    INSERT INTO crypto_asset_reviews
        (monthly_review_id, asset_id, wallet_id, group_id, asset_performance_percent, btc_performance_percent, relative_strength, cycles_closed, recommendation, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        wallet_id = VALUES(wallet_id),
        group_id = VALUES(group_id),
        asset_performance_percent = VALUES(asset_performance_percent),
        btc_performance_percent = VALUES(btc_performance_percent),
        relative_strength = VALUES(relative_strength),
        cycles_closed = VALUES(cycles_closed),
        recommendation = VALUES(recommendation),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([
    $reviewId,
    $assetId,
    (int)$data['wallet_id'],
    (int)$data['group_id'],
    $assetPerformance,
    $btcPerformance,
    $relativeStrength,
    $cyclesClosed,
    $recommendation,
    $notes,
]);

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/reviews.php?review_id=' . $reviewId);
exit;
