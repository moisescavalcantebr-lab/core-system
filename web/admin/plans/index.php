<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$plans = $pdo->query("
    SELECT
        p.*,
        GROUP_CONCAT(
            CASE
                WHEN pp.id IS NULL THEN NULL
                WHEN pp.billing_cycle = 'free' THEN CONCAT(pp.billing_cycle, ':', FORMAT(pp.price, 2), ':', pp.status)
                WHEN LOWER(p.name) LIKE '%start%' AND pp.billing_cycle = 'monthly' THEN CONCAT(pp.billing_cycle, ':', FORMAT(pp.price, 2), ':', pp.status)
                WHEN LOWER(p.name) LIKE '%plus%' AND pp.billing_cycle = 'annual' THEN CONCAT(pp.billing_cycle, ':', FORMAT(pp.price, 2), ':', pp.status)
                WHEN LOWER(p.name) NOT LIKE '%start%' AND LOWER(p.name) NOT LIKE '%plus%' AND pp.billing_cycle IN ('monthly', 'annual') THEN CONCAT(pp.billing_cycle, ':', FORMAT(pp.price, 2), ':', pp.status)
                ELSE NULL
            END
            ORDER BY FIELD(pp.billing_cycle, 'free', 'monthly', 'annual')
            SEPARATOR '|'
        ) AS price_summary
    FROM plans p
    LEFT JOIN plan_prices pp ON pp.plan_id = p.id
    GROUP BY p.id
    ORDER BY FIELD(p.billing_cycle, 'free', 'monthly', 'annual'), p.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

function corePlanCycleAllowed(string $planName, string $cycle): bool
{
    $key = strtolower($planName);

    if (str_contains($key, 'gratis') || str_contains($key, 'grátis')) {
        return $cycle === 'free';
    }

    if (str_contains($key, 'start')) {
        return $cycle === 'monthly';
    }

    if (str_contains($key, 'plus')) {
        return $cycle === 'annual';
    }

    return true;
}

function corePlanProtected(string $planName): bool
{
    $key = strtolower($planName);

    return str_contains($key, 'gratis')
        || str_contains($key, 'grátis')
        || str_contains($key, 'start')
        || str_contains($key, 'plus');
}

$title = 'Planos';
ob_start();
?>

<div class="c-page c-plans-admin">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Planos</h1>
            <p class="c-page-subtitle">Modelos de plano e valores disponíveis no core</p>
        </div>

        <div class="c-page-actions">
            <a href="/web/admin/financeiro/saldo.php" class="c-btn-secondary">Carteira</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card c-plan-model">
            <strong>Modelo fixo</strong>
            <p>O Core trabalha com três planos: Grátis, Start mensal e Plus anual. O valor final pode somar módulos configurados na base.</p>
        </div>

        <div class="c-table-wrapper c-plans-table-wrapper">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Valores</th>
                        <th>Status</th>
                        <th style="text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                            $isFree = ($plan['billing_cycle'] ?? '') === 'free';
                            $prices = array_filter(explode('|', (string)($plan['price_summary'] ?? '')));
                            $limits = json_decode((string)($plan['limits_json'] ?? ''), true);
                            $discountPercent = is_array($limits) ? (float)($limits['annual_discount_percent'] ?? 0) : 0.0;
                        ?>
                        <tr>
                            <td data-label="Nome"><strong><?= htmlspecialchars($plan['name']) ?></strong></td>
                            <td data-label="Valores">
                                <div class="c-plan-prices">
                                    <?php foreach ($prices as $priceRow): ?>
                                        <?php [$cycle, $value, $active] = array_pad(explode(':', $priceRow), 3, '0'); ?>
                                        <?php if (!corePlanCycleAllowed((string)$plan['name'], (string)$cycle)) { continue; } ?>
                                        <span class="c-badge c-badge--<?= (int)$active === 1 ? 'info' : 'neutral' ?>">
                                            <?= htmlspecialchars(match ($cycle) {
                                                'free' => 'Gratis',
                                                'monthly' => 'Mensal',
                                                'annual' => 'Anual',
                                                default => $cycle,
                                            }) ?> R$ <?= htmlspecialchars(str_replace('.', ',', $value)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if ($discountPercent > 0): ?>
                                        <span class="c-badge c-badge--success">
                                            <?= htmlspecialchars(number_format($discountPercent, 0, ',', '.')) ?>% desconto anual
                                        </span>
                                    <?php endif; ?>
                                    <?php if (empty($prices)): ?>
                                        <span class="c-badge c-badge--warning">Sem valores</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span class="c-badge c-badge--<?= (int)$plan['status'] === 1 ? 'success' : 'danger' ?>">
                                    <?= (int)$plan['status'] === 1 ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td data-label="Ações" class="c-plans-row-actions" style="text-align:right;">
                                <a class="c-btn-secondary btn-sm" href="/web/admin/plans/edit.php?id=<?= (int)$plan['id'] ?>">Editar</a>
                                <?php if (!corePlanProtected((string)$plan['name'])): ?>
                                    <form method="post" action="/app/actions/plans/delete.php" style="display:inline;" onsubmit="return confirm('Excluir plano?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
                                        <button class="c-btn-secondary btn-sm">Excluir</button>
                                    </form>
                                <?php else: ?>
                                    <span class="c-badge c-badge--warning">Protegido</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($plans)): ?>
                        <tr><td colspan="4">Nenhum plano cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.c-plan-model {
    border-color: rgba(59, 130, 246, .32);
    background: rgba(59, 130, 246, .08);
}

.c-plan-model p {
    color: var(--muted);
    margin: 6px 0 0;
}

.c-plan-prices {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

@media (max-width: 760px) {
    .c-plans-admin .c-page-header {
        align-items: flex-start;
    }

    .c-plan-model {
        padding: 14px;
    }

    .c-plans-table-wrapper {
        overflow: visible;
        background: transparent;
        box-shadow: none;
    }

    .c-plans-table-wrapper table,
    .c-plans-table-wrapper thead,
    .c-plans-table-wrapper tbody,
    .c-plans-table-wrapper tr,
    .c-plans-table-wrapper td {
        display: block;
        width: 100%;
    }

    .c-plans-table-wrapper thead {
        display: none;
    }

    .c-plans-table-wrapper tr {
        display: grid;
        grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
        gap: 10px;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }

    .c-plans-table-wrapper td {
        padding: 0;
        border: 0;
        min-width: 0;
    }

    .c-plans-table-wrapper td::before {
        content: attr(data-label);
        display: block;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .c-plans-row-actions {
        grid-column: 1 / -1;
        text-align: left !important;
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .c-plans-row-actions::before {
        grid-column: 1 / -1;
    }

    .c-plans-row-actions .btn-sm,
    .c-plans-row-actions .c-badge,
    .c-plans-row-actions form,
    .c-plans-row-actions button {
        width: 100%;
        justify-content: center;
        min-height: 38px;
        margin: 0;
    }
}

@media (max-width: 420px) {
    .c-plans-table-wrapper tr,
    .c-plans-row-actions {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
