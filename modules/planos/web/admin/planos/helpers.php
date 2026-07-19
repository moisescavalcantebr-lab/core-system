<?php
declare(strict_types=1);

function planosCorePdo(): PDO
{
    $coreEnvPath = dirname(PROJECT_PATH, 2) . '/env/env.production.php';

    if (!is_file($coreEnvPath)) {
        throw new RuntimeException('Core nao configurado.');
    }

    $coreEnv = require $coreEnvPath;
    $coreDb = $coreEnv['db'];

    return new PDO(
        "mysql:host={$coreDb['host']};dbname={$coreDb['name']};charset={$coreDb['charset']}",
        $coreDb['user'],
        $coreDb['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ]
    );
}

function planosProjectSlug(): string
{
    return basename(PROJECT_PATH);
}

function planosCoreProject(PDO $corePdo): ?array
{
    $stmt = $corePdo->prepare("
        SELECT p.*,
               b.name AS base_name,
               b.slug AS base_slug,
               pl.name AS plan_name,
               COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle
        FROM projects p
        LEFT JOIN bases b ON b.id = p.base_id
        LEFT JOIN plans pl ON pl.id = p.plan_id
        LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
        WHERE p.slug = ?
        LIMIT 1
    ");
    $stmt->execute([planosProjectSlug()]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function planosRank(string $name): int
{
    $key = strtolower($name);

    if (str_contains($key, 'plus')) {
        return 3;
    }

    if (str_contains($key, 'start')) {
        return 2;
    }

    return 1;
}

function planosCycleLabel(string $cycle): string
{
    return match ($cycle) {
        'annual' => 'Anual',
        'monthly' => 'Mensal',
        default => 'Gratis',
    };
}

function planosCycleAllowedForPlan(string $planName, string $cycle): bool
{
    $key = strtolower($planName);

    if (str_contains($key, 'start')) {
        return $cycle === 'monthly';
    }

    if (str_contains($key, 'plus')) {
        return $cycle === 'annual';
    }

    return in_array($cycle, ['free', 'monthly', 'annual'], true);
}

function planosMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function planosModuleSlug(string $slug): string
{
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug)) ?? '';
}

function planosFeatureLabel(string $key, mixed $value): string
{
    $label = ucwords(str_replace('_', ' ', $key));

    if (is_bool($value)) {
        return $label . ': ' . ($value ? 'Sim' : 'Nao');
    }

    if (is_array($value)) {
        return $label . ': ' . implode(', ', array_map('strval', $value));
    }

    return $label . ': ' . (string)$value;
}

function planosPlanFeatures(?string $limitsJson): array
{
    $limits = json_decode((string)$limitsJson, true);

    if (!is_array($limits)) {
        return [];
    }

    $features = [];

    foreach ($limits as $key => $value) {
        $features[] = planosFeatureLabel((string)$key, $value);
    }

    return array_slice($features, 0, 8);
}

function planosAvailablePlans(PDO $corePdo, int $baseId): array
{
    $hasLimits = false;

    try {
        $hasLimits = (bool)$corePdo->query("SHOW COLUMNS FROM plans LIKE 'limits_json'")->fetchColumn();
    } catch (Throwable $e) {
        $hasLimits = false;
    }

    $limitsSelect = $hasLimits ? 'pl.limits_json' : 'NULL AS limits_json';

    $stmt = $corePdo->prepare("
        SELECT
            pl.id,
            pl.name,
            pl.billing_cycle,
            {$limitsSelect},
            MIN(COALESCE(bpp.custom_price, pp.price)) AS starting_price,
            COUNT(pp.id) AS price_options
        FROM plans pl
        INNER JOIN plan_prices pp ON pp.plan_id = pl.id AND pp.status = 1
        INNER JOIN base_plan_prices bpp ON bpp.plan_price_id = pp.id AND bpp.status = 1
        WHERE bpp.base_id = ?
          AND pl.status = 1
          AND (
              pp.billing_cycle = 'free'
              OR (LOWER(pl.name) LIKE '%start%' AND pp.billing_cycle = 'monthly')
              OR (LOWER(pl.name) LIKE '%plus%' AND pp.billing_cycle = 'annual')
              OR (LOWER(pl.name) NOT LIKE '%start%' AND LOWER(pl.name) NOT LIKE '%plus%' AND pp.billing_cycle IN ('monthly', 'annual'))
          )
        GROUP BY pl.id
        ORDER BY FIELD(pl.billing_cycle, 'free', 'monthly', 'annual'), pl.id ASC
    ");
    $stmt->execute([$baseId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function planosPlanPrices(PDO $corePdo, int $baseId, int $planId): array
{
    $stmt = $corePdo->prepare("
        SELECT
            bpp.id AS base_plan_price_id,
            pp.id AS plan_price_id,
            pl.name AS plan_name,
            pp.billing_cycle,
            COALESCE(bpp.custom_price, pp.price) AS effective_price
        FROM base_plan_prices bpp
        INNER JOIN plan_prices pp ON pp.id = bpp.plan_price_id
        INNER JOIN plans pl ON pl.id = pp.plan_id
        WHERE bpp.base_id = ?
          AND bpp.status = 1
          AND pp.status = 1
          AND pp.plan_id = ?
          AND pp.billing_cycle IN ('monthly', 'annual')
          AND (
              (LOWER(pl.name) LIKE '%start%' AND pp.billing_cycle = 'monthly')
              OR (LOWER(pl.name) LIKE '%plus%' AND pp.billing_cycle = 'annual')
              OR (LOWER(pl.name) NOT LIKE '%start%' AND LOWER(pl.name) NOT LIKE '%plus%')
          )
        ORDER BY FIELD(pp.billing_cycle, 'monthly', 'annual')
    ");
    $stmt->execute([$baseId, $planId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function planosAvailableModules(PDO $corePdo, array $coreProject): array
{
    $rootModulesPath = dirname(PROJECT_PATH, 2) . '/modules';
    $baseId = (int)($coreProject['base_id'] ?? 0);
    $modules = [];

    if ($baseId <= 0 || !is_dir($rootModulesPath)) {
        return [];
    }

    $stmt = $corePdo->prepare("
        SELECT *
        FROM base_module_prices
        WHERE base_id = ?
          AND status = 1
          AND commercial_category IN ('included', 'extra')
        ORDER BY commercial_category ASC, display_order ASC, module_slug ASC
    ");
    $stmt->execute([$baseId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pricing) {
        $moduleSlug = planosModuleSlug((string)$pricing['module_slug']);

        if ($moduleSlug === '') {
            continue;
        }

        $manifestPath = $rootModulesPath . '/' . $moduleSlug . '/module.json';
        $manifest = is_file($manifestPath)
            ? json_decode((string)file_get_contents($manifestPath), true)
            : [];
        $manifest = is_array($manifest) ? $manifest : [];

        $modules[$moduleSlug] = [
            'slug' => $moduleSlug,
            'label' => (string)($manifest['label'] ?? $moduleSlug),
            'description' => (string)($manifest['description'] ?? ''),
            'category' => (string)($manifest['category'] ?? 'Modulo'),
            'commercial_category' => (string)($pricing['commercial_category'] ?? 'extra'),
            'monthly_price' => $pricing['monthly_price'] !== null ? (float)$pricing['monthly_price'] : 0.0,
            'annual_price' => $pricing['annual_price'] !== null ? (float)$pricing['annual_price'] : 0.0,
        ];
    }

    return $modules;
}

function planosSelectedModules(array $availableModules, array $postedModules, bool $includeAuto = true): array
{
    $selected = [];

    if ($includeAuto) {
        foreach ($availableModules as $module) {
            if (($module['commercial_category'] ?? '') === 'included') {
                $selected[(string)$module['slug']] = $module;
            }
        }
    }

    foreach ($postedModules as $moduleSlug) {
        $moduleSlug = planosModuleSlug((string)$moduleSlug);

        if (isset($availableModules[$moduleSlug])) {
            $selected[$moduleSlug] = $availableModules[$moduleSlug];
        }
    }

    return array_values($selected);
}

function planosProjectInstalledModuleSlugs(): array
{
    $modulesPath = PROJECT_PATH . '/modules';

    if (!is_dir($modulesPath)) {
        return [];
    }

    $installed = [];

    foreach (scandir($modulesPath) ?: [] as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $moduleSlug = planosModuleSlug((string)$moduleSlug);

        if ($moduleSlug !== '' && is_file($modulesPath . '/' . $moduleSlug . '/module.json')) {
            $installed[] = $moduleSlug;
        }
    }

    return $installed;
}

function planosAutoModules(array $availableModules, bool $includeInstalledExtras = true): array
{
    $auto = [];
    $installed = $includeInstalledExtras ? planosProjectInstalledModuleSlugs() : [];

    foreach ($availableModules as $module) {
        $moduleSlug = (string)($module['slug'] ?? '');
        $commercialCategory = (string)($module['commercial_category'] ?? '');

        if ($commercialCategory === 'included' || ($commercialCategory === 'extra' && in_array($moduleSlug, $installed, true))) {
            $auto[$moduleSlug] = $module;
        }
    }

    return array_values($auto);
}

function planosIncludedModules(array $availableModules): array
{
    return planosAutoModules($availableModules, false);
}

function planosExtraModules(array $availableModules): array
{
    return array_values(array_filter($availableModules, fn(array $module) => ($module['commercial_category'] ?? '') === 'extra'));
}

function planosModulePriceForCycle(array $module, string $cycle): float
{
    return (float)($cycle === 'annual' ? ($module['annual_price'] ?? 0) : ($module['monthly_price'] ?? 0));
}

function planosTotalForCycle(array $price, array $modules): float
{
    $cycle = (string)($price['billing_cycle'] ?? 'monthly');
    $total = (float)($price['effective_price'] ?? 0);

    foreach ($modules as $module) {
        $total += planosModulePriceForCycle($module, $cycle);
    }

    return $total;
}

function planosFinanceInstalled(): bool
{
    return is_file(PROJECT_PATH . '/modules/financeiro/module.json')
        && is_file(PROJECT_PATH . '/web/admin/financeiro/helpers.php');
}

function planosEnsureFinanceBalanceSchema(PDO $projectPdo): void
{
    if (!planosFinanceInstalled()) {
        throw new RuntimeException('Modulo financeiro nao instalado neste projeto.');
    }

    require_once PROJECT_PATH . '/web/admin/financeiro/helpers.php';

    if (function_exists('financeEnsureEntryMetaSchema')) {
        financeEnsureEntryMetaSchema($projectPdo);
    }
}

function planosAllocatedBalance(PDO $projectPdo): float
{
    planosEnsureFinanceBalanceSchema($projectPdo);

    if (function_exists('financeAllocatedBalance')) {
        return financeAllocatedBalance($projectPdo);
    }

    $row = $projectPdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income' AND source = 'balance_deposit' THEN amount ELSE 0 END), 0) AS deposited_total,
            COALESCE(SUM(CASE WHEN type = 'expense' AND source = 'balance_usage' THEN amount ELSE 0 END), 0) AS used_total
        FROM finance_entries
        WHERE status = 'paid'
    ")->fetch(PDO::FETCH_ASSOC) ?: ['deposited_total' => 0, 'used_total' => 0];

    return (float)$row['deposited_total'] - (float)$row['used_total'];
}

function planosDebitAllocatedBalance(PDO $projectPdo, float $amount, string $title, string $description, int $userId): void
{
    planosEnsureFinanceBalanceSchema($projectPdo);

    $stmt = $projectPdo->prepare("
        INSERT INTO finance_entries
            (category_id, type, title, description, amount, party_type, party_module, party_id, party_name, due_date, paid_at, status, source, payment_method, receipt_path, created_by_user_id, updated_by_user_id)
        VALUES
            (NULL, 'expense', ?, ?, ?, 'other', NULL, NULL, NULL, NULL, ?, 'paid', 'balance_usage', 'saldo', NULL, ?, ?)
    ");
    $stmt->execute([$title, $description !== '' ? $description : null, $amount, date('Y-m-d'), $userId ?: null, $userId ?: null]);
}

function planosModulesPayload(array $modules): array
{
    return array_values(array_map(static fn(array $module): array => [
        'slug' => (string)($module['slug'] ?? ''),
        'label' => (string)($module['label'] ?? ($module['slug'] ?? '')),
        'commercial_category' => (string)($module['commercial_category'] ?? 'extra'),
        'monthly_price' => (float)($module['monthly_price'] ?? 0),
        'annual_price' => (float)($module['annual_price'] ?? 0),
    ], $modules));
}

function planosUpgradeRequests(PDO $corePdo, int $projectId): array
{
    $stmt = $corePdo->prepare("
        SELECT
            r.*,
            pl.name AS plan_name,
            pp.billing_cycle,
            rf.id AS refund_id,
            rf.status AS refund_status,
            rf.sent_at AS refund_sent_at,
            rf.sent_receipt_path AS refund_sent_receipt_path
        FROM plan_upgrade_requests r
        INNER JOIN plans pl ON pl.id = r.plan_id
        LEFT JOIN plan_prices pp ON pp.id = r.plan_price_id
        LEFT JOIN plan_refund_requests rf ON rf.upgrade_request_id = r.id
        WHERE r.project_id = ?
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 8
    ");
    $stmt->execute([$projectId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function planosCoreSettings(PDO $corePdo): array
{
    return $corePdo->query("
        SELECT setting_key, setting_value
        FROM core_settings
        WHERE setting_key IN ('upgrade_pix_key', 'upgrade_pix_holder', 'upgrade_pix_notes')
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
}
