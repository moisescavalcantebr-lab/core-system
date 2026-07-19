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
$availableModules = [];
$autoModules = [];
$selectedPrice = null;
$checkoutTotal = 0.0;
$allocatedBalance = 0.0;
$error = null;

try {
    $corePdo = planosCorePdo();
    $coreProject = planosCoreProject($corePdo);

    if (!$coreProject) {
        throw new RuntimeException('Projeto nao encontrado no Core.');
    }

    $stmt = $corePdo->prepare("SELECT * FROM plans WHERE id = ? AND status = 1 LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$plan || planosRank((string)$plan['name']) <= planosRank((string)$coreProject['plan_name'])) {
        throw new RuntimeException('Plano indisponivel para upgrade.');
    }

    $prices = planosPlanPrices($corePdo, (int)$coreProject['base_id'], $planId);
    $availableModules = planosAvailableModules($corePdo, $coreProject);
    $autoModules = planosAutoModules($availableModules);
    $selectedPrice = $prices[0] ?? null;

    if ($selectedPrice) {
        $checkoutTotal = planosTotalForCycle($selectedPrice, $autoModules);
    }

    $allocatedBalance = planosAllocatedBalance($pdo);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$title = 'Solicitar Upgrade';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Solicitar Upgrade</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)($plan['name'] ?? 'Plano')) ?></p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/planos/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if ($error): ?>
            <div class="c-card"><p><?= htmlspecialchars($error) ?></p></div>
        <?php elseif ($plan && !empty($selectedPrice)): ?>
            <?php $hasEnoughBalance = $allocatedBalance + 0.0001 >= $checkoutTotal; ?>
            <form method="post" action="<?= PROJECT_URL ?>/admin/planos/request.php" class="c-card planos-checkout">
                <?= csrf_field() ?>
                <input type="hidden" name="base_plan_price_id" value="<?= (int)$selectedPrice['base_plan_price_id'] ?>">

                <div class="planos-checkout-head">
                    <div>
                        <span><?= htmlspecialchars(planosCycleLabel((string)$selectedPrice['billing_cycle'])) ?></span>
                        <h2><?= htmlspecialchars((string)$plan['name']) ?></h2>
                    </div>
                    <strong><?= planosMoney($checkoutTotal) ?></strong>
                </div>

                <div class="c-card planos-breakdown">
                    <h3>Resumo do pagamento</h3>
                    <div class="planos-line">
                        <span>Valor base do plano</span>
                        <strong><?= planosMoney((float)$selectedPrice['effective_price']) ?></strong>
                    </div>
                    <?php foreach ($autoModules as $module): ?>
                        <div class="planos-line">
                            <span><?= htmlspecialchars((string)$module['label']) ?></span>
                            <strong><?= planosMoney(planosModulePriceForCycle($module, (string)$selectedPrice['billing_cycle'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="planos-line planos-line-total">
                        <span>Total</span>
                        <strong><?= planosMoney($checkoutTotal) ?></strong>
                    </div>
                </div>

                <div class="c-card planos-balance <?= $hasEnoughBalance ? 'is-ok' : 'is-low' ?>">
                    <div>
                        <span>Saldo para pagamentos</span>
                        <strong><?= planosMoney($allocatedBalance) ?></strong>
                    </div>
                    <small><?= $hasEnoughBalance ? 'Saldo suficiente para confirmar este upgrade.' : 'Saldo insuficiente. Adicione saldo antes de confirmar.' ?></small>
                </div>

                <?php if ($hasEnoughBalance): ?>
                    <button class="c-btn-primary">Confirmar upgrade</button>
                    <p class="planos-muted">O valor sera debitado do saldo para pagamentos e o plano sera atualizado automaticamente.</p>
                <?php else: ?>
                    <a class="c-btn-primary planos-add-balance" href="<?= PROJECT_URL ?>/admin/financeiro/meu_saldo.php">Adicionar saldo</a>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="c-card"><p>Nenhum valor mensal ou anual foi liberado para este plano nesta base.</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
.planos-checkout {
    max-width: 760px;
}

.planos-checkout-head,
.planos-line,
.planos-balance {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}

.planos-checkout-head span,
.planos-line span,
.planos-balance span,
.planos-balance small,
.planos-muted {
    color: var(--text-secondary);
}

.planos-checkout-head h2 {
    margin: 3px 0 0;
}

.planos-checkout-head > strong {
    font-size: 28px;
}

.planos-breakdown,
.planos-balance {
    margin: 12px 0;
}

.planos-line {
    border-top: 1px solid var(--border-color);
    padding: 10px 0;
}

.planos-line-total strong {
    font-size: 20px;
}

.planos-balance {
    border: 1px solid var(--border-color);
    padding: 12px;
}

.planos-balance strong,
.planos-balance small {
    display: block;
}

.planos-balance.is-ok {
    border-color: rgba(34, 197, 94, .4);
    background: rgba(34, 197, 94, .08);
}

.planos-balance.is-low {
    border-color: rgba(245, 158, 11, .45);
    background: rgba(245, 158, 11, .08);
}

.planos-add-balance {
    display: inline-block;
    text-align: center;
}

@media (max-width: 760px) {
    .planos-checkout-head,
    .planos-line,
    .planos-balance {
        align-items: flex-start;
        grid-template-columns: 1fr;
        flex-direction: column;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
