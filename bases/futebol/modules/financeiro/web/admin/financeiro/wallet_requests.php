<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);

if (!financeUsesParticipants($pdo)) {
    flash('info', 'Solicitacoes de saldo ficam disponiveis apenas no modo participantes.');
    redirect(PROJECT_URL . '/admin/financeiro/index.php');
}

$requests = $pdo->query("
    SELECT r.*, u.name AS user_name, u.email AS user_email, reviewer.name AS reviewer_name
    FROM finance_wallet_requests r
    LEFT JOIN project_users u ON u.id = r.user_id
    LEFT JOIN project_users reviewer ON reviewer.id = r.reviewed_by_user_id
    ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Solicitações de saldo';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Solicitações de saldo</h1>
            <p class="c-page-subtitle">Confira comprovantes antes de liberar saldo para pagamentos do projeto.</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/financeiro/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <?php if (empty($requests)): ?>
                <p>Nenhuma solicitação enviada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Solicitante</th>
                                <th>Valor</th>
                                <th>Comprovante</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($request['user_name'] ?? '-') ?></strong>
                                        <div><?= htmlspecialchars($request['user_email'] ?? '') ?></div>
                                        <?php if (!empty($request['reviewer_name'])): ?>
                                            <small>Rev.: <?= htmlspecialchars($request['reviewer_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= financeMoney((float)$request['amount']) ?></td>
                                    <td>
                                        <?php if (!empty($request['receipt_path'])): ?>
                                            <a href="<?= PROJECT_URL ?>/<?= htmlspecialchars((string)$request['receipt_path']) ?>" target="_blank" rel="noopener noreferrer">Ver comprovante</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="c-badge <?= financeWalletStatusBadge((string)$request['status']) ?>">
                                            <?= financeWalletStatusLabel((string)$request['status']) ?>
                                        </span>
                                        <?php if (!empty($request['notes'])): ?>
                                            <div><?= htmlspecialchars((string)$request['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)$request['created_at']) ?></td>
                                    <td>
                                        <?php if (($request['status'] ?? '') === 'pending'): ?>
                                            <form action="<?= PROJECT_URL ?>/admin/financeiro/wallet_request_review.php?id=<?= (int)$request['id'] ?>" method="POST" style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="approve">
                                                <button class="c-btn-secondary">Aprovar</button>
                                            </form>

                                            <form action="<?= PROJECT_URL ?>/admin/financeiro/wallet_request_review.php?id=<?= (int)$request['id'] ?>" method="POST" style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="reject">
                                                <button class="c-btn-secondary">Rejeitar</button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
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
