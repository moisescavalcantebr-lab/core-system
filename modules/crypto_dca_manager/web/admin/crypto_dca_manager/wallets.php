<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'Contas';
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM crypto_wallets WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$wallets = cryptoDcaWallets($pdo, false);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Contas</h1>
            <p class="c-page-subtitle">Organize a estrategia por contas de DCA</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php" class="c-btn-secondary">Dashboard</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="crypto-two-col">
            <form class="c-card" method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/wallet_save.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
                <h3><?= $edit ? 'Editar conta' : 'Nova conta' ?></h3>
                <div class="crypto-form-grid">
                    <div class="c-form-group">
                        <label>Nome</label>
                        <input class="c-input" name="name" required value="<?= htmlspecialchars((string)($edit['name'] ?? '')) ?>">
                    </div>
                    <div class="c-form-group">
                        <label>Tipo</label>
                        <input class="c-input" name="type" value="<?= htmlspecialchars((string)($edit['type'] ?? 'strategy')) ?>">
                    </div>
                    <div class="c-form-group">
                        <label>Entrada padrao</label>
                        <input class="c-input" name="default_entry_amount" value="<?= htmlspecialchars((string)($edit['default_entry_amount'] ?? '50.00')) ?>">
                    </div>
                    <div class="c-form-group">
                        <label>Status</label>
                        <select class="c-input" name="status">
                            <option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativa</option>
                            <option value="inactive" <?= ($edit['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativa</option>
                        </select>
                    </div>
                </div>
                <div class="c-form-group">
                    <label>Descricao</label>
                    <textarea class="c-input" name="description" rows="3"><?= htmlspecialchars((string)($edit['description'] ?? '')) ?></textarea>
                </div>
                <button class="c-btn-secondary"><?= $edit ? 'Atualizar conta' : 'Cadastrar conta' ?></button>
            </form>

            <div class="c-card">
                <h3>Padrao da estrategia</h3>
                <p>Use contas para separar observacao, base, Top 5, X2/recuperacao e futuras camadas de conviccao.</p>
            </div>
        </div>

        <div class="c-card">
            <h3>Contas cadastradas</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Nome</th><th>Tipo</th><th>Entrada</th><th>Status</th><th>Acoes</th></tr></thead>
                    <tbody>
                    <?php foreach ($wallets as $wallet): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$wallet['name']) ?></strong><br><small><?= htmlspecialchars((string)$wallet['description']) ?></small></td>
                            <td><?= htmlspecialchars((string)$wallet['type']) ?></td>
                            <td><?= cryptoDcaMoney((float)$wallet['default_entry_amount']) ?></td>
                            <td><span class="c-badge <?= $wallet['status'] === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>"><?= htmlspecialchars((string)$wallet['status']) ?></span></td>
                            <td><a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/wallets.php?edit=<?= (int)$wallet['id'] ?>" class="c-btn-secondary">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
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
