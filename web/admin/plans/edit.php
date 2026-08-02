<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    http_response_code(404);
    exit('Plano nao encontrado.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM plan_prices
    WHERE plan_id = ?
      AND (
          billing_cycle = 'free'
          OR (? = 1 AND billing_cycle IN ('monthly', 'annual'))
          OR (? = 0 AND billing_cycle IN ('monthly', 'annual'))
      )
    ORDER BY FIELD(billing_cycle, 'free', 'monthly', 'annual')
");
$planKeyForPrices = strtolower((string)$plan['name']);
$isStartForPrices = str_contains($planKeyForPrices, 'start') ? 1 : 0;
$stmt->execute([$id, $isStartForPrices, $isStartForPrices]);
$priceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$prices = [];
foreach ($priceRows as $priceRow) {
    $prices[(string)$priceRow['billing_cycle']] = $priceRow;
}

$isFree = ($plan['billing_cycle'] ?? '') === 'free';
$planKey = strtolower((string)$plan['name']);
$isStart = str_contains($planKey, 'start');
$isFixedPlan = $isFree || $isStart;
$fixedCycleLabel = $isFree ? 'Gratis' : ($isStart ? 'Mensal e anual' : 'Personalizado');
$limits = json_decode((string)($plan['limits_json'] ?? ''), true);
$limits = is_array($limits) ? $limits : [];
$annualDiscountPercent = (float)($limits['annual_discount_percent'] ?? 0);
$title = 'Editar Plano';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Editar Plano</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$plan['name']) ?></p>
        </div>

        <a href="/web/admin/plans/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="post" action="/app/actions/plans/update.php" class="c-card">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">

            <?php if ($isFixedPlan): ?>
                <div class="c-plan-fixed-note">
                    <strong>Plano fixo</strong>
                    <span><?= htmlspecialchars((string)$plan['name']) ?> usa <?= $isStart ? 'os ciclos' : 'o ciclo' ?> <?= htmlspecialchars($fixedCycleLabel) ?>.</span>
                </div>
            <?php endif; ?>

            <div class="c-plan-form-grid">
                <div class="c-form-group">
                    <label>Nome</label>
                    <input class="c-input" name="name" value="<?= htmlspecialchars((string)$plan['name']) ?>" required>
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select class="c-input" name="status" <?= $isFree ? 'disabled' : '' ?>>
                        <option value="1" <?= (int)$plan['status'] === 1 ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= (int)$plan['status'] === 0 ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <?php if ($isFree): ?>
                        <input type="hidden" name="status" value="1">
                    <?php endif; ?>
                </div>
            </div>

            <div class="c-card c-plan-price-box">
                <h3>Valores do plano</h3>

                <?php if ($isFree): ?>
                    <div class="c-plan-price-grid">
                        <div class="c-form-group">
                            <label>Gratis</label>
                            <input class="c-input" value="R$ 0,00" disabled>
                            <input type="hidden" name="free_enabled" value="1">
                            <input type="hidden" name="free_price" value="0">
                        </div>
                    </div>
                <?php else: ?>
                    <div class="c-plan-price-grid">
                        <div class="c-form-group">
                            <label><?= $isStart ? 'Valor mensal' : 'Mensal' ?></label>
                            <input class="c-input" type="number" step="0.01" min="0" name="monthly_price" value="<?= htmlspecialchars((string)($prices['monthly']['price'] ?? '0.00')) ?>">
                            <?php if ($isStart): ?>
                                <input type="hidden" name="monthly_enabled" value="1">
                                <p class="c-plan-cycle-note">Ciclo fixo do Plano Start.</p>
                            <?php else: ?>
                                <label class="c-check-line">
                                    <input type="checkbox" name="monthly_enabled" value="1" <?= (int)($prices['monthly']['status'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    Disponivel
                                </label>
                            <?php endif; ?>
                        </div>

                        <div class="c-form-group">
                            <label><?= $isStart ? 'Valor anual' : 'Anual' ?></label>
                            <input class="c-input" type="number" step="0.01" min="0" name="annual_price" value="<?= htmlspecialchars((string)($prices['annual']['price'] ?? '0.00')) ?>">
                            <?php if ($isStart): ?>
                                <input type="hidden" name="annual_enabled" value="1">
                                <p class="c-plan-cycle-note">Ciclo anual do Plano Start.</p>
                            <?php else: ?>
                                <label class="c-check-line">
                                    <input type="checkbox" name="annual_enabled" value="1" <?= (int)($prices['annual']['status'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    Disponivel
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isStart): ?>
                    <div class="c-plan-discount-grid">
                        <div class="c-form-group">
                            <label>Desconto anual em destaque (%)</label>
                            <input class="c-input" type="number" step="0.01" min="0" max="100" name="annual_discount_percent" value="<?= htmlspecialchars((string)$annualDiscountPercent) ?>">
                            <p class="c-plan-cycle-note">Mostrado no Start anual para comparar com o pagamento mensal.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-form-group">
                <label>Limites/recursos em JSON</label>
                <textarea class="c-input" name="limits_json" rows="8"><?= htmlspecialchars((string)($plan['limits_json'] ?? '')) ?></textarea>
            </div>

            <button class="c-btn-secondary">Salvar Plano</button>
        </form>
    </div>
</div>

<style>
.c-plan-form-grid,
.c-plan-price-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.c-plan-price-box {
    margin: 14px 0;
}

.c-plan-discount-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr);
    gap: 12px;
    margin-top: 12px;
}

.c-plan-fixed-note {
    border: 1px solid rgba(59, 130, 246, .32);
    background: rgba(59, 130, 246, .08);
    margin-bottom: 14px;
    padding: 12px;
}

.c-plan-fixed-note strong,
.c-plan-fixed-note span {
    display: block;
}

.c-plan-fixed-note span,
.c-plan-cycle-note {
    color: var(--muted);
}

.c-plan-cycle-note {
    margin: 8px 0 0;
}

.c-check-line {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    color: var(--text-secondary);
}

@media (max-width: 760px) {
    .c-plan-form-grid,
    .c-plan-price-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
