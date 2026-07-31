<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();
csrf_verify();

$basePlanPriceId = (int)($_POST['base_plan_price_id'] ?? 0);
$user = projectUser();

try {
    if ($basePlanPriceId <= 0) {
        throw new RuntimeException('Plano invalido.');
    }

    $corePdo = projectCorePdo();

    if (!$corePdo) {
        throw new RuntimeException('Core nao configurado.');
    }

    $coreProject = upgradeCoreProject($corePdo);

    if (!$coreProject) {
        throw new RuntimeException('Projeto nao encontrado no Core.');
    }

    $stmt = $corePdo->prepare("
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
    $stmt->execute([$basePlanPriceId, (int)$coreProject['base_id']]);
    $selectedPlan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedPlan) {
        throw new RuntimeException('Plano indisponivel para esta base.');
    }

    if (upgradeRank((string)$selectedPlan['plan_name']) <= upgradeRank((string)$coreProject['plan_name'])) {
        throw new RuntimeException('Este plano nao e um upgrade para o plano atual.');
    }

    if (!upgradeCycleAllowedForPlan((string)$selectedPlan['plan_name'], (string)$selectedPlan['billing_cycle'])) {
        throw new RuntimeException('Ciclo indisponivel para este plano.');
    }

    $availableModules = upgradeAvailableModules($corePdo, $coreProject);
    $selectedModules = upgradeAutoModules($availableModules);
    $requestAmount = upgradeTotalForCycle($selectedPlan, $selectedModules);

    $rootPath = dirname(PROJECT_PATH, 2);
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', $rootPath);
    }
    if (!defined('BASES_PATH')) {
        define('BASES_PATH', $rootPath . '/bases');
    }

    require_once $rootPath . '/app/services/projects/ProjectInstaller.php';

    $expiresAt = null;
    if ($selectedPlan['billing_cycle'] === 'monthly') {
        $expiresAt = date('Y-m-d', strtotime('+30 days'));
    } elseif ($selectedPlan['billing_cycle'] === 'annual') {
        $expiresAt = date('Y-m-d', strtotime('+365 days'));
    }

    $corePdo->beginTransaction();

    $lockStmt = $corePdo->prepare("SELECT id FROM projects WHERE id = ? FOR UPDATE");
    $lockStmt->execute([(int)$coreProject['id']]);

    $walletBalance = projectCoreWalletBalance($corePdo, (int)$coreProject['id']);

    if ($walletBalance + 0.0001 < $requestAmount) {
        throw new RuntimeException('Credito insuficiente para confirmar este upgrade.');
    }

    $stmt = $corePdo->prepare("
        INSERT INTO plan_upgrade_requests
        (project_id, plan_id, plan_price_id, requested_by_name, requested_by_email, amount, receipt_path, requested_modules_json, notes, status, reviewed_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, 'approved', NOW())
    ");
    $stmt->execute([
        (int)$coreProject['id'],
        (int)$selectedPlan['plan_id'],
        (int)$selectedPlan['plan_price_id'],
        (string)($user['name'] ?? $coreProject['owner_name'] ?? ''),
        (string)($user['email'] ?? $coreProject['owner_email'] ?? ''),
        $requestAmount,
        json_encode(upgradeModulesPayload($selectedModules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'Upgrade confirmado com credito da carteira.',
    ]);

    $upgradeRequestId = (int)$corePdo->lastInsertId();

    if ($requestAmount > 0) {
        $stmt = $corePdo->prepare("
            INSERT INTO project_wallet_movements
            (project_id, movement_type, source, amount, description, reference_table, reference_id, status, created_by_user_id)
            VALUES (?, 'debit', 'upgrade', ?, ?, 'plan_upgrade_requests', ?, 'applied', NULL)
        ");
        $stmt->execute([
            (int)$coreProject['id'],
            $requestAmount,
            'Upgrade para ' . (string)$selectedPlan['plan_name'] . ' (' . upgradeCycleLabel((string)$selectedPlan['billing_cycle']) . ')',
            $upgradeRequestId,
        ]);
    }

    $stmt = $corePdo->prepare("
        UPDATE projects
        SET plan_id = ?, plan_price_id = ?, billing_status = 'active', expires_at = ?
        WHERE id = ?
    ");
    $stmt->execute([
        (int)$selectedPlan['plan_id'],
        (int)$selectedPlan['plan_price_id'],
        $expiresAt,
        (int)$coreProject['id'],
    ]);

    $corePdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (?, 'upgrade_wallet_paid', ?, 'info')
    ")->execute([
        (int)$coreProject['id'],
        'Upgrade confirmado com carteira para ' . $selectedPlan['plan_name'] . ' (' . $selectedPlan['billing_cycle'] . ') no valor de ' . upgradeMoney($requestAmount) . '.',
    ]);

    $corePdo->commit();

    ProjectInstaller::syncFromDatabase($corePdo, (int)$coreProject['id']);
    ProjectInstaller::syncInstalledModulesFromBase($corePdo, (int)$coreProject['id']);

    flash('success', 'Upgrade confirmado com credito da carteira.');
} catch (Throwable $e) {
    if (isset($corePdo) && $corePdo instanceof PDO && $corePdo->inTransaction()) {
        $corePdo->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect(PROJECT_URL . '/admin/upgrade/index.php');
