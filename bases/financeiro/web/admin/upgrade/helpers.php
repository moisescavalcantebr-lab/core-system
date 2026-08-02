<?php
declare(strict_types=1);

require_once APP_PATH . '/helpers/core_bridge.php';

function upgradeCoreProject(PDO $corePdo): ?array
{
    global $project;

    $projectId = (int)($project['id'] ?? 0);

    $sql = "
        SELECT p.*,
               b.name AS base_name,
               b.slug AS base_slug,
               pl.name AS plan_name,
               COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle
        FROM projects p
        LEFT JOIN bases b ON b.id = p.base_id
        LEFT JOIN plans pl ON pl.id = p.plan_id
        LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
    ";

    if ($projectId > 0) {
        $stmt = $corePdo->prepare($sql . " WHERE p.id = ? LIMIT 1");
        $stmt->execute([$projectId]);
    } else {
        $stmt = $corePdo->prepare($sql . " WHERE p.slug = ? LIMIT 1");
        $stmt->execute([basename(PROJECT_PATH)]);
    }

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function upgradeRank(string $name): int
{
    $key = strtolower($name);

    if (str_contains($key, 'start')) {
        return 2;
    }

    return 1;
}

function upgradeCycleLabel(string $cycle): string
{
    return match ($cycle) {
        'annual' => 'Anual',
        'monthly' => 'Mensal',
        default => 'Gratis',
    };
}

function upgradeCycleAllowedForPlan(string $planName, string $cycle): bool
{
    $key = strtolower($planName);

    if (str_contains($key, 'start')) {
        return in_array($cycle, ['monthly', 'annual'], true);
    }

    return in_array($cycle, ['free', 'monthly', 'annual'], true);
}

function upgradeMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function upgradeModuleSlug(string $slug): string
{
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug)) ?? '';
}

function upgradeFeatureLabel(string $key, mixed $value): string
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

function upgradePlanFeatures(?string $limitsJson, ?string $baseSlug = null, ?string $planName = null): array
{
    $baseSlug = strtolower((string)$baseSlug);
    $planKey = strtolower((string)$planName);

    if ($baseSlug === 'financeiro') {
        if (str_contains($planKey, 'start')) {
            return [
                'Dashboard financeira',
                'Controle de entradas e saidas',
                'Categorias financeiras',
                'Subcategorias e tags',
                'Graficos por categoria e subcategoria',
                'Comprovantes nos lancamentos',
                'Opcao mensal ou anual',
                'Perfil e configuracoes',
                'Suporte padrao',
            ];
        }

        return [
            'Dashboard administrativa',
            'Lancamentos financeiros basicos',
            'Categorias principais',
            'Status pendente, pago e cancelado',
            'Perfil e configuracoes',
        ];
    }

    $limits = json_decode((string)$limitsJson, true);

    if (!is_array($limits)) {
        return [];
    }

    $features = [];

    foreach ($limits as $key => $value) {
        if (in_array((string)$key, ['annual_discount_percent'], true)) {
            continue;
        }

        $features[] = upgradeFeatureLabel((string)$key, $value);
    }

    return array_slice($features, 0, 8);
}

function upgradePlanDiscountPercent(?string $limitsJson): float
{
    $limits = json_decode((string)$limitsJson, true);

    if (!is_array($limits)) {
        return 0.0;
    }

    $discount = (float)($limits['annual_discount_percent'] ?? 0);

    return $discount > 0 ? min($discount, 100.0) : 0.0;
}

function upgradeAvailablePlans(PDO $corePdo, int $baseId): array
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
              OR (LOWER(pl.name) LIKE '%start%' AND pp.billing_cycle IN ('monthly', 'annual'))
              OR (LOWER(pl.name) NOT LIKE '%plus%' AND pp.billing_cycle IN ('monthly', 'annual'))
          )
        GROUP BY pl.id
        ORDER BY FIELD(pl.billing_cycle, 'free', 'monthly', 'annual'), pl.id ASC
    ");
    $stmt->execute([$baseId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function upgradePlanPrices(PDO $corePdo, int $baseId, int $planId): array
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
              OR (LOWER(pl.name) LIKE '%start%' AND pp.billing_cycle = 'annual')
              OR (LOWER(pl.name) NOT LIKE '%plus%')
          )
        ORDER BY FIELD(pp.billing_cycle, 'monthly', 'annual')
    ");
    $stmt->execute([$baseId, $planId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function upgradeAvailableModules(PDO $corePdo, array $coreProject): array
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
        $moduleSlug = upgradeModuleSlug((string)$pricing['module_slug']);

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

function upgradeProjectInstalledModuleSlugs(): array
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

        $moduleSlug = upgradeModuleSlug((string)$moduleSlug);

        if ($moduleSlug !== '' && is_file($modulesPath . '/' . $moduleSlug . '/module.json')) {
            $installed[] = $moduleSlug;
        }
    }

    return $installed;
}

function upgradeAutoModules(array $availableModules, bool $includeInstalledExtras = true): array
{
    $auto = [];
    $installed = $includeInstalledExtras ? upgradeProjectInstalledModuleSlugs() : [];

    foreach ($availableModules as $module) {
        $moduleSlug = (string)($module['slug'] ?? '');
        $commercialCategory = (string)($module['commercial_category'] ?? '');

        if ($commercialCategory === 'included' || ($commercialCategory === 'extra' && in_array($moduleSlug, $installed, true))) {
            $auto[$moduleSlug] = $module;
        }
    }

    return array_values($auto);
}

function upgradeModulePriceForCycle(array $module, string $cycle): float
{
    return (float)($cycle === 'annual' ? ($module['annual_price'] ?? 0) : ($module['monthly_price'] ?? 0));
}

function upgradeTotalForCycle(array $price, array $modules): float
{
    $cycle = (string)($price['billing_cycle'] ?? 'monthly');
    $total = (float)($price['effective_price'] ?? 0);

    foreach ($modules as $module) {
        $total += upgradeModulePriceForCycle($module, $cycle);
    }

    return $total;
}

function upgradeModulesPayload(array $modules): array
{
    return array_values(array_map(static fn(array $module): array => [
        'slug' => (string)($module['slug'] ?? ''),
        'label' => (string)($module['label'] ?? ($module['slug'] ?? '')),
        'commercial_category' => (string)($module['commercial_category'] ?? 'extra'),
        'monthly_price' => (float)($module['monthly_price'] ?? 0),
        'annual_price' => (float)($module['annual_price'] ?? 0),
    ], $modules));
}

function upgradeRequests(PDO $corePdo, int $projectId): array
{
    $stmt = $corePdo->prepare("
        SELECT r.*, pl.name AS plan_name, pp.billing_cycle
        FROM plan_upgrade_requests r
        INNER JOIN plans pl ON pl.id = r.plan_id
        LEFT JOIN plan_prices pp ON pp.id = r.plan_price_id
        WHERE r.project_id = ?
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT 8
    ");
    $stmt->execute([$projectId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
