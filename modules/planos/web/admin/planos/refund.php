<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

$upgradeId = (int)($_GET['upgrade_id'] ?? $_POST['upgrade_id'] ?? 0);
$request = null;
$error = null;

try {
    $corePdo = planosCorePdo();
    $coreProject = planosCoreProject($corePdo);

    if (!$coreProject) {
        throw new RuntimeException('Projeto nao encontrado no Core.');
    }

    $stmt = $corePdo->prepare("
        SELECT r.*, pl.name AS plan_name, pp.billing_cycle, rf.id AS refund_id
        FROM plan_upgrade_requests r
        INNER JOIN plans pl ON pl.id = r.plan_id
        LEFT JOIN plan_prices pp ON pp.id = r.plan_price_id
        LEFT JOIN plan_refund_requests rf ON rf.upgrade_request_id = r.id
        WHERE r.id = ?
          AND r.project_id = ?
          AND r.status = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$upgradeId, (int)$coreProject['id']]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Upgrade aprovado nao encontrado.');
    }

    if (!empty($request['refund_id'])) {
        throw new RuntimeException('Reembolso ja solicitado para este upgrade.');
    }

    $approvedAt = (string)($request['reviewed_at'] ?: $request['created_at']);
    if (strtotime($approvedAt . ' +7 days') < time()) {
        throw new RuntimeException('O prazo de 7 dias para solicitar reembolso foi encerrado.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $reason = trim((string)($_POST['reason'] ?? ''));

        if ($reason === '') {
            throw new RuntimeException('Informe o motivo do reembolso.');
        }

        $stmt = $corePdo->prepare("
            INSERT INTO plan_refund_requests
            (upgrade_request_id, project_id, plan_id, plan_price_id, amount, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            (int)$request['id'],
            (int)$request['project_id'],
            (int)$request['plan_id'],
            !empty($request['plan_price_id']) ? (int)$request['plan_price_id'] : null,
            (float)$request['amount'],
            $reason,
        ]);

        $corePdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'refund_requested', ?, 'warning')
        ")->execute([
            (int)$request['project_id'],
            'Solicitacao de reembolso enviada para ' . $request['plan_name'],
        ]);

        flash('success', 'Solicitacao de reembolso enviada.');
        redirect(PROJECT_URL . '/admin/planos/index.php');
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$title = 'Solicitar Reembolso';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Solicitar Reembolso</h1>
            <p class="c-page-subtitle">Pedido vinculado ao upgrade aprovado</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/planos/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if ($error): ?>
            <div class="c-card"><p><?= htmlspecialchars($error) ?></p></div>
        <?php elseif ($request): ?>
            <form method="post" class="c-card planos-refund-form">
                <?= csrf_field() ?>
                <input type="hidden" name="upgrade_id" value="<?= (int)$request['id'] ?>">

                <div class="c-settings-grid">
                    <div class="c-form-group">
                        <label>Plano</label>
                        <strong><?= htmlspecialchars($request['plan_name']) ?> · <?= htmlspecialchars(planosCycleLabel((string)($request['billing_cycle'] ?? 'monthly'))) ?></strong>
                    </div>
                    <div class="c-form-group">
                        <label>Valor</label>
                        <strong><?= planosMoney((float)$request['amount']) ?></strong>
                    </div>
                    <div class="c-form-group">
                        <label>Prazo maximo</label>
                        <strong><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($request['reviewed_at'] ?: $request['created_at']) . ' +7 days'))) ?></strong>
                    </div>
                </div>

                <div class="c-form-group">
                    <label>Motivo</label>
                    <textarea class="c-input" name="reason" rows="5" required></textarea>
                </div>

                <button class="c-btn-secondary">Enviar solicitacao</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
.planos-refund-form {
    max-width: 860px;
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
