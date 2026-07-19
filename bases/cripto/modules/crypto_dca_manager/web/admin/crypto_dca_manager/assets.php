<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'Ativos';
$wallets = cryptoDcaWallets($pdo);
$groups = cryptoDcaGroups($pdo);
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM crypto_assets WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$assets = cryptoDcaFetchAssets($pdo);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Ativos / Tokens</h1>
            <p class="c-page-subtitle">Cadastre tokens, status estrategico e limite de DCA</p>
        </div>
        <div class="crypto-actions">
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/cycles.php" class="c-btn-secondary">Ciclos</a>
            <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php" class="c-btn-secondary">Dashboard</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form class="c-card" method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/asset_save.php">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <h3><?= $edit ? 'Editar ativo' : 'Novo ativo' ?></h3>
            <div class="crypto-form-grid three">
                <div class="c-form-group">
                    <label>Simbolo</label>
                    <input class="c-input" name="symbol" required value="<?= htmlspecialchars((string)($edit['symbol'] ?? '')) ?>" placeholder="BTC">
                </div>
                <div class="c-form-group">
                    <label>Nome</label>
                    <input class="c-input" name="name" required value="<?= htmlspecialchars((string)($edit['name'] ?? '')) ?>" placeholder="Bitcoin">
                </div>
                <div class="c-form-group">
                    <label>Status estrategico</label>
                    <select class="c-input" name="strategy_status">
                        <?php foreach (cryptoDcaStrategyStatusOptions() as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($edit['strategy_status'] ?? 'em_observacao') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Conta</label>
                    <select class="c-input" name="wallet_id" required>
                        <?php foreach ($wallets as $wallet): ?>
                            <option value="<?= (int)$wallet['id'] ?>" <?= (int)($edit['wallet_id'] ?? 0) === (int)$wallet['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$wallet['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Grupo</label>
                    <select class="c-input" name="group_id" required>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?= (int)$group['id'] ?>" <?= (int)($edit['group_id'] ?? 0) === (int)$group['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$group['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Status</label>
                    <select class="c-input" name="status">
                        <option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="c-form-group">
                    <label>Par USDT</label>
                    <input class="c-input" name="pair_usdt" value="<?= htmlspecialchars((string)($edit['pair_usdt'] ?? '')) ?>" placeholder="BTCUSDT">
                </div>
                <div class="c-form-group">
                    <label>Entrada padrao</label>
                    <input class="c-input" name="base_entry_amount" value="<?= htmlspecialchars((string)($edit['base_entry_amount'] ?? '50.00')) ?>">
                </div>
                <div class="c-form-group">
                    <label>Max DCA</label>
                    <input class="c-input" name="max_dca" type="number" min="1" max="12" value="<?= (int)($edit['max_dca'] ?? 4) ?>">
                </div>
            </div>
            <div class="c-form-group">
                <label>Observacoes</label>
                <textarea class="c-input" name="notes" rows="3"><?= htmlspecialchars((string)($edit['notes'] ?? '')) ?></textarea>
            </div>
            <button class="c-btn-secondary"><?= $edit ? 'Atualizar ativo' : 'Cadastrar ativo' ?></button>
        </form>

        <div class="c-card">
            <h3>Ativos cadastrados</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Ativo</th><th>Conta</th><th>Grupo</th><th>Estrategia</th><th>DCA</th><th>Status</th><th>Acoes</th></tr></thead>
                    <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$asset['symbol']) ?></strong><br><small><?= htmlspecialchars((string)$asset['name']) ?></small></td>
                            <td><?= htmlspecialchars((string)$asset['wallet_name']) ?></td>
                            <td><?= htmlspecialchars((string)$asset['group_name']) ?></td>
                            <td><?= htmlspecialchars(cryptoDcaStrategyStatusLabel((string)$asset['strategy_status'])) ?></td>
                            <td><?= (int)$asset['current_dca_count'] ?>/<?= (int)$asset['max_dca'] ?></td>
                            <td><span class="c-badge <?= $asset['status'] === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>"><?= htmlspecialchars((string)$asset['status']) ?></span></td>
                            <td class="crypto-inline-actions">
                                <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/assets.php?edit=<?= (int)$asset['id'] ?>" class="c-btn-secondary">Editar</a>
                                <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/cycles.php?asset_id=<?= (int)$asset['id'] ?>" class="c-btn-secondary">Ciclo</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assets)): ?>
                        <tr><td colspan="7">Nenhum ativo cadastrado.</td></tr>
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
