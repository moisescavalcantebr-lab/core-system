<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$status = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;
$limitsJson = trim((string)($_POST['limits_json'] ?? ''));
$discountPercent = (float)str_replace(',', '.', (string)($_POST['annual_discount_percent'] ?? '0'));

if ($id <= 0 || $name === '') {
    flash('error', 'Plano invalido.');
    redirect('/web/admin/plans/index.php');
}

if ($limitsJson !== '' && json_decode($limitsJson, true) === null && json_last_error() !== JSON_ERROR_NONE) {
    flash('error', 'JSON de limites invalido.');
    redirect('/web/admin/plans/edit.php?id=' . $id);
}

$stmt = $pdo->prepare("SELECT name, billing_cycle FROM plans WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$currentPlan = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$currentCycle = (string)($currentPlan['billing_cycle'] ?? '');
$planKey = strtolower($name);
$isStart = str_contains($planKey, 'start');

$limits = $limitsJson !== '' ? json_decode($limitsJson, true) : [];
$limits = is_array($limits) ? $limits : [];

if ($isStart) {
    if ($discountPercent < 0 || $discountPercent > 100) {
        flash('error', 'Desconto anual do Start deve ficar entre 0 e 100%.');
        redirect('/web/admin/plans/edit.php?id=' . $id);
    }

    if ($discountPercent > 0) {
        $limits['annual_discount_percent'] = round($discountPercent, 2);
    } else {
        unset($limits['annual_discount_percent']);
    }
} else {
    unset($limits['annual_discount_percent']);
}

$limitsJson = !empty($limits)
    ? json_encode($limits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : '';

if ($currentCycle === 'free') {
    $status = 1;
}

$pdo->beginTransaction();

$primaryCycle = $currentCycle === 'free' ? 'free' : 'monthly';
$primaryPrice = match ($primaryCycle) {
    'free' => 0.0,
    'annual' => (float)($_POST['annual_price'] ?? 0),
    default => (float)($_POST['monthly_price'] ?? 0),
};
$oldPrices = [];

$stmt = $pdo->prepare("SELECT id, billing_cycle, price FROM plan_prices WHERE plan_id = ?");
$stmt->execute([$id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $priceRow) {
    $oldPrices[(string)$priceRow['billing_cycle']] = [
        'id' => (int)$priceRow['id'],
        'price' => (float)$priceRow['price'],
    ];
}

$stmt = $pdo->prepare("
    UPDATE plans
    SET name = ?, billing_cycle = ?, price = ?, limits_json = ?, status = ?
    WHERE id = ?
");
$stmt->execute([$name, $primaryCycle, $primaryPrice, $limitsJson !== '' ? $limitsJson : null, $status, $id]);

$upsert = $pdo->prepare("
    INSERT INTO plan_prices (plan_id, billing_cycle, price, status)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE price = VALUES(price), status = VALUES(status)
");

if ($currentCycle === 'free') {
    $upsert->execute([$id, 'free', 0.00, 1]);
} else {
    $monthlyPrice = (float)($_POST['monthly_price'] ?? 0);
    $annualPrice = (float)($_POST['annual_price'] ?? 0);
    $monthlyStatus = isset($_POST['monthly_enabled']) ? 1 : 0;
    $annualStatus = isset($_POST['annual_enabled']) ? 1 : 0;

    if ($isStart) {
        $monthlyStatus = 1;
        $annualStatus = 1;
    }

    if ($isStart) {
        $upsert->execute([$id, 'monthly', $monthlyPrice, $monthlyStatus]);
        $upsert->execute([$id, 'annual', $annualPrice, $annualStatus]);
    } else {
        $upsert->execute([$id, 'monthly', $monthlyPrice, $monthlyStatus]);
        $upsert->execute([$id, 'annual', $annualPrice, $annualStatus]);
    }

    $stmt = $pdo->prepare("SELECT id, billing_cycle, price FROM plan_prices WHERE plan_id = ?");
    $stmt->execute([$id]);
    $newPrices = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $priceRow) {
        $newPrices[(string)$priceRow['billing_cycle']] = [
            'id' => (int)$priceRow['id'],
            'price' => (float)$priceRow['price'],
        ];
    }

    $syncBasePrice = $pdo->prepare("
        UPDATE base_plan_prices
        SET custom_price = ?
        WHERE plan_price_id = ?
          AND (custom_price IS NULL OR ABS(custom_price - ?) < 0.001)
    ");

    foreach (['monthly', 'annual'] as $cycle) {
        if (!isset($oldPrices[$cycle], $newPrices[$cycle])) {
            continue;
        }

        $syncBasePrice->execute([
            $newPrices[$cycle]['price'],
            $newPrices[$cycle]['id'],
            $oldPrices[$cycle]['price'],
        ]);
    }
}

$pdo->commit();

flash('success', 'Plano atualizado.');
redirect('/web/admin/plans/edit.php?id=' . $id);
