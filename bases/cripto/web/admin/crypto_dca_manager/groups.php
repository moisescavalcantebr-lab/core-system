<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);

$title = 'Grupos';
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM crypto_groups WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$groups = cryptoDcaGroups($pdo, false);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Grupos / Setores</h1>
            <p class="c-page-subtitle">Classifique ativos por narrativa, risco e segmento</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php" class="c-btn-secondary">Dashboard</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>
        <form class="c-card" method="post" action="<?= PROJECT_URL ?>/admin/crypto_dca_manager/group_save.php">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="crypto-form-grid three">
                <div class="c-form-group">
                    <label>Nome</label>
                    <input class="c-input" name="name" required value="<?= htmlspecialchars((string)($edit['name'] ?? '')) ?>">
                </div>
                <div class="c-form-group">
                    <label>Risco</label>
                    <select class="c-input" name="risk_level">
                        <?php foreach (cryptoDcaRiskOptions() as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($edit['risk_level'] ?? 'medium') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
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
            </div>
            <div class="c-form-group">
                <label>Descricao</label>
                <textarea class="c-input" name="description" rows="3"><?= htmlspecialchars((string)($edit['description'] ?? '')) ?></textarea>
            </div>
            <button class="c-btn-secondary"><?= $edit ? 'Atualizar grupo' : 'Cadastrar grupo' ?></button>
        </form>

        <div class="c-card">
            <h3>Grupos cadastrados</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Nome</th><th>Risco</th><th>Status</th><th>Acoes</th></tr></thead>
                    <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$group['name']) ?></strong><br><small><?= htmlspecialchars((string)$group['description']) ?></small></td>
                            <td><?= htmlspecialchars(cryptoDcaRiskOptions()[$group['risk_level']] ?? (string)$group['risk_level']) ?></td>
                            <td><span class="c-badge <?= $group['status'] === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>"><?= htmlspecialchars((string)$group['status']) ?></span></td>
                            <td><a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/groups.php?edit=<?= (int)$group['id'] ?>" class="c-btn-secondary">Editar</a></td>
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
