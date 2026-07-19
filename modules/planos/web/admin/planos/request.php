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

    $corePdo = planosCorePdo();
    $coreProject = planosCoreProject($corePdo);

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

    if (planosRank((string)$selectedPlan['plan_name']) <= planosRank((string)$coreProject['plan_name'])) {
        throw new RuntimeException('Este plano nao e um upgrade para o plano atual.');
    }

    if (!planosCycleAllowedForPlan((string)$selectedPlan['plan_name'], (string)$selectedPlan['billing_cycle'])) {
        throw new RuntimeException('Ciclo indisponivel para este plano.');
    }

    $stmt = $corePdo->prepare("
        SELECT COUNT(*)
        FROM plan_upgrade_requests
        WHERE project_id = ?
          AND plan_id = ?
          AND status = 'pending'
    ");
    $stmt->execute([(int)$coreProject['id'], (int)$selectedPlan['plan_id']]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new RuntimeException('Ja existe uma solicitacao pendente para este plano.');
    }

    $availableModules = planosAvailableModules($corePdo, $coreProject);
    $selectedModules = planosSelectedModules($availableModules, planosProjectInstalledModuleSlugs(), true);
    $requestAmount = planosTotalForCycle($selectedPlan, $selectedModules);
    $allocatedBalance = planosAllocatedBalance($pdo);

    if ($allocatedBalance + 0.0001 < $requestAmount) {
        throw new RuntimeException('Saldo insuficiente para confirmar este upgrade.');
    }

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

    $pdo->beginTransaction();
    $corePdo->beginTransaction();

    if ($requestAmount > 0) {
        planosDebitAllocatedBalance(
            $pdo,
            $requestAmount,
            'Pagamento de upgrade: ' . (string)$selectedPlan['plan_name'],
            'Plano ' . (string)$selectedPlan['plan_name'] . ' (' . planosCycleLabel((string)$selectedPlan['billing_cycle']) . ')',
            (int)($user['id'] ?? 0)
        );
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
        json_encode(planosModulesPayload($selectedModules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'Pagamento confirmado com saldo do projeto.',
    ]);

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
        VALUES (?, 'upgrade_paid_balance', ?, 'info')
    ")->execute([
        (int)$coreProject['id'],
        'Upgrade pago com saldo do projeto para ' . $selectedPlan['plan_name'] . ' (' . $selectedPlan['billing_cycle'] . ') no valor de ' . planosMoney($requestAmount) . '.',
    ]);

    $corePdo->commit();
    $pdo->commit();

    ProjectInstaller::syncFromDatabase($corePdo, (int)$coreProject['id']);
    ProjectInstaller::syncInstalledModulesFromBase($corePdo, (int)$coreProject['id']);

    flash('success', 'Upgrade confirmado com saldo do projeto.');
} catch (Throwable $e) {
    if (isset($corePdo) && $corePdo instanceof PDO && $corePdo->inTransaction()) {
        $corePdo->rollBack();
    }
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect(PROJECT_URL . '/admin/planos/index.php');
