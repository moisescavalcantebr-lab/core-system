<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'Ranking DCA';

$topProfit = $pdo->query("
    SELECT a.symbol, a.name, COALESCE(SUM(c.profit_amount), 0) AS profit, COALESCE(AVG(c.profit_percent), 0) AS percent
    FROM crypto_assets a
    LEFT JOIN crypto_cycles c ON c.asset_id = a.id AND c.status = 'closed'
    GROUP BY a.id
    ORDER BY profit DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$topPercent = $pdo->query("
    SELECT a.symbol, a.name, COALESCE(SUM(c.profit_amount), 0) AS profit, COALESCE(AVG(c.profit_percent), 0) AS percent
    FROM crypto_assets a
    LEFT JOIN crypto_cycles c ON c.asset_id = a.id AND c.status = 'closed'
    GROUP BY a.id
    ORDER BY percent DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$groups = $pdo->query("
    SELECT g.name, COALESCE(SUM(c.profit_amount), 0) AS profit
    FROM crypto_groups g
    LEFT JOIN crypto_assets a ON a.group_id = g.id
    LEFT JOIN crypto_cycles c ON c.asset_id = a.id AND c.status = 'closed'
    GROUP BY g.id
    ORDER BY profit DESC
")->fetchAll(PDO::FETCH_ASSOC);

$reviewRank = $pdo->query("
    SELECT ar.*, a.symbol, a.name
    FROM crypto_asset_reviews ar
    INNER JOIN crypto_assets a ON a.id = ar.asset_id
    INNER JOIN crypto_monthly_reviews r ON r.id = ar.monthly_review_id
    WHERE r.id = (SELECT id FROM crypto_monthly_reviews ORDER BY review_year DESC, review_month DESC LIMIT 1)
    ORDER BY ar.relative_strength DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Ranking</h1>
            <p class="c-page-subtitle">Leitura rapida dos melhores, piores e candidatos estrategicos</p>
        </div>
        <div class="crypto-actions">
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php" class="c-btn-secondary">Dashboard</a>
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/reviews.php" class="c-btn-secondary">Revisao mensal</a>
        </div>
    </div>

    <div class="c-page-content">
        <div class="crypto-two-col">
            <div class="c-card">
                <h3>Top lucro</h3>
                <?php require __DIR__ . '/ranking_table.php'; cryptoDcaRankingTable($topProfit, 'profit'); ?>
            </div>
            <div class="c-card">
                <h3>Top percentual</h3>
                <?php cryptoDcaRankingTable($topPercent, 'percent'); ?>
            </div>
        </div>

        <div class="crypto-two-col">
            <div class="c-card">
                <h3>Grupos</h3>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Grupo</th><th>Resultado</th></tr></thead>
                        <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr><td><?= htmlspecialchars((string)$group['name']) ?></td><td><?= cryptoDcaMoney((float)$group['profit']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups)): ?><tr><td colspan="2">Sem dados.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="c-card">
                <h3>Forca relativa contra BTC</h3>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Ativo</th><th>Forca</th><th>Recomendacao</th></tr></thead>
                        <tbody>
                        <?php foreach ($reviewRank as $review): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$review['symbol']) ?></strong></td>
                                <td><?= cryptoDcaPercent((float)$review['relative_strength']) ?></td>
                                <td><?= htmlspecialchars(cryptoDcaRecommendationOptions()[(string)$review['recommendation']] ?? (string)$review['recommendation']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reviewRank)): ?><tr><td colspan="3">Sem revisao mensal ainda.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
