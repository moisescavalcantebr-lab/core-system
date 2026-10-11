<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();
requireProjectAdmin();

require_once APP_PATH . '/helpers/core_bridge.php';

$corePdo = projectCorePdo();
$coreProjectId = (int)($project['id'] ?? 0);
$requests = projectCoreWalletRequests($corePdo, $coreProjectId, null);
$movements = projectCoreWalletMovements($corePdo, $coreProjectId, null);

function walletHistoryStatusBadge(string $status): string
{
    $class = $status === 'approved' || $status === 'applied' ? 'success' : ($status === 'rejected' || $status === 'canceled' ? 'danger' : 'warning');
    $label = [
        'pending' => 'Pendente',
        'approved' => 'Aprovada',
        'rejected' => 'Rejeitada',
        'applied' => 'Aplicada',
        'canceled' => 'Cancelada',
    ][$status] ?? $status;

    return '<span class="c-badge c-badge--' . $class . '">' . htmlspecialchars($label) . '</span>';
}

$title = 'Historico da Carteira';
ob_start();
?>

<div class="c-page wallet-history-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Historico da Carteira</h1>
            <p class="c-page-subtitle">Solicitacoes e movimentacoes de credito do projeto</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/saldo.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <section class="c-card">
            <h3>Solicitacoes de credito</h3>

            <?php if (empty($requests)): ?>
                <p>Nenhuma solicitacao enviada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Comprovante</th>
                                <th>Data</th>
                                <th>Revisao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?= htmlspecialchars(projectCoreWalletMoney((float)$request['amount'])) ?></td>
                                    <td><?= walletHistoryStatusBadge((string)$request['status']) ?></td>
                                    <td>
                                        <?php if (!empty($request['receipt_path'])): ?>
                                            <a class="c-btn-secondary" href="<?= htmlspecialchars((string)$request['receipt_path']) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$request['created_at']))) ?></td>
                                    <td><?= !empty($request['reviewed_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$request['reviewed_at']))) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="c-card">
            <h3>Movimentacoes da carteira</h3>

            <?php if (empty($movements)): ?>
                <p>Nenhuma movimentacao registrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Origem</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Descricao</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement): ?>
                                <tr>
                                    <td><?= ((string)$movement['movement_type'] === 'credit') ? 'Credito' : 'Debito' ?></td>
                                    <td><?= htmlspecialchars((string)$movement['source']) ?></td>
                                    <td><?= htmlspecialchars(projectCoreWalletMoney((float)$movement['amount'])) ?></td>
                                    <td><?= walletHistoryStatusBadge((string)$movement['status']) ?></td>
                                    <td><?= htmlspecialchars((string)($movement['description'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$movement['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<style>
.wallet-history-page .c-card h3 {
    margin-top: 0;
}

@media (max-width: 760px) {
    .wallet-history-page .c-table th,
    .wallet-history-page .c-table td {
        white-space: nowrap;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
