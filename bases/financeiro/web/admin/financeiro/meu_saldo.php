<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAuth();
financeEnsureEntryMetaSchema($pdo);

if (!financeUsesParticipants($pdo)) {
    flash('info', 'Saldo por participante nao esta disponivel no modo financeiro pessoal.');
    redirect(PROJECT_URL . '/admin/financeiro/index.php');
}

$user = projectUser();
$userId = (int)($user['id'] ?? 0);
$balance = financeAllocatedBalance($pdo);

$pixSettings = financeCorePixSettings();
$pixKey = (string)($pixSettings['upgrade_pix_key'] ?? '');
$pixReceiverName = (string)($pixSettings['upgrade_pix_holder'] ?? '');
$pixNotes = (string)($pixSettings['upgrade_pix_notes'] ?? '');

$stmt = $pdo->prepare("
    SELECT *
    FROM finance_wallet_requests
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM finance_entries
    WHERE source IN ('balance_deposit', 'balance_usage')
      AND status = 'paid'
    ORDER BY COALESCE(paid_at, DATE(created_at)) DESC, id DESC
    LIMIT 10
");
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Saldo para pagamentos';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Saldo para pagamentos</h1>
            <p class="c-page-subtitle">Saldo alocado para pagar planos e módulos deste projeto.</p>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-dashboard-grid">
            <div class="c-dashboard-card c-card--info">
                <h4>Saldo atual</h4>
                <div class="c-metric"><?= financeMoney($balance) ?></div>
            </div>
        </div>

        <div class="c-form-grid">
            <div class="c-card">
                <h3>Adicionar saldo</h3>
                <?php if ($pixKey): ?>
                    <p>Faça o pagamento pela chave Pix abaixo e envie o comprovante para análise.</p>
                    <div class="c-card">
                        <strong><?= htmlspecialchars($pixReceiverName ?: 'Recebedor') ?></strong>
                        <div style="word-break:break-all;"><?= htmlspecialchars($pixKey) ?></div>
                        <?php if ($pixNotes !== ''): ?>
                            <p><?= nl2br(htmlspecialchars($pixNotes)) ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>Chave Pix ainda não configurada no Core.</p>
                <?php endif; ?>
            </div>

            <form action="<?= PROJECT_URL ?>/admin/financeiro/wallet_request_store.php" method="POST" enctype="multipart/form-data" class="c-card">
                <?= csrf_field(); ?>
                <h3>Enviar comprovante</h3>

                <div class="c-form-group">
                    <label>Valor</label>
                    <input type="number" name="amount" min="0" step="0.01" class="c-input" required>
                </div>

                <div class="c-form-group">
                    <label>Comprovante</label>
                    <input type="file" name="receipt" class="c-input" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                </div>

                <button class="c-btn-secondary" <?= $pixKey === '' ? 'disabled' : '' ?>>Enviar solicitação</button>
            </form>
        </div>

        <div class="c-card">
            <h3>Solicitações</h3>
            <?php if (empty($requests)): ?>
                <p>Nenhuma solicitação enviada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?= financeMoney((float)$request['amount']) ?></td>
                                    <td>
                                        <span class="c-badge <?= financeWalletStatusBadge((string)$request['status']) ?>">
                                            <?= financeWalletStatusLabel((string)$request['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars((string)$request['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Histórico</h3>
            <?php if (empty($entries)): ?>
                <p>Nenhum movimento aprovado.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Movimento</th>
                                <th>Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars(financeEntryTypeLabel($entry)) ?></td>
                                    <td><?= financeMoney((float)$entry['amount']) ?></td>
                                    <td><?= htmlspecialchars((string)($entry['paid_at'] ?? $entry['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
