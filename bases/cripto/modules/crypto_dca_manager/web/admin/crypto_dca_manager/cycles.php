<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'Ciclos';
$assets = cryptoDcaFetchAssets($pdo, "WHERE a.status = 'active'", []);
$selectedAssetId = (int)($_GET['asset_id'] ?? 0);

$cycles = $pdo->query("
    SELECT c.*, a.symbol, a.name AS asset_name, w.name AS wallet_name, g.name AS group_name
    FROM crypto_cycles c
    INNER JOIN crypto_assets a ON a.id = c.asset_id
    INNER JOIN crypto_wallets w ON w.id = c.wallet_id
    INNER JOIN crypto_groups g ON g.id = c.group_id
    ORDER BY c.status = 'open' DESC, c.opened_at DESC, c.id DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Ciclos e Entradas</h1>
            <p class="c-page-subtitle">Abra ciclos, registre DCA e feche resultados</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/assets.php" class="c-btn-secondary">Ativos</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="crypto-two-col">
            <form class="c-card" method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/cycle_open.php">
                <?= csrf_field() ?>
                <h3>Abrir ciclo</h3>
                <div class="c-form-group">
                    <label>Ativo</label>
                    <select class="c-input" name="asset_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($assets as $asset): ?>
                            <option value="<?= (int)$asset['id'] ?>" <?= $selectedAssetId === (int)$asset['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$asset['symbol'] . ' - ' . (string)$asset['wallet_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="crypto-form-grid">
                    <div class="c-form-group">
                        <label>Entrada inicial</label>
                        <input class="c-input" name="entry_amount" value="50,00">
                    </div>
                    <div class="c-form-group">
                        <label>Data</label>
                        <input class="c-input" name="opened_at" type="date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="c-form-group">
                        <label>Preco</label>
                        <input class="c-input" name="price" placeholder="0,00000000">
                    </div>
                    <div class="c-form-group">
                        <label>Quantidade</label>
                        <input class="c-input" name="quantity" placeholder="automatico se tiver preco">
                    </div>
                </div>
                <button class="c-btn-secondary">Abrir ciclo</button>
            </form>

            <form class="c-card" method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/entry_save.php">
                <?= csrf_field() ?>
                <h3>Registrar entrada DCA</h3>
                <div class="c-form-group">
                    <label>Ciclo aberto</label>
                    <select class="c-input" name="cycle_id" required>
                        <option value="">Selecione</option>
                        <?php foreach ($cycles as $cycle): ?>
                            <?php if ($cycle['status'] !== 'open' && $cycle['status'] !== 'x2_candidate') continue; ?>
                            <option value="<?= (int)$cycle['id'] ?>"><?= htmlspecialchars((string)$cycle['symbol'] . ' ciclo #' . (string)$cycle['cycle_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="crypto-form-grid">
                    <div class="c-form-group"><label>Valor</label><input class="c-input" name="amount_usd" value="50,00"></div>
                    <div class="c-form-group"><label>Preco</label><input class="c-input" name="price" required></div>
                    <div class="c-form-group"><label>Quantidade</label><input class="c-input" name="quantity" placeholder="automatico"></div>
                    <div class="c-form-group"><label>Data</label><input class="c-input" name="executed_at" type="date" value="<?= date('Y-m-d') ?>"></div>
                </div>
                <button class="c-btn-secondary">Registrar DCA</button>
            </form>
        </div>

        <div class="c-card">
            <h3>Ciclos</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Ciclo</th><th>Conta</th><th>Grupo</th><th>Alocado</th><th>Preco medio</th><th>DCA</th><th>Status</th><th>Acoes</th></tr></thead>
                    <tbody>
                    <?php foreach ($cycles as $cycle): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$cycle['symbol']) ?> #<?= (int)$cycle['cycle_number'] ?></strong><br><small><?= date('d/m/Y', strtotime((string)$cycle['opened_at'])) ?></small></td>
                            <td><?= htmlspecialchars((string)$cycle['wallet_name']) ?></td>
                            <td><?= htmlspecialchars((string)$cycle['group_name']) ?></td>
                            <td><?= cryptoDcaMoney((float)$cycle['total_allocated']) ?></td>
                            <td><?= $cycle['average_price'] ? number_format((float)$cycle['average_price'], 8, ',', '.') : '-' ?></td>
                            <td><?= (int)$cycle['dca_count'] ?></td>
                            <td><span class="c-badge c-badge--neutral"><?= htmlspecialchars((string)$cycle['status']) ?></span></td>
                            <td class="crypto-inline-actions">
                                <?php if (in_array($cycle['status'], ['open', 'x2_candidate'], true)): ?>
                                    <form method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/cycle_close.php?id=<?= (int)$cycle['id'] ?>">
                                        <?= csrf_field() ?>
                                        <input type="text" name="exit_price" class="c-input" placeholder="Preco saida" style="width:110px">
                                        <button class="c-btn-secondary">Fechar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cycles)): ?><tr><td colspan="8">Nenhum ciclo aberto.</td></tr><?php endif; ?>
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
