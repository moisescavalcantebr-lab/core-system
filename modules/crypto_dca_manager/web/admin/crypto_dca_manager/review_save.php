<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$month = (int)($_POST['review_month'] ?? 0);
$year = (int)($_POST['review_year'] ?? 0);
$totalCapital = cryptoDcaDecimal((string)($_POST['total_capital'] ?? '0'));
$totalProfit = cryptoDcaDecimal((string)($_POST['total_profit'] ?? '0'));
$btcPerformance = cryptoDcaDecimal((string)($_POST['btc_performance_percent'] ?? '0'));
$notes = trim((string)($_POST['notes'] ?? ''));
$totalPercent = $totalCapital > 0 ? ($totalProfit / $totalCapital) * 100 : 0;

if ($month < 1 || $month > 12 || $year < 2020) {
    die('Periodo invalido.');
}

$stmt = $pdo->prepare("
    INSERT INTO crypto_monthly_reviews (review_month, review_year, total_capital, total_profit, total_profit_percent, btc_performance_percent, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        total_capital = VALUES(total_capital),
        total_profit = VALUES(total_profit),
        total_profit_percent = VALUES(total_profit_percent),
        btc_performance_percent = VALUES(btc_performance_percent),
        notes = VALUES(notes),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$month, $year, $totalCapital, $totalProfit, $totalPercent, $btcPerformance, $notes]);

$stmt = $pdo->prepare("SELECT id FROM crypto_monthly_reviews WHERE review_month = ? AND review_year = ? LIMIT 1");
$stmt->execute([$month, $year]);
$reviewId = (int)$stmt->fetchColumn();

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/reviews.php?review_id=' . $reviewId);
exit;
