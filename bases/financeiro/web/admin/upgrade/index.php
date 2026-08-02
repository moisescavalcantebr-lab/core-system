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
$walletBalance = 0.0;
$error = null;

try {
    $corePdo = projectCorePdo();

    if (!$corePdo) {
        throw new RuntimeException('Core nao configurado.');
    }

    $coreProject = upgradeCoreProject($corePdo);

    if (!$coreProject) {
        throw new RuntimeException('Projeto nao encontrado no Core.');
    }

    $walletBalance = projectCoreWalletBalance($corePdo, (int)$coreProject['id']);
    $availablePlans = upgradeAvailablePlans($corePdo, (int)$coreProject['base_id']);
    $availableModules = upgradeAvailableModules($corePdo, $coreProject);
    $requests = upgradeRequests($corePdo, (int)$coreProject['id']);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$currentPlanName = (string)($coreProject['plan_name'] ?? ($project['plan_name'] ?? 'Plano Gratis'));
$currentRank = upgradeRank($currentPlanName);
$title = 'Upgrade';
$walletText = upgradeMoney($walletBalance);

ob_start();
?>

<div class="c-page c-upgrade-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Upgrade</h1>
            <p class="c-page-subtitle">Planos e recursos disponiveis para este projeto</p>
        </div>
        <div class="c-page-actions upgrade-header-actions">
            <div class="upgrade-credit-pill">
                <span>Credito disponivel</span>
                <strong><?= htmlspecialchars($walletText) ?></strong>
            </div>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if ($error): ?>
            <div class="c-card">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <div class="upgrade-grid">
                <?php foreach ($availablePlans as $planItem): ?>
                    <?php
                        $planName = (string)$planItem['name'];
                        $isCurrent = (int)$planItem['id'] === (int)($coreProject['plan_id'] ?? 0);
                        $isFree = (string)($planItem['billing_cycle'] ?? '') === 'free';
                        $isLowerOrSame = upgradeRank($planName) <= $currentRank;
                        $features = upgradePlanFeatures(
                            $planItem['limits_json'] ?? null,
                            (string)($coreProject['base_slug'] ?? ''),
                            $planName
                        );
                        $discountPercent = upgradePlanDiscountPercent($planItem['limits_json'] ?? null);
                        $includedModules = $isFree ? [] : upgradeAutoModules($availableModules);
                        $startingCycle = $isFree ? 'free' : 'monthly';
                        $startingPrice = upgradeTotalForCycle([
                            'billing_cycle' => $startingCycle,
                            'effective_price' => (float)$planItem['starting_price'],
                        ], $includedModules);
                        $priceCaption = $isFree
                            ? 'para comecar'
                            : 'mensal ou anual + modulos incluidos';
                    ?>
                    <section class="c-card upgrade-card <?= $isCurrent ? 'is-current' : '' ?>">
                        <div class="upgrade-card-head">
                            <div>
                                <span><?= $isCurrent ? 'Plano atual' : 'Disponivel' ?></span>
                                <h2><?= htmlspecialchars($planName) ?></h2>
                            </div>
                            <span class="c-badge c-badge--<?= $isCurrent ? 'success' : 'info' ?>">
                                <?= $isCurrent ? 'Ativo' : 'Upgrade' ?>
                            </span>
                        </div>

                        <div class="upgrade-price">
                            <strong><?= htmlspecialchars(upgradeMoney($startingPrice)) ?></strong>
                            <span><?= htmlspecialchars($priceCaption) ?></span>
                        </div>

                        <?php if ($discountPercent > 0): ?>
                            <div class="upgrade-discount">
                                <?= htmlspecialchars(number_format($discountPercent, 0, ',', '.')) ?>% de desconto no anual
                            </div>
                        <?php endif; ?>

                        <ul class="upgrade-list">
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
                            <a class="c-btn-secondary c-btn-block upgrade-action" href="<?= PROJECT_URL ?>/admin/upgrade/checkout.php?plan_id=<?= (int)$planItem['id'] ?>">
                                Fazer upgrade
                            </a>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>

            <div class="c-card">
                <h3>Historico recente</h3>
                <?php if (empty($requests)): ?>
                    <p>Nenhum upgrade realizado.</p>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <?php
                                        $cycle = (string)($request['billing_cycle'] ?? 'monthly');
                                        $approvedAt = (string)($request['reviewed_at'] ?: $request['created_at']);
                                        $expiresAt = $request['status'] === 'approved'
                                            ? date('Y-m-d', strtotime($approvedAt . ($cycle === 'annual' ? ' +365 days' : ' +30 days')))
                                            : null;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$request['plan_name']) ?> · <?= htmlspecialchars(upgradeCycleLabel($cycle)) ?></td>
                                        <td><?= htmlspecialchars(upgradeMoney((float)$request['amount'])) ?></td>
                                        <td>
                                            <span class="c-badge c-badge--<?= $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                                <?= htmlspecialchars(match ($request['status']) {
                                                    'approved' => 'Aprovado',
                                                    'rejected' => 'Rejeitado',
                                                    default => 'Pendente',
                                                }) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars((string)$request['created_at']) ?></td>
                                        <td><?= $expiresAt ? htmlspecialchars(date('d/m/Y', strtotime($expiresAt))) : '-' ?></td>
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
.upgrade-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.upgrade-header-actions {
    align-items: center;
}

.upgrade-credit-pill {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 12px;
    border: 1px solid rgba(139, 92, 246, .45);
    background: rgba(139, 92, 246, .08);
    color: var(--text-primary);
}

.upgrade-credit-pill span {
    color: var(--text-secondary);
    font-size: 11px;
}

.upgrade-credit-pill strong {
    font-size: 13px;
}

.upgrade-card {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.upgrade-card.is-current {
    border-color: var(--accent-color, #3b82f6);
}

.upgrade-card-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.upgrade-card-head span,
.upgrade-price span {
    color: var(--text-secondary);
    font-size: 12px;
}

.upgrade-card h2,
.upgrade-price strong {
    margin: 3px 0 0;
    font-size: 24px;
}

.upgrade-price {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 12px;
}

.upgrade-discount {
    border: 1px solid rgba(34, 197, 94, .35);
    background: rgba(34, 197, 94, .1);
    color: #86efac;
    padding: 10px 12px;
    font-weight: 800;
}

.upgrade-list {
    display: grid;
    gap: 8px;
    margin: 0 0 auto;
    padding: 0;
    list-style: none;
}

.upgrade-list li::before {
    content: "";
    display: inline-block;
    width: 7px;
    height: 7px;
    margin-right: 8px;
    border-radius: 50%;
    background: #22c55e;
}

.upgrade-action {
    display: block;
    text-align: center;
    margin-right: 0;
    font-weight: 800;
}

@media (max-width: 1000px) {
    .upgrade-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .upgrade-header-actions {
        width: 100%;
        justify-content: space-between;
    }

    .upgrade-credit-pill {
        flex: 1;
        justify-content: space-between;
        min-width: 0;
    }
}
</style>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;

$rightSidebarContent = '
<div class="c-card">
    <h3>Informações</h3>
    <p>Gerencie suas assinaturas e planos de upgrade.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
