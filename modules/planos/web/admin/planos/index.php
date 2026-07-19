<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

$coreProject = null;
$availablePlans = [];
$availableModules = [];
$requests = [];
$error = null;

try {
    $corePdo = planosCorePdo();
    $coreProject = planosCoreProject($corePdo);

    if (!$coreProject) {
        throw new RuntimeException('Projeto nao encontrado no Core.');
    }

    $availablePlans = planosAvailablePlans($corePdo, (int)$coreProject['base_id']);
    $availableModules = planosAvailableModules($corePdo, $coreProject);
    $requests = planosUpgradeRequests($corePdo, (int)$coreProject['id']);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$currentPlanName = (string)($coreProject['plan_name'] ?? ($project['plan_name'] ?? 'Plano Gratis'));
$currentRank = planosRank($currentPlanName);
$title = 'Planos';

ob_start();
?>

<div class="c-page c-planos-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Planos</h1>
            <p class="c-page-subtitle">Escolha o melhor nivel para este projeto</p>
        </div>
        <a href="<?= projectDashboardUrl() ?>" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if ($error): ?>
            <div class="c-card">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <div class="planos-grid">
                <?php foreach ($availablePlans as $planItem): ?>
                    <?php
                        $planName = (string)$planItem['name'];
                        $isCurrent = (int)$planItem['id'] === (int)($coreProject['plan_id'] ?? 0);
                        $isFree = (string)($planItem['billing_cycle'] ?? '') === 'free';
                        $isLowerOrSame = planosRank($planName) <= $currentRank;
                        $features = planosPlanFeatures($planItem['limits_json'] ?? null);
                        $includedModules = $isFree ? [] : planosAutoModules($availableModules);
                        $startingCycle = $isFree ? 'free' : (str_contains(strtolower($planName), 'plus') ? 'annual' : 'monthly');
                        $startingPrice = planosTotalForCycle([
                            'billing_cycle' => $startingCycle,
                            'effective_price' => (float)$planItem['starting_price'],
                        ], $includedModules);
                        $priceCaption = $isFree
                            ? 'para comecar'
                            : (str_contains(strtolower($planName), 'plus') ? 'anual + modulos incluidos' : 'mensal + modulos incluidos');
                    ?>
                    <section class="c-card planos-card <?= $isCurrent ? 'is-current' : '' ?>">
                        <div class="planos-card-head">
                            <div>
                                <span><?= $isCurrent ? 'Plano atual' : 'Disponivel' ?></span>
                                <h2><?= htmlspecialchars($planName) ?></h2>
                            </div>
                            <span class="c-badge c-badge--<?= $isCurrent ? 'success' : 'info' ?>">
                                <?= $isCurrent ? 'Ativo' : 'Upgrade' ?>
                            </span>
                        </div>

                        <div class="planos-price">
                            <strong><?= planosMoney($startingPrice) ?></strong>
                            <span><?= htmlspecialchars($priceCaption) ?></span>
                        </div>

                        <ul class="planos-list">
                            <?php if (empty($features)): ?>
                                <li>Recursos conforme configuracao do plano.</li>
                            <?php else: ?>
                                <?php foreach ($features as $feature): ?>
                                    <li><?= htmlspecialchars($feature) ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>

                        <?php if ($isCurrent): ?>
                            <button class="c-btn-secondary" disabled>Plano atual</button>
                        <?php elseif ($isLowerOrSame || $isFree): ?>
                            <button class="c-btn-secondary" disabled>Indisponivel</button>
                        <?php else: ?>
                            <a class="c-btn-secondary c-btn-block planos-action" href="<?= PROJECT_URL ?>/admin/planos/checkout.php?plan_id=<?= (int)$planItem['id'] ?>">
                                Solicitar upgrade
                            </a>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <div class="c-card">
                <h3>Solicitacoes recentes</h3>
                <?php if (empty($requests)): ?>
                    <p>Nenhuma solicitacao enviada.</p>
                <?php else: ?>
                    <div class="c-table-wrapper">
                        <table class="c-table">
                            <thead>
                                <tr>
                                    <th>Plano</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Data</th>
                                    <th>Expira em</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <?php
                                        $approvedAt = (string)($request['reviewed_at'] ?: $request['created_at']);
                                        $cycle = (string)($request['billing_cycle'] ?? 'monthly');
                                        $expiresAt = $request['status'] === 'approved'
                                            ? date('Y-m-d', strtotime($approvedAt . ($cycle === 'annual' ? ' +365 days' : ' +30 days')))
                                            : null;
                                        $refundLimit = $request['status'] === 'approved' ? strtotime($approvedAt . ' +7 days') : false;
                                        $canRefund = $request['status'] === 'approved' && empty($request['refund_id']) && $refundLimit !== false && $refundLimit >= time();
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$request['plan_name']) ?> · <?= htmlspecialchars(planosCycleLabel($cycle)) ?></td>
                                        <td><?= planosMoney((float)$request['amount']) ?></td>
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
                                        <td><?= $expiresAt ? htmlspecialchars(date('d/m/Y', strtotime($expiresAt))) : '-' ?></td>
                                        <td>
                                            <?php if ($canRefund): ?>
                                                <a class="c-btn-secondary btn-sm" href="<?= PROJECT_URL ?>/admin/planos/refund.php?upgrade_id=<?= (int)$request['id'] ?>">Solicitar reembolso</a>
                                            <?php elseif (!empty($request['refund_status'])): ?>
                                                <span class="c-badge c-badge--neutral">Reembolso <?= htmlspecialchars((string)$request['refund_status']) ?></span>
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
        <?php endif; ?>
    </div>
</div>

<style>
.planos-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.planos-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.planos-card.is-current {
    border-color: var(--accent-color, #3b82f6);
}

.planos-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.planos-card-head span,
.planos-price span {
    color: var(--text-secondary);
    font-size: 12px;
}

.planos-card h2,
.planos-price strong {
    margin: 3px 0 0;
    font-size: 24px;
}

.planos-price {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 12px;
}

.planos-list {
    display: grid;
    gap: 8px;
    margin: 0 0 auto;
    padding: 0;
    list-style: none;
}

.planos-list li::before {
    content: "";
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 8px;
    border-radius: 50%;
    background: #22c55e;
}

.planos-action {
    display: block;
    text-align: center;
    margin-right: 0;
    font-weight: 800;
}

@media (max-width: 1000px) {
    .planos-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
