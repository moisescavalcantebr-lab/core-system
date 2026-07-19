<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    http_response_code(404);
    exit('Base nao encontrada.');
}

$plans = $pdo->query("
    SELECT pp.*, p.name AS plan_name, p.billing_cycle AS plan_cycle, p.status AS plan_status
    FROM plan_prices pp
    INNER JOIN plans p ON p.id = pp.plan_id
    WHERE p.status = 1
      AND pp.status = 1
      AND (
          pp.billing_cycle = 'free'
          OR (LOWER(p.name) LIKE '%start%' AND pp.billing_cycle = 'monthly')
          OR (LOWER(p.name) LIKE '%plus%' AND pp.billing_cycle = 'annual')
          OR (LOWER(p.name) NOT LIKE '%start%' AND LOWER(p.name) NOT LIKE '%plus%' AND pp.billing_cycle IN ('monthly', 'annual'))
      )
    ORDER BY FIELD(p.billing_cycle, 'free', 'monthly', 'annual'), p.id ASC, FIELD(pp.billing_cycle, 'free', 'monthly', 'annual')
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM base_plan_prices WHERE base_id = ?");
$stmt->execute([$id]);
$linkedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$linked = [];
foreach ($linkedRows as $row) {
    $linked[(int)$row['plan_price_id']] = $row;
}

$title = 'Planos da Base';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Planos da Base</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$base['name']) ?></p>
        </div>

        <a href="/web/admin/bases/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="post" action="/app/actions/bases/plans_save.php" class="c-card">
            <?= csrf_field() ?>
            <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">

            <div class="c-table-wrapper">
                <table class="c-table">
                    <thead>
                        <tr>
                            <th>Usar</th>
                            <th>Plano</th>
                            <th>Ciclo</th>
                            <th>Valor padrão</th>
                            <th>Valor nessa base</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                            <?php
                                $planPriceId = (int)$plan['id'];
                                $isLinked = isset($linked[$planPriceId]) && (int)$linked[$planPriceId]['status'] === 1;
                                $isFree = (string)$plan['billing_cycle'] === 'free';
                                if ($isFree) {
                                    $isLinked = true;
                                }
                                $custom = $linked[$planPriceId]['custom_price'] ?? $plan['price'];
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="plan_price_ids[]" value="<?= $planPriceId ?>" <?= $isLinked ? 'checked' : '' ?> <?= $isFree ? 'disabled' : '' ?>>
                                    <?php if ($isFree): ?>
                                        <input type="hidden" name="plan_price_ids[]" value="<?= $planPriceId ?>">
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars((string)$plan['plan_name']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars(match ($plan['billing_cycle']) {
                                        'free' => 'Gratis',
                                        'monthly' => 'Mensal',
                                        'annual' => 'Anual',
                                        default => '-',
                                    }) ?>
                                </td>
                                <td>R$ <?= number_format((float)$plan['price'], 2, ',', '.') ?></td>
                                <td>
                                    <input class="c-input" type="number" step="0.01" min="0" name="custom_price[<?= $planPriceId ?>]" value="<?= htmlspecialchars((string)$custom) ?>" <?= $isFree ? 'readonly' : '' ?>>
                                    <?php if ($isFree): ?>
                                        <span class="c-badge c-badge--success">Obrigatório</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button class="c-btn-secondary">Salvar Planos da Base</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
