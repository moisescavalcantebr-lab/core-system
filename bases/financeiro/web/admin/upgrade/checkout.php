<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

$planId = (int)($_GET['plan_id'] ?? 0);
$coreProject = null;
$plan = null;
$prices = [];
$autoModules = [];
$selectedPrice = null;
$checkoutTotal = 0.0;
$walletBalance = 0.0;
$selectedBasePlanPriceId = (int)($_GET['base_plan_price_id'] ?? 0);
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

    $stmt = $corePdo->prepare("SELECT * FROM plans WHERE id = ? AND status = 1 LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$plan || upgradeRank((string)$plan['name']) <= upgradeRank((string)$coreProject['plan_name'])) {
        throw new RuntimeException('Plano indisponivel para upgrade.');
    }

    $prices = upgradePlanPrices($corePdo, (int)$coreProject['base_id'], $planId);
    $availableModules = upgradeAvailableModules($corePdo, $coreProject);
    $autoModules = upgradeAutoModules($availableModules);
    foreach ($prices as $priceOption) {
        if ((int)$priceOption['base_plan_price_id'] === $selectedBasePlanPriceId) {
            $selectedPrice = $priceOption;
            break;
        }
    }

    $selectedPrice = $selectedPrice ?: ($prices[0] ?? null);

    if ($selectedPrice) {
        $checkoutTotal = upgradeTotalForCycle($selectedPrice, $autoModules);
    }

    $walletBalance = projectCoreWalletBalance($corePdo, (int)$coreProject['id']);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$title = 'Confirmar Upgrade';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Confirmar Upgrade</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)($plan['name'] ?? 'Plano')) ?></p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/upgrade/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if ($error): ?>
            <div class="c-card"><p><?= htmlspecialchars($error) ?></p></div>
        <?php elseif ($plan && !empty($selectedPrice)): ?>
            <?php $hasEnoughBalance = $walletBalance + 0.0001 >= $checkoutTotal; ?>
            <form method="post" action="<?= PROJECT_URL ?>/admin/upgrade/request.php" class="c-card upgrade-checkout">
                <?= csrf_field() ?>
                <input type="hidden" name="base_plan_price_id" value="<?= (int)$selectedPrice['base_plan_price_id'] ?>">

                <?php if (count($prices) > 1): ?>
                    <div class="upgrade-cycle-picker" aria-label="Escolha a forma de pagamento">
                        <?php foreach ($prices as $priceOption): ?>
                            <?php
                                $optionTotal = upgradeTotalForCycle($priceOption, $autoModules);
                                $isSelected = (int)$priceOption['base_plan_price_id'] === (int)$selectedPrice['base_plan_price_id'];
                                $checkoutUrl = PROJECT_URL . '/admin/upgrade/checkout.php?plan_id=' . (int)$planId . '&base_plan_price_id=' . (int)$priceOption['base_plan_price_id'];
                            ?>
                            <a class="upgrade-cycle-option <?= $isSelected ? 'is-selected' : '' ?>" href="<?= htmlspecialchars($checkoutUrl) ?>">
                                <span><?= htmlspecialchars(upgradeCycleLabel((string)$priceOption['billing_cycle'])) ?></span>
                                <strong><?= htmlspecialchars(upgradeMoney($optionTotal)) ?></strong>
                                <?php if ((string)$priceOption['billing_cycle'] === 'annual'): ?>
                                    <small>Melhor custo no ano</small>
                                <?php else: ?>
                                    <small>Pagamento mensal</small>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="upgrade-checkout-head">
                    <div>
                        <span><?= htmlspecialchars(upgradeCycleLabel((string)$selectedPrice['billing_cycle'])) ?></span>
                        <h2><?= htmlspecialchars((string)$plan['name']) ?></h2>
                    </div>
                    <strong><?= htmlspecialchars(upgradeMoney($checkoutTotal)) ?></strong>
                </div>

                <div class="c-card upgrade-breakdown">
                    <h3>Resumo do pagamento</h3>
                    <div class="upgrade-line">
                        <span>Valor base do plano</span>
                        <strong><?= htmlspecialchars(upgradeMoney((float)$selectedPrice['effective_price'])) ?></strong>
                    </div>
                    <?php foreach ($autoModules as $module): ?>
                        <div class="upgrade-line">
                            <span><?= htmlspecialchars((string)$module['label']) ?></span>
                            <strong><?= htmlspecialchars(upgradeMoney(upgradeModulePriceForCycle($module, (string)$selectedPrice['billing_cycle']))) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="upgrade-line upgrade-line-total">
                        <span>Total</span>
                        <strong><?= htmlspecialchars(upgradeMoney($checkoutTotal)) ?></strong>
                    </div>
                </div>

                <div class="c-card upgrade-balance <?= $hasEnoughBalance ? 'is-ok' : 'is-low' ?>">
                    <div>
                        <span>Credito disponivel</span>
                        <strong><?= htmlspecialchars(upgradeMoney($walletBalance)) ?></strong>
                    </div>
                    <small><?= $hasEnoughBalance ? 'Credito suficiente para confirmar este upgrade.' : 'Credito insuficiente. Adicione credito antes de confirmar.' ?></small>
                </div>

                <?php if ($hasEnoughBalance): ?>
                    <button class="c-btn-primary">Confirmar upgrade</button>
                    <p class="upgrade-muted">O valor sera debitado da carteira e o plano sera atualizado automaticamente.</p>
                <?php else: ?>
                    <a class="c-btn-secondary upgrade-add-balance" href="<?= PROJECT_URL ?>/admin/saldo.php">Adicionar credito</a>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="c-card"><p>Nenhum valor mensal ou anual foi liberado para este plano nesta base.</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
.upgrade-checkout {
    max-width: 760px;
}

.upgrade-cycle-picker {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}

.upgrade-cycle-option {
    border: 1px solid var(--border-color);
    background: rgba(15, 23, 42, .32);
    color: var(--text-primary);
    display: grid;
    gap: 4px;
    padding: 12px;
    text-decoration: none;
}

.upgrade-cycle-option.is-selected {
    border-color: rgba(59, 130, 246, .7);
    background: rgba(59, 130, 246, .12);
}

.upgrade-cycle-option span,
.upgrade-cycle-option small {
    color: var(--text-secondary);
}

.upgrade-cycle-option strong {
    font-size: 20px;
}

.upgrade-checkout-head,
.upgrade-line,
.upgrade-balance {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}

.upgrade-checkout-head span,
.upgrade-line span,
.upgrade-balance span,
.upgrade-balance small,
.upgrade-muted {
    color: var(--text-secondary);
}

.upgrade-checkout-head h2 {
    margin: 3px 0 0;
}

.upgrade-checkout-head > strong {
    font-size: 28px;
}

.upgrade-breakdown,
.upgrade-balance {
    margin: 12px 0;
}

.upgrade-line {
    border-top: 1px solid var(--border-color);
    padding: 10px 0;
}

.upgrade-line-total strong {
    font-size: 20px;
}

.upgrade-balance {
    border: 1px solid var(--border-color);
    padding: 12px;
}

.upgrade-balance strong,
.upgrade-balance small {
    display: block;
}

.upgrade-balance.is-ok {
    border-color: rgba(34, 197, 94, .4);
    background: rgba(34, 197, 94, .08);
}

.upgrade-balance.is-low {
    border-color: rgba(245, 158, 11, .45);
    background: rgba(245, 158, 11, .08);
}

.upgrade-add-balance {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
    text-align: center;
    text-decoration: none;
    width: fit-content;
}

@media (max-width: 760px) {
    .upgrade-cycle-picker {
        grid-template-columns: 1fr;
    }

    .upgrade-checkout-head,
    .upgrade-line,
    .upgrade-balance {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
