<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, b.name AS base_name, pl.name AS plan_name, COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle
    FROM projects p
    LEFT JOIN bases b ON b.id = p.base_id
    LEFT JOIN plans pl ON pl.id = p.plan_id
    LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    exit('Projeto nao encontrado.');
}

$stmt = $pdo->prepare("
    SELECT
        bpp.id AS base_plan_price_id,
        pp.id AS plan_price_id,
        pp.billing_cycle,
        COALESCE(bpp.custom_price, pp.price) AS effective_price,
        pl.id AS plan_id,
        pl.name AS plan_name
    FROM base_plan_prices bpp
    INNER JOIN plan_prices pp ON pp.id = bpp.plan_price_id
    INNER JOIN plans pl ON pl.id = pp.plan_id
    WHERE bpp.base_id = ?
      AND bpp.status = 1
      AND pp.status = 1
      AND pl.status = 1
    ORDER BY FIELD(pp.billing_cycle, 'free', 'monthly', 'annual'), pl.id ASC
");
$stmt->execute([(int)$project['base_id']]);
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Alterar Plano';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Alterar Plano</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($project['name']) ?> · <?= htmlspecialchars((string)$project['base_name']) ?></p>
        </div>

        <a href="/web/admin/projects/view.php?id=<?= (int)$project['id'] ?>" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="post" action="/app/actions/projects/set_plan.php" class="c-card">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$project['id'] ?>">

            <div class="c-settings-grid">
                <div class="c-form-group">
                    <label>Plano atual</label>
                    <div><strong><?= htmlspecialchars((string)$project['plan_name']) ?></strong></div>
                </div>

                <div class="c-form-group">
                    <label>Ciclo atual</label>
                    <div><strong><?= htmlspecialchars(($project['billing_cycle'] ?? '') === 'annual' ? 'Anual' : (($project['billing_cycle'] ?? '') === 'monthly' ? 'Mensal' : 'Gratis')) ?></strong></div>
                </div>

                <div class="c-form-group">
                    <label>Expiração atual</label>
                    <div><strong><?= htmlspecialchars((string)($project['expires_at'] ?? 'Plano gratuito')) ?></strong></div>
                </div>
            </div>

            <div class="c-form-group">
                <label>Novo plano</label>
                <select class="c-input" name="base_plan_price_id" required>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= (int)$plan['base_plan_price_id'] ?>" <?= (int)$plan['plan_price_id'] === (int)($project['plan_price_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($plan['plan_name']) ?> ·
                            <?= htmlspecialchars(match ($plan['billing_cycle']) {
                                'monthly' => 'Mensal',
                                'annual' => 'Anual',
                                'free' => 'Gratis',
                                default => '-',
                            }) ?>
                            · R$ <?= number_format((float)$plan['effective_price'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="c-btn-secondary">Aplicar e sincronizar projeto</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
