<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$status = (string)($_GET['status'] ?? 'pending');
$allowedStatus = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'pending';
}

$where = $status === 'all' ? '1=1' : 'wr.status = :status';
$stmt = $pdo->prepare("
    SELECT wr.*, p.name AS project_name, p.slug AS project_slug, p.owner_name, p.owner_email
    FROM project_wallet_requests wr
    INNER JOIN projects p ON p.id = wr.project_id
    WHERE $where
    ORDER BY FIELD(wr.status, 'pending', 'approved', 'rejected'), wr.created_at DESC, wr.id DESC
");
if ($status === 'all') {
    $stmt->execute();
} else {
    $stmt->execute(['status' => $status]);
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$projects = $pdo->query("
    SELECT p.id, p.name, p.slug, p.owner_name, p.owner_email, p.billing_status, p.status
    FROM projects p
    WHERE p.status <> 'deleted'
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$balances = coreWalletBalances($pdo, array_column($projects, 'id'));

$movements = $pdo->query("
    SELECT wm.*, p.name AS project_name, p.slug AS project_slug
    FROM project_wallet_movements wm
    INNER JOIN projects p ON p.id = wm.project_id
    ORDER BY wm.created_at DESC, wm.id DESC
    LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC);

$pendingTotal = (float)$pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM project_wallet_requests
    WHERE status = 'pending'
")->fetchColumn();

$availableTotal = (float)$pdo->query("
    SELECT COALESCE(SUM(
        CASE
            WHEN movement_type = 'credit' THEN amount
            WHEN movement_type = 'debit' THEN -amount
            ELSE 0
        END
    ), 0)
    FROM project_wallet_movements
    WHERE status = 'applied'
")->fetchColumn();

$title = 'Carteira dos Projetos';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Carteira dos Projetos</h1>
            <p class="c-page-subtitle">Carteira central usada para upgrades instantaneos</p>
        </div>

        <div class="c-page-actions">
            <a href="/web/admin/financeiro/index.php" class="c-btn-secondary">Financeiro</a>
            <a href="/web/admin/plans/payment.php" class="c-btn-secondary">Pix</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-dashboard-grid">
            <div class="c-dashboard-card">
                <h4>Credito disponivel</h4>
                <div class="c-metric"><?= coreWalletMoney($availableTotal) ?></div>
            </div>

            <div class="c-dashboard-card <?= $pendingTotal > 0 ? 'c-card--warning' : 'c-card--success' ?>">
                <h4>Creditos pendentes</h4>
                <div class="c-metric"><?= coreWalletMoney($pendingTotal) ?></div>
            </div>

            <div class="c-dashboard-card">
                <h4>Projetos com carteira</h4>
                <div class="c-metric"><?= count($projects) ?></div>
            </div>
        </div>

        <div class="c-card">
            <div class="c-section-header">
                <div>
                    <h3>Solicitacoes de credito</h3>
                    <p>Entradas aprovadas viram credito na carteira central do projeto.</p>
                </div>

                <form method="get" class="c-inline-form">
                    <select name="status" class="c-input" onchange="this.form.submit()">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Aprovadas</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejeitadas</option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todas</option>
                    </select>
                </form>
            </div>

            <?php if (empty($requests)): ?>
                <p>Nenhuma solicitacao encontrada.</p>
            <?php else: ?>
                <div class="c-table-responsive c-wallet-table-wrapper">
                    <table class="c-table c-wallet-table">
                        <thead>
                            <tr>
                                <th>Projeto</th>
                                <th>Solicitante</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td data-label="Projeto">
                                        <strong><?= htmlspecialchars((string)$request['project_name']) ?></strong>
                                        <small><?= htmlspecialchars((string)$request['project_slug']) ?></small>
                                    </td>
                                    <td data-label="Solicitante">
                                        <?= htmlspecialchars((string)($request['requested_by_name'] ?: $request['owner_name'])) ?>
                                        <small><?= htmlspecialchars((string)($request['requested_by_email'] ?: $request['owner_email'])) ?></small>
                                    </td>
                                    <td data-label="Valor"><?= coreWalletMoney((float)$request['amount']) ?></td>
                                    <td data-label="Status">
                                        <span class="c-badge c-badge--<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                            <?= htmlspecialchars([
                                                'pending' => 'Pendente',
                                                'approved' => 'Aprovada',
                                                'rejected' => 'Rejeitada',
                                            ][$request['status']] ?? (string)$request['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Data"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$request['created_at']))) ?></td>
                                    <td data-label="Acoes" class="c-wallet-actions">
                                        <?php if (!empty($request['receipt_path'])): ?>
                                            <a class="c-btn-secondary btn-sm" href="<?= htmlspecialchars((string)$request['receipt_path']) ?>" target="_blank">Comprovante</a>
                                        <?php endif; ?>

                                        <?php if ($request['status'] === 'pending'): ?>
                                            <form method="post" action="/app/actions/projects/wallet_request_review.php" class="c-inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                                <input type="hidden" name="decision" value="approved">
                                                <button class="c-btn-secondary btn-sm">Aprovar</button>
                                            </form>

                                            <form method="post" action="/app/actions/projects/wallet_request_review.php" class="c-inline-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                                <input type="hidden" name="decision" value="rejected">
                                                <button class="c-btn-danger btn-sm">Rejeitar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Carteira por projeto</h3>

            <?php if (empty($projects)): ?>
                <p>Nenhum projeto criado.</p>
            <?php else: ?>
                <div class="c-table-responsive c-wallet-table-wrapper">
                    <table class="c-table c-wallet-table">
                        <thead>
                            <tr>
                                <th>Projeto</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Carteira</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td data-label="Projeto">
                                        <strong><?= htmlspecialchars((string)$project['name']) ?></strong>
                                        <small><?= htmlspecialchars((string)$project['slug']) ?></small>
                                    </td>
                                    <td data-label="Cliente">
                                        <?= htmlspecialchars((string)$project['owner_name']) ?>
                                        <small><?= htmlspecialchars((string)$project['owner_email']) ?></small>
                                    </td>
                                    <td data-label="Status">
                                        <span class="c-badge c-badge--<?= $project['billing_status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= htmlspecialchars((string)$project['billing_status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Carteira"><?= coreWalletMoney((float)($balances[(int)$project['id']] ?? 0)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Movimentos recentes</h3>

            <?php if (empty($movements)): ?>
                <p>Nenhum movimento registrado.</p>
            <?php else: ?>
                <div class="c-table-responsive c-wallet-table-wrapper">
                    <table class="c-table c-wallet-table">
                        <thead>
                            <tr>
                                <th>Projeto</th>
                                <th>Tipo</th>
                                <th>Origem</th>
                                <th>Valor</th>
                                <th>Descricao</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement): ?>
                                <tr>
                                    <td data-label="Projeto"><?= htmlspecialchars((string)$movement['project_name']) ?></td>
                                    <td data-label="Tipo"><?= $movement['movement_type'] === 'credit' ? 'Credito' : 'Debito' ?></td>
                                    <td data-label="Origem"><?= htmlspecialchars((string)$movement['source']) ?></td>
                                    <td data-label="Valor"><?= coreWalletMoney((float)$movement['amount']) ?></td>
                                    <td data-label="Descricao"><?= htmlspecialchars((string)($movement['description'] ?? '')) ?></td>
                                    <td data-label="Data"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$movement['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-wallet-table td small {
    display: block;
    margin-top: 3px;
    color: var(--text-secondary);
    font-size: 11px;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.c-wallet-actions {
    min-width: 118px;
}

.c-wallet-actions .c-inline-form,
.c-wallet-actions .btn-sm {
    margin: 2px 0;
}

@media (max-width: 760px) {
    .c-page {
        min-width: 0;
    }

    .c-page-header,
    .c-section-header {
        align-items: stretch;
    }

    .c-page-actions,
    .c-section-header,
    .c-inline-form,
    .c-inline-form .c-input {
        width: 100%;
    }

    .c-wallet-table-wrapper {
        overflow: visible;
    }

    .c-wallet-table,
    .c-wallet-table thead,
    .c-wallet-table tbody,
    .c-wallet-table tr,
    .c-wallet-table td {
        display: block;
        width: 100%;
    }

    .c-wallet-table thead {
        display: none;
    }

    .c-wallet-table tr {
        padding: 10px 0;
        border-top: 1px solid var(--border-color);
    }

    .c-wallet-table tr:first-child {
        border-top: 0;
    }

    .c-wallet-table td {
        display: grid;
        grid-template-columns: 92px minmax(0, 1fr);
        gap: 10px;
        align-items: start;
        padding: 7px 0;
        border: 0;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .c-wallet-table td::before {
        content: attr(data-label);
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 700;
    }

    .c-wallet-actions {
        min-width: 0;
    }

    .c-wallet-actions .c-inline-form,
    .c-wallet-actions .btn-sm,
    .c-wallet-actions button {
        width: 100%;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
