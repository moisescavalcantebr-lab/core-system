<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);
$selected = array_values(array_filter(array_map('intval', $_POST['plan_price_ids'] ?? [])));
$customPrices = $_POST['custom_price'] ?? [];

if ($baseId <= 0) {
    flash('error', 'Base invalida.');
    redirect('/web/admin/bases/index.php');
}

$pdo->beginTransaction();

$freePlanPriceIds = $pdo->query("
    SELECT pp.id
    FROM plan_prices pp
    INNER JOIN plans pl ON pl.id = pp.plan_id
    WHERE pp.billing_cycle = 'free'
      AND pp.status = 1
      AND pl.status = 1
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($freePlanPriceIds as $freePlanPriceId) {
    $selected[] = (int)$freePlanPriceId;
}

$selected = array_values(array_unique($selected));

if (!empty($selected)) {
    $placeholders = implode(',', array_fill(0, count($selected), '?'));
    $stmt = $pdo->prepare("
        SELECT DISTINCT pp.plan_id
        FROM plan_prices pp
        INNER JOIN plans pl ON pl.id = pp.plan_id
        WHERE pp.id IN ($placeholders)
          AND pp.billing_cycle <> 'free'
          AND pp.status = 1
          AND pl.status = 1
    ");
    $stmt->execute($selected);
    $selectedPlanIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!empty($selectedPlanIds)) {
        $planPlaceholders = implode(',', array_fill(0, count($selectedPlanIds), '?'));
        $stmt = $pdo->prepare("
            SELECT pp.id
            FROM plan_prices pp
            INNER JOIN plans pl ON pl.id = pp.plan_id
            WHERE pp.plan_id IN ($planPlaceholders)
              AND pp.status = 1
              AND pl.status = 1
              AND (
                  pp.billing_cycle = 'free'
                  OR (LOWER(pl.name) LIKE '%start%' AND pp.billing_cycle IN ('monthly', 'annual'))
                  OR (LOWER(pl.name) NOT LIKE '%plus%' AND pp.billing_cycle IN ('monthly', 'annual'))
              )
        ");
        $stmt->execute($selectedPlanIds);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $planPriceId) {
            $selected[] = (int)$planPriceId;
        }

        $selected = array_values(array_unique($selected));
    }
}

$pdo->prepare("UPDATE base_plan_prices SET status = 0 WHERE base_id = ?")->execute([$baseId]);

$stmt = $pdo->prepare("
    INSERT INTO base_plan_prices (base_id, plan_price_id, custom_price, status)
    VALUES (?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), status = 1
");

foreach ($selected as $planPriceId) {
    $price = isset($customPrices[$planPriceId]) ? (float)$customPrices[$planPriceId] : null;
    $stmt->execute([$baseId, $planPriceId, $price]);
}

$pdo->commit();

flash('success', 'Planos da base atualizados.');
redirect('/web/admin/bases/plans.php?id=' . $baseId);
