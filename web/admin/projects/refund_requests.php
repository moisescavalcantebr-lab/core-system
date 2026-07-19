<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$status = $_GET['status'] ?? 'pending';
$allowedStatus = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'pending';
}

$where = $status === 'all' ? '1=1' : 'rf.status = :status';
$sql = "
    SELECT
        rf.*,
        p.name AS project_name,
        p.slug AS project_slug,
        p.owner_email,
        b.name AS base_name,
        pl.name AS plan_name,
        pp.billing_cycle,
        r.receipt_path,
        r.reviewed_at AS upgrade_approved_at
    FROM plan_refund_requests rf
    INNER JOIN projects p ON p.id = rf.project_id
    INNER JOIN plans pl ON pl.id = rf.plan_id
    LEFT JOIN plan_prices pp ON pp.id = rf.plan_price_id
    LEFT JOIN bases b ON b.id = p.base_id
    LEFT JOIN plan_upgrade_requests r ON r.id = rf.upgrade_request_id
    WHERE {$where}
    ORDER BY FIELD(rf.status, 'pending', 'approved', 'rejected'), rf.requested_at DESC, rf.id DESC
";

$stmt = $pdo->prepare($sql);
if ($status !== 'all') {
    $stmt->execute(['status' => $status]);
} else {
    $stmt->execute();
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Solicitações de Reembolso';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Solicitações de Reembolso</h1>
            <p class="c-page-subtitle">Análise manual dos pedidos dentro do prazo de 7 dias</p>
        </div>

        <div class="c-actions">
            <a href="/web/admin/projects/upgrade_requests.php" class="c-btn-secondary">Upgrades</a>
            <a href="/web/admin/projects/index.php" class="c-btn-secondary">Projetos</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <form method="get" class="c-refund-filter">
                <div class="c-form-group">
                    <label>Status</label>
                    <select class="c-input" name="status">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Aprovadas</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejeitadas</option>
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todas</option>
                    </select>
                </div>
                <button class="c-btn-secondary">Filtrar</button>
            </form>
        </div>

        <div class="c-table-wrapper">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Plano</th>
                        <th>Valor</th>
                        <th>Motivo</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($request['project_name']) ?></strong>
                                <span><?= htmlspecialchars($request['project_slug']) ?></span>
                            </td>
                            <td>
                                <?= htmlspecialchars($request['plan_name']) ?>
                                <span><?= htmlspecialchars(($request['billing_cycle'] ?? '') === 'annual' ? 'Anual' : 'Mensal') ?></span>
                            </td>
                            <td>R$ <?= number_format((float)$request['amount'], 2, ',', '.') ?></td>
                            <td><?= nl2br(htmlspecialchars((string)$request['reason'])) ?></td>
                            <td>
                                <span class="c-badge c-badge--<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars(match ($request['status']) {
                                        'approved' => !empty($request['sent_at']) ? 'Enviado' : 'Aprovada',
                                        'rejected' => 'Rejeitada',
                                        default => 'Pendente',
                                    }) ?>
                                </span>
                                <?php if (!empty($request['sent_at'])): ?>
                                    <span><?= htmlspecialchars((string)$request['sent_at']) ?></span>
                                    <?php if (!empty($request['sent_receipt_path'])): ?>
                                        <a class="c-btn-secondary btn-sm" href="/<?= htmlspecialchars((string)$request['sent_receipt_path']) ?>" target="_blank" rel="noopener noreferrer">Comprovante</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)$request['requested_at']) ?></td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <form method="post" action="/app/actions/projects/refund_request_review.php" class="c-refund-actions">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                        <input class="c-input" name="review_notes" placeholder="Observação">
                                        <button class="c-btn-secondary" name="decision" value="approved">Aprovar</button>
                                        <button class="c-btn-secondary" name="decision" value="rejected">Rejeitar</button>
                                    </form>
                                <?php elseif ($request['status'] === 'approved' && empty($request['sent_at'])): ?>
                                    <form method="post" action="/app/actions/projects/refund_request_send.php" enctype="multipart/form-data" class="c-refund-send">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                        <input class="c-input" name="sent_notes" placeholder="Observação do envio">
                                        <input class="c-input" type="file" name="sent_receipt" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                        <button class="c-btn-secondary">Marcar como enviado</button>
                                    </form>
                                <?php else: ?>
                                    <?= htmlspecialchars((string)($request['review_notes'] ?? '-')) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7">Nenhuma solicitação encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.c-refund-filter {
    display: flex;
    gap: 10px;
    align-items: end;
}

.c-refund-filter .c-form-group {
    width: min(260px, 100%);
}

.c-table td span {
    display: block;
    color: var(--text-secondary);
    margin-top: 3px;
}

.c-refund-actions {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) auto auto;
    gap: 6px;
}

.c-refund-send {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) minmax(150px, 220px) auto;
    gap: 6px;
}

@media (max-width: 900px) {
    .c-refund-filter,
    .c-refund-actions,
    .c-refund-send {
        display: grid;
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
