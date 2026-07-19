<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'Revisao Mensal';
$assets = cryptoDcaFetchAssets($pdo, "WHERE a.status = 'active'", []);
$reviews = $pdo->query("SELECT * FROM crypto_monthly_reviews ORDER BY review_year DESC, review_month DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$selectedReviewId = (int)($_GET['review_id'] ?? ($reviews[0]['id'] ?? 0));
$selectedReview = null;

if ($selectedReviewId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM crypto_monthly_reviews WHERE id = ? LIMIT 1");
    $stmt->execute([$selectedReviewId]);
    $selectedReview = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$assetReviews = [];
if ($selectedReview) {
    $stmt = $pdo->prepare("SELECT * FROM crypto_asset_reviews WHERE monthly_review_id = ?");
    $stmt->execute([(int)$selectedReview['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $assetReviews[(int)$row['asset_id']] = $row;
    }
}

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Revisao Mensal</h1>
            <p class="c-page-subtitle">Comparacao manual dos ativos contra BTC no fechamento estrategico</p>
        </div>
        <div class="crypto-actions">
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php" class="c-btn-secondary">Dashboard</a>
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/ranking.php" class="c-btn-secondary">Ranking</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="crypto-two-col">
            <div class="c-card">
                <h3>Nova revisao</h3>
                <form method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/review_save.php" class="crypto-form-grid">
                    <?= csrf_field() ?>
                    <label>Mes
                        <input type="number" name="review_month" min="1" max="12" value="<?= (int)date('n') ?>" required>
                    </label>
                    <label>Ano
                        <input type="number" name="review_year" min="2020" value="<?= (int)date('Y') ?>" required>
                    </label>
                    <label>Capital total
                        <input type="text" name="total_capital" value="0,00">
                    </label>
                    <label>Lucro total
                        <input type="text" name="total_profit" value="0,00">
                    </label>
                    <label>% BTC no periodo
                        <input type="text" name="btc_performance_percent" value="0,00">
                    </label>
                    <label>Observacoes
                        <textarea name="notes" rows="3" placeholder="Leitura do mes, risco, decisao geral..."></textarea>
                    </label>
                    <button class="c-btn-primary" type="submit">Salvar revisao</button>
                </form>
            </div>

            <div class="c-card">
                <h3>Historico</h3>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Periodo</th><th>Capital</th><th>Resultado</th><th>BTC</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td><?= str_pad((string)$review['review_month'], 2, '0', STR_PAD_LEFT) ?>/<?= (int)$review['review_year'] ?></td>
                                <td><?= cryptoDcaMoney((float)$review['total_capital']) ?></td>
                                <td><?= cryptoDcaMoney((float)$review['total_profit']) ?></td>
                                <td><?= cryptoDcaPercent((float)$review['btc_performance_percent']) ?></td>
                                <td><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/reviews.php?review_id=<?= (int)$review['id'] ?>">Abrir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reviews)): ?>
                            <tr><td colspan="5">Nenhuma revisao salva.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="c-card">
            <h3>Ativos da revisao <?= $selectedReview ? str_pad((string)$selectedReview['review_month'], 2, '0', STR_PAD_LEFT) . '/' . (int)$selectedReview['review_year'] : '' ?></h3>
            <?php if (!$selectedReview): ?>
                <p>Crie uma revisao mensal para liberar a avaliacao dos ativos.</p>
            <?php else: ?>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Ativo</th><th>Conta</th><th>Grupo</th><th>% ativo</th><th>Recomendacao</th><th>Notas</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <?php $current = $assetReviews[(int)$asset['id']] ?? []; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$asset['symbol']) ?></strong></td>
                                <td><?= htmlspecialchars((string)$asset['wallet_name']) ?></td>
                                <td><?= htmlspecialchars((string)$asset['group_name']) ?></td>
                                <td colspan="4">
                                    <form method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/asset_review_save.php" class="crypto-inline-review">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="monthly_review_id" value="<?= (int)$selectedReview['id'] ?>">
                                        <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                                        <input type="text" name="asset_performance_percent" value="<?= htmlspecialchars((string)($current['asset_performance_percent'] ?? '0,00')) ?>" placeholder="% ativo">
                                        <select name="recommendation">
                                            <?php foreach (cryptoDcaRecommendationOptions() as $value => $label): ?>
                                                <option value="<?= htmlspecialchars($value) ?>" <?= ($current['recommendation'] ?? 'manter') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="notes" value="<?= htmlspecialchars((string)($current['notes'] ?? '')) ?>" placeholder="Notas">
                                        <button class="c-btn-secondary" type="submit">Salvar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assets)): ?>
                            <tr><td colspan="7">Nenhum ativo ativo.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
