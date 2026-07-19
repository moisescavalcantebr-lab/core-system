<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'DCA Strategy';
$summary = cryptoDcaDashboardSummary($pdo);
$recentAssets = cryptoDcaFetchAssets($pdo, "WHERE a.status = 'active'", []);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">DCA Strategy</h1>
            <p class="c-page-subtitle">Gestao pessoal de estrategia cripto DCA Spot</p>
        </div>
        <div class="crypto-actions">
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/wallets.php" class="c-btn-secondary">Contas</a>
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/assets.php" class="c-btn-secondary">Ativos</a>
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/cycles.php" class="c-btn-secondary">Ciclos</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="crypto-metrics">
            <div class="crypto-card"><span>Capital alocado</span><strong><?= cryptoDcaMoney((float)$summary['capital_allocated']) ?></strong><small>Total em ciclos</small></div>
            <div class="crypto-card"><span>Lucro/prejuizo</span><strong><?= cryptoDcaMoney((float)$summary['profit_total']) ?></strong><small>Ciclos fechados</small></div>
            <div class="crypto-card"><span>Capital em risco</span><strong><?= cryptoDcaMoney((float)$summary['capital_risk']) ?></strong><small>Ciclos abertos</small></div>
            <div class="crypto-card"><span>Ativos ativos</span><strong><?= (int)$summary['active_assets'] ?></strong><small><?= (int)$summary['watch_assets'] ?> em observacao</small></div>
            <div class="crypto-card"><span>Top 5</span><strong><?= (int)$summary['top_assets'] ?></strong><small>Alta conviccao</small></div>
            <div class="crypto-card"><span>X2</span><strong><?= (int)$summary['x2_assets'] ?></strong><small>Recuperacao</small></div>
        </div>

        <div class="crypto-dashboard-grid">
            <div class="c-card">
                <h3>Leitura rapida</h3>
                <div class="crypto-read">
                    <div><span>Melhor ativo</span><strong><?= htmlspecialchars((string)($summary['best_asset']['symbol'] ?? '-')) ?></strong><small><?= cryptoDcaMoney((float)($summary['best_asset']['profit'] ?? 0)) ?></small></div>
                    <div><span>Pior ativo</span><strong><?= htmlspecialchars((string)($summary['worst_asset']['symbol'] ?? '-')) ?></strong><small><?= cryptoDcaMoney((float)($summary['worst_asset']['profit'] ?? 0)) ?></small></div>
                    <div><span>Melhor grupo</span><strong><?= htmlspecialchars((string)($summary['best_group']['name'] ?? '-')) ?></strong><small><?= cryptoDcaMoney((float)($summary['best_group']['profit'] ?? 0)) ?></small></div>
                    <div><span>Pior grupo</span><strong><?= htmlspecialchars((string)($summary['worst_group']['name'] ?? '-')) ?></strong><small><?= cryptoDcaMoney((float)($summary['worst_group']['profit'] ?? 0)) ?></small></div>
                </div>
            </div>

            <div class="c-card">
                <h3>Comparacao BTC</h3>
                <p>As revisoes mensais registram a performance de cada ativo contra BTC e calculam forca relativa.</p>
                <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/reviews.php" class="c-btn-secondary">Revisao mensal</a>
            </div>
        </div>

        <div class="c-card">
            <h3>Ativos acompanhados</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Ativo</th><th>Conta</th><th>Grupo</th><th>Status</th><th>DCA</th><th>Entrada</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($recentAssets, 0, 10) as $asset): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$asset['symbol']) ?></strong><br><small><?= htmlspecialchars((string)$asset['name']) ?></small></td>
                            <td><?= htmlspecialchars((string)$asset['wallet_name']) ?></td>
                            <td><?= htmlspecialchars((string)$asset['group_name']) ?></td>
                            <td><span class="c-badge c-badge--neutral"><?= htmlspecialchars(cryptoDcaStrategyStatusLabel((string)$asset['strategy_status'])) ?></span></td>
                            <td><?= (int)$asset['current_dca_count'] ?>/<?= (int)$asset['max_dca'] ?></td>
                            <td><?= cryptoDcaMoney((float)$asset['current_entry_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentAssets)): ?>
                        <tr><td colspan="6">Nenhum ativo cadastrado ainda.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
