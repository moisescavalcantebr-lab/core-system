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

$where = $status === 'all' ? '1=1' : 'r.status = :status';
$sql = "
    SELECT
        r.*,
        p.name AS project_name,
        p.slug AS project_slug,
        p.owner_email,
        p.base_id,
        b.name AS base_name,
        pl.name AS plan_name,
        COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle,
        COALESCE(pp.price, pl.price) AS price
    FROM plan_upgrade_requests r
    INNER JOIN projects p ON p.id = r.project_id
    INNER JOIN plans pl ON pl.id = r.plan_id
    LEFT JOIN plan_prices pp ON pp.id = r.plan_price_id
    LEFT JOIN bases b ON b.id = p.base_id
    WHERE {$where}
    ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.created_at DESC, r.id DESC
";

$stmt = $pdo->prepare($sql);
if ($status !== 'all') {
    $stmt->execute(['status' => $status]);
} else {
    $stmt->execute();
}
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Solicitações de Upgrade';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Solicitações de Upgrade</h1>
            <p class="c-page-subtitle">Conferência manual de Pix e ativação do plano</p>
        </div>

        <div class="c-actions">
            <a href="/web/admin/financeiro/index.php" class="c-btn-secondary">Voltar</a>
            <a href="/web/admin/projects/refund_requests.php" class="c-btn-secondary">Reembolsos</a>
            <a href="/web/admin/projects/index.php" class="c-btn-secondary">Projetos</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <form method="get" class="c-upgrade-filter">
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
                        <th>Base</th>
                        <th>Plano</th>
                        <th>Módulos</th>
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
                                <strong><?= htmlspecialchars($request['project_name']) ?></strong>
                                <span><?= htmlspecialchars($request['project_slug']) ?></span>
                            </td>
                            <td><?= htmlspecialchars((string)($request['base_name'] ?? '-')) ?></td>
                            <td>
                                <?= htmlspecialchars($request['plan_name']) ?>
                                <span><?= htmlspecialchars($request['billing_cycle'] === 'annual' ? 'Anual' : 'Mensal') ?></span>
                            </td>
                            <td>
                                <?php
                                    $requestedModules = json_decode((string)($request['requested_modules_json'] ?? ''), true);
                                    $requestedModules = is_array($requestedModules) ? $requestedModules : [];
                                ?>
                                <?php if (empty($requestedModules)): ?>
                                    -
                                <?php else: ?>
                                    <div class="c-module-tags">
                                        <?php foreach ($requestedModules as $module): ?>
                                            <span><?= htmlspecialchars((string)($module['label'] ?? $module['slug'] ?? 'Modulo')) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>R$ <?= number_format((float)$request['amount'], 2, ',', '.') ?></td>
                            <td>
                                <?php if (!empty($request['receipt_path'])): ?>
                                    <a class="c-btn-secondary btn-sm" href="/<?= htmlspecialchars($request['receipt_path']) ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="c-badge c-badge--<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                    <?= htmlspecialchars(match ($request['status']) {
                                        'approved' => 'Aprovada',
                                        'rejected' => 'Rejeitada',
                                        default => 'Pendente',
                                    }) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars((string)$request['created_at']) ?></td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <form method="post" action="/app/actions/projects/upgrade_request_review.php" class="c-upgrade-actions">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                        <input class="c-input" name="review_notes" placeholder="Observação">
                                        <button class="c-btn-secondary" name="decision" value="approved">Aprovar</button>
                                        <button class="c-btn-secondary" name="decision" value="rejected">Rejeitar</button>
                                    </form>
                                <?php else: ?>
                                    <?= htmlspecialchars((string)($request['review_notes'] ?? '-')) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($requests)): ?>
                        <tr><td colspan="9">Nenhuma solicitação encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.c-upgrade-filter {
    display: flex;
    gap: 10px;
    align-items: end;
}

.c-upgrade-filter .c-form-group {
    width: min(260px, 100%);
}

.c-table td span {
    display: block;
    color: var(--text-secondary);
    margin-top: 3px;
}

.c-upgrade-actions {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) auto auto;
    gap: 6px;
}

.c-module-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.c-module-tags span {
    display: inline-flex;
    margin: 0;
    padding: 4px 7px;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    background: var(--bg-card);
}

@media (max-width: 900px) {
    .c-upgrade-filter,
    .c-upgrade-actions {
        display: grid;
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
