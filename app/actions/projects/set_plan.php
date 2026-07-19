<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectInstaller.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$basePlanPriceId = (int)($_POST['base_plan_price_id'] ?? 0);

if ($id <= 0 || $basePlanPriceId <= 0) {
    flash('error', 'Projeto ou plano invalido.');
    redirect('/web/admin/projects/index.php');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new RuntimeException('Projeto nao encontrado.');
    }

    $stmt = $pdo->prepare("
        SELECT
            bpp.id AS base_plan_price_id,
            pp.id AS plan_price_id,
            pp.plan_id,
            pp.billing_cycle,
            COALESCE(bpp.custom_price, pp.price) AS effective_price,
            pl.name AS plan_name
        FROM base_plan_prices bpp
        INNER JOIN plan_prices pp ON pp.id = bpp.plan_price_id
        INNER JOIN plans pl ON pl.id = pp.plan_id
        WHERE bpp.id = ?
          AND bpp.base_id = ?
          AND bpp.status = 1
          AND pp.status = 1
          AND pl.status = 1
        LIMIT 1
    ");
    $stmt->execute([$basePlanPriceId, (int)$project['base_id']]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new RuntimeException('Plano nao vinculado a base do projeto.');
    }

    $expiresAt = null;
    $billingStatus = 'active';

    if ($plan['billing_cycle'] === 'monthly') {
        $expiresAt = date('Y-m-d', strtotime('+30 days'));
    } elseif ($plan['billing_cycle'] === 'annual') {
        $expiresAt = date('Y-m-d', strtotime('+365 days'));
    }

    $stmt = $pdo->prepare("
        UPDATE projects
        SET plan_id = ?, plan_price_id = ?, billing_status = ?, expires_at = ?
        WHERE id = ?
    ");
    $stmt->execute([(int)$plan['plan_id'], (int)$plan['plan_price_id'], $billingStatus, $expiresAt, $id]);

    ProjectInstaller::syncFromDatabase($pdo, $id);
    $syncedModules = ProjectInstaller::syncInstalledModulesFromBase($pdo, $id);

    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (?, 'plan_changed', ?, 'info')
    ")->execute([
        $id,
        'Plano alterado manualmente para ' . $plan['plan_name'] . ' (' . $plan['billing_cycle'] . ')' .
        (!empty($syncedModules) ? '. Modulos sincronizados: ' . implode(', ', $syncedModules) : ''),
    ]);

    flash('success', 'Plano aplicado e projeto sincronizado.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('/web/admin/projects/upgrade.php?id=' . $id);
