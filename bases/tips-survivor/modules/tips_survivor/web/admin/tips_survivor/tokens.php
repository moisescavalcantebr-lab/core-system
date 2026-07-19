<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireAdmin();
tipsEnsureSchema($pdo);

$title = 'Tokens - Tips Survivor';
$wallets = $pdo->query("
    SELECT *
    FROM tips_user_wallets
    ORDER BY status = 'start' DESC, tokens DESC, user_id ASC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
$transactions = $pdo->query("
    SELECT *
    FROM tips_token_transactions
    ORDER BY created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Tokens internos</h1>
            <p class="c-page-subtitle">Saldo e historico do Token Survivor, sem valor financeiro.</p>
        </div>
        <?= tipsNav('tokens') ?>
    </div>

    <div class="c-page-content">
        <div class="tips-panel-grid">
            <div class="c-card">
                <h3>Saldos</h3>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Usuario</th><th>Status</th><th>Tokens</th><th>Ultima ativacao</th></tr></thead>
                        <tbody>
                        <?php foreach ($wallets as $wallet): ?>
                            <tr>
                                <td>#<?= (int)$wallet['user_id'] ?></td>
                                <td><span class="c-badge <?= tipsBadgeClass((string)$wallet['status']) ?>"><?= htmlspecialchars(tipsStatusLabel((string)$wallet['status'])) ?></span></td>
                                <td><?= (int)$wallet['tokens'] ?></td>
                                <td><?= htmlspecialchars((string)($wallet['last_start_activated_at'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($wallets)): ?>
                            <tr><td colspan="4">Nenhum saldo criado ainda.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="c-card">
                <h3>Historico recente</h3>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Usuario</th><th>Tipo</th><th>Valor</th><th>Data</th></tr></thead>
                        <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>#<?= (int)$transaction['user_id'] ?></td>
                                <td><?= htmlspecialchars((string)$transaction['type']) ?></td>
                                <td><?= (int)$transaction['amount'] ?></td>
                                <td><?= htmlspecialchars((string)$transaction['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="4">Nenhuma transacao registrada ainda.</td></tr>
                        <?php endif; ?>
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
