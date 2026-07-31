<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$laboratoryEnabled = coreLaboratoryEnabled();
$environmentLabel = coreEnvironmentLabel();
$baseId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT b.*,
        (SELECT COUNT(*) FROM projects p WHERE p.base_id = b.id AND p.status != 'deleted') AS total_projects
    FROM bases b
    WHERE b.id = :id
");
$stmt->execute(['id' => $baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base inválida.');
}

if ($base['slug'] === 'base') {
    die('A base principal deve permanecer limpa.');
}

$basePath = BASES_PATH . '/' . $base['slug'];
$baseModulesPath = $basePath . '/modules';
$rootModulesPath = ROOT_PATH . '/modules';
$modules = [];
$categories = [];
$segments = [];
$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$segmentFilter = trim((string)($_GET['segment'] ?? ''));
$stateFilter = trim((string)($_GET['state'] ?? ''));
$showAvailableModules = $laboratoryEnabled && (string)($_GET['show'] ?? '') === 'available';
$moduleConfigs = [];

$modulePriceColumns = $pdo->query("SHOW COLUMNS FROM base_module_prices")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('settings_json', $modulePriceColumns, true)) {
    $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN settings_json LONGTEXT NULL AFTER annual_price");
}

$configStmt = $pdo->prepare("
    SELECT *
    FROM base_module_prices
    WHERE base_id = ?
");
$configStmt->execute([$baseId]);
foreach ($configStmt->fetchAll(PDO::FETCH_ASSOC) as $config) {
    $moduleConfigs[(string)$config['module_slug']] = $config;
}

if (is_dir($rootModulesPath)) {
    foreach (scandir($rootModulesPath) as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $manifestPath = $rootModulesPath . '/' . $moduleSlug . '/module.json';

        if (!is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            continue;
        }

        if ($moduleSlug === 'planos' || ($manifest['status'] ?? '') === 'legacy') {
            continue;
        }

        $config = $moduleConfigs[$moduleSlug] ?? [];
        $settings = [];

        if (!empty($config['settings_json'])) {
            $decodedSettings = json_decode((string)$config['settings_json'], true);
            $settings = is_array($decodedSettings) ? $decodedSettings : [];
        }

        $monthlyPrice = $config['monthly_price'] ?? ($manifest['monthly_price'] ?? null);
        $annualPrice = $config['annual_price'] ?? ($manifest['annual_price'] ?? null);

        $modules[] = [
            'slug' => $moduleSlug,
            'label' => $manifest['label'] ?? $moduleSlug,
            'description' => $manifest['description'] ?? '',
            'version' => $manifest['version'] ?? '',
            'status' => $manifest['status'] ?? 'draft',
            'kind' => $manifest['kind'] ?? 'main',
            'category' => $manifest['category'] ?? 'Geral',
            'commercial_category' => $config['commercial_category'] ?? ($manifest['commercial_category'] ?? 'extra'),
            'monthly_price' => $monthlyPrice,
            'annual_price' => $annualPrice,
            'commercial_status' => isset($config['status']) ? (int)$config['status'] : 0,
            'display_order' => isset($config['display_order']) ? (int)$config['display_order'] : 0,
            'settings' => $settings,
            'configured' => isset($moduleConfigs[$moduleSlug]),
            'segments' => $manifest['segments'] ?? ['geral'],
            'recommended_bases' => $manifest['recommended_bases'] ?? ['geral'],
            'attach_to' => $manifest['attach_to'] ?? [],
            'tags' => $manifest['tags'] ?? [],
            'installed' => is_file($baseModulesPath . '/' . $moduleSlug . '/module.json'),
        ];

        $categories[$manifest['category'] ?? 'Geral'] = true;

        foreach (($manifest['segments'] ?? ['geral']) as $segment) {
            $segments[(string)$segment] = true;
        }
    }
}

$modules = array_values(array_filter($modules, function(array $module) use ($search, $categoryFilter, $segmentFilter, $stateFilter, $showAvailableModules) {
    if (!$showAvailableModules && !$module['installed']) {
        return false;
    }

    $haystack = strtolower($module['label'] . ' ' . $module['slug'] . ' ' . $module['description'] . ' ' . implode(' ', $module['tags']));

    if ($search !== '' && !str_contains($haystack, strtolower($search))) {
        return false;
    }

    if ($categoryFilter !== '' && $module['category'] !== $categoryFilter) {
        return false;
    }

    if ($segmentFilter !== '' && !in_array($segmentFilter, $module['segments'], true)) {
        return false;
    }

    if ($stateFilter === 'installed' && !$module['installed']) {
        return false;
    }

    if ($stateFilter === 'available' && $module['installed']) {
        return false;
    }

    return true;
}));

ksort($categories);
ksort($segments);
usort($modules, fn($a, $b) => [$a['display_order'], $a['label']] <=> [$b['display_order'], $b['label']]);

function moduleRecommendedForBase(array $module, string $baseSlug): bool
{
    $recommendedBases = array_values(array_filter(array_map('strval', $module['recommended_bases'] ?? [])));

    return !empty($recommendedBases)
        && (
            in_array('geral', $recommendedBases, true)
            || in_array($baseSlug, $recommendedBases, true)
        );
}

$modules = array_values(array_filter($modules, fn(array $module) => moduleRecommendedForBase($module, (string)$base['slug'])));
usort($modules, function(array $a, array $b): int {
    $groupRank = static function(array $module): int {
        if (empty($module['installed'])) {
            return 2;
        }

        return ($module['kind'] ?? 'main') === 'addon' ? 1 : 0;
    };

    $aKind = ($a['kind'] ?? 'main') === 'addon' ? 1 : 0;
    $bKind = ($b['kind'] ?? 'main') === 'addon' ? 1 : 0;

    return [$groupRank($a), $aKind, $a['display_order'], $a['label']]
        <=> [$groupRank($b), $bKind, $b['display_order'], $b['label']];
});

function moduleSectionKey(array $module): string
{
    $kind = ($module['kind'] ?? 'main') === 'addon' ? 'addon' : 'main';

    if (!empty($module['installed'])) {
        return $kind === 'addon' ? 'installed_addon' : 'installed_main';
    }

    return $kind === 'addon' ? 'available_addon' : 'available_main';
}

function moduleSectionMeta(string $group): array
{
    return match ($group) {
        'installed_addon' => [
            'title' => 'Addons instalados',
            'description' => 'Complementos acoplados aos modulos principais desta base.',
        ],
        'available_main' => [
            'title' => 'Modulos principais disponiveis',
            'description' => 'Recursos centrais recomendados para esta base.',
        ],
        'available_addon' => [
            'title' => 'Addons disponiveis',
            'description' => 'Complementos recomendados para os modulos desta base.',
        ],
        default => [
            'title' => 'Modulos principais instalados',
            'description' => 'Recursos centrais que estruturam a base.',
        ],
    };
}

function moduleItemGroupClass(string $sectionKey): string
{
    return match ($sectionKey) {
        'installed_addon' => 'addon',
        'available_main' => 'available',
        'available_addon' => 'available-addon',
        default => 'main',
    };
}

$moduleSectionCounts = [];
foreach ($modules as $moduleForSectionCount) {
    $moduleSectionKey = moduleSectionKey($moduleForSectionCount);
    $moduleSectionCounts[$moduleSectionKey] = ($moduleSectionCounts[$moduleSectionKey] ?? 0) + 1;
}

function moduleCommercialCategoryLabel(string $category): string
{
    return match ($category) {
        'included' => 'Incluído',
        'coming_soon' => 'Em breve',
        default => 'Extra',
    };
}

function moduleCommercialStatusLabel(int $status): string
{
    return $status === 1 ? 'Visível' : 'Oculto';
}

function modulePriceLabel($value): string
{
    return $value !== null ? 'R$ ' . number_format((float)$value, 2, ',', '.') : '-';
}

function participantTypeSlug(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function participantTypeOptions(PDO $pdo): array
{
    $options = [
        'jogador' => ['singular' => 'Jogador', 'plural' => 'Jogadores'],
    ];

    $stmt = $pdo->query("
        SELECT settings_json
        FROM base_module_prices
        WHERE module_slug = 'participantes'
          AND settings_json IS NOT NULL
          AND settings_json <> ''
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $settingsJson) {
        $settings = json_decode((string)$settingsJson, true);

        if (!is_array($settings)) {
            continue;
        }

        $context = participantTypeSlug((string)($settings['participant_context'] ?? ''));
        $singular = trim((string)($settings['participant_label'] ?? ''));
        $plural = trim((string)($settings['participant_label_plural'] ?? ''));

        if ($context === 'membros') {
            $context = 'membro';
        }

        if (
            $context === ''
            || in_array($context, ['custom', 'participante', 'participantes'], true)
            || $singular === ''
            || $plural === ''
        ) {
            continue;
        }

        $options[$context] = [
            'singular' => $singular,
            'plural' => $plural,
        ];
    }

    uasort($options, fn(array $a, array $b) => strcmp($a['plural'], $b['plural']));

    return $options;
}

$participantTypeOptions = participantTypeOptions($pdo);
$baseParticipantSettings = [];

foreach ($modules as $moduleForSettings) {
    if (($moduleForSettings['slug'] ?? '') === 'participantes') {
        $baseParticipantSettings = is_array($moduleForSettings['settings'] ?? null) ? $moduleForSettings['settings'] : [];
        break;
    }
}

function financeModeFromParticipantSettings(array $participantSettings): string
{
    $context = participantTypeSlug((string)($participantSettings['participant_context'] ?? ''));

    if ($context === '' || in_array($context, ['custom', 'participante', 'participantes'], true)) {
        return 'participants';
    }

    return $context;
}

function financeModeLabelFromSettings(string $mode, array $participantSettings): string
{
    if ($mode === 'personal') {
        return 'Pessoal';
    }

    $label = trim((string)($participantSettings['participant_label'] ?? ''));

    return $label !== '' ? $label : 'Participantes';
}

$title = 'Módulos da Base';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Módulos: <?= htmlspecialchars($base['name']) ?></h1>
            <p class="c-page-subtitle">Adicione módulos testados à base de segmento</p>
        </div>

        <div class="c-page-actions">
            <?php if ($laboratoryEnabled): ?>
                <?php if ($showAvailableModules): ?>
                    <a class="c-btn-secondary c-btn-lock" href="/web/admin/bases/modules.php?id=<?= (int)$base['id'] ?>">
                        Ocultar disponiveis
                    </a>
                <?php else: ?>
                    <a class="c-btn-secondary c-btn-unlock" href="/web/admin/bases/modules.php?id=<?= (int)$base['id'] ?>&show=available">
                        Ver disponiveis
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <span class="c-badge c-badge--neutral"><?= htmlspecialchars($environmentLabel) ?></span>
            <?php endif; ?>

            <a class="c-btn-secondary" href="/web/admin/bases/index.php">
                Voltar
            </a>
        </div>
    </div>

    <div class="c-page-content">

        <?php if (!$showAvailableModules): ?>
            <div class="c-card c-module-protection">
                <strong><?= $laboratoryEnabled ? 'Edicao protegida' : 'Publicacao protegida' ?></strong>
                <p>
                    <?php if ($laboratoryEnabled): ?>
                        A tela mostra apenas modulos instalados. Para instalar outro modulo, use Ver disponiveis. Cada modulo precisa ser aberto individualmente para editar.
                    <?php else: ?>
                        Em producao, esta tela mostra apenas os modulos publicados nesta base oficial. Ajustes estruturais devem ser feitos no laboratorio e enviados por deploy.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($showAvailableModules): ?>
        <div class="c-card">
            <form method="get">
                <input type="hidden" name="id" value="<?= (int)$base['id'] ?>">
                <input type="hidden" name="show" value="available">

                <div style="display:grid; grid-template-columns:minmax(220px, 1fr) 160px 160px 160px; gap:12px;">
                    <div class="c-form-group">
                        <label>Buscar</label>
                        <input class="c-input" type="text" name="search" placeholder="Buscar módulo..." value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Categoria</label>
                        <select class="c-input" name="category">
                            <option value="">Todas</option>
                            <?php foreach (array_keys($categories) as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Segmento</label>
                        <select class="c-input" name="segment">
                            <option value="">Todos</option>
                            <?php foreach (array_keys($segments) as $segment): ?>
                                <option value="<?= htmlspecialchars($segment) ?>" <?= $segmentFilter === $segment ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($segment) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Situação</label>
                        <select class="c-input" name="state">
                            <option value="">Todos</option>
                            <option value="installed" <?= $stateFilter === 'installed' ? 'selected' : '' ?>>Instalados</option>
                            <option value="available" <?= $stateFilter === 'available' ? 'selected' : '' ?>>Disponíveis</option>
                        </select>
                    </div>
                </div>

                <button class="c-btn-secondary">Filtrar</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="c-card">

            <?php if (empty($modules)): ?>

                <p><?= $showAvailableModules ? 'Nenhum módulo disponível na pasta raiz ' : 'Nenhum módulo instalado nesta base.' ?><?= $showAvailableModules ? '<strong>modules/</strong>.' : '' ?></p>

            <?php else: ?>

                <div class="c-module-list">
                    <?php $currentModuleGroup = null; ?>
                    <?php foreach ($modules as $module): ?>
                        <?php
                            $moduleSectionKey = moduleSectionKey($module);
                            $moduleGroup = moduleItemGroupClass($moduleSectionKey);
                            $sectionClass = str_replace('_', '-', $moduleSectionKey);
                            $moduleItemClass = match ($moduleGroup) {
                                'addon' => 'c-module-item--addon',
                                'available-addon' => 'c-module-item--available-addon',
                                'available' => 'c-module-item--available',
                                default => 'c-module-item--main',
                            };
                            $sectionMeta = moduleSectionMeta($moduleSectionKey);
                            $formId = 'module-commerce-' . preg_replace('/[^a-z0-9\-_]/', '-', (string)$module['slug']);
                            $panelId = $formId . '-panel';
                            $settings = $module['settings'] ?? [];
                            $participantContext = (string)($settings['participant_context'] ?? 'custom');
                            $participantLabel = (string)($settings['participant_label'] ?? 'Participante');
                            $participantLabelPlural = (string)($settings['participant_label_plural'] ?? 'Participantes');
                            if ($participantContext === 'participante') {
                                $participantContext = 'custom';
                            }
                            if ($participantContext === 'membros') {
                                $participantContext = 'membro';
                            }
                            if (
                                $participantContext !== 'custom'
                                && !isset($participantTypeOptions[$participantContext])
                                && $participantLabel !== ''
                                && $participantLabelPlural !== ''
                            ) {
                                $participantTypeOptions[$participantContext] = [
                                    'singular' => $participantLabel,
                                    'plural' => $participantLabelPlural,
                                ];
                            }
                            $participantContextOption = isset($participantTypeOptions[$participantContext]) ? $participantContext : 'custom';
                            $financeMode = (string)($settings['finance_mode'] ?? 'personal');
                            if ($financeMode !== 'personal') {
                                $financeMode = participantTypeSlug($financeMode);
                            }
                            if ($financeMode === '' || in_array($financeMode, ['custom', 'participante', 'participantes'], true)) {
                                $financeMode = 'participants';
                            }
                            $financeLinkedMode = financeModeFromParticipantSettings($baseParticipantSettings);
                            $financeLinkedLabel = financeModeLabelFromSettings($financeLinkedMode, $baseParticipantSettings);
                            $requiredModules = array_values(array_filter(array_map('strval', $module['attach_to'] ?? [])));
                            $missingRequiredModules = array_values(array_filter($requiredModules, fn(string $requiredModule) => !is_file($baseModulesPath . '/' . $requiredModule . '/module.json')));
                        ?>

                        <?php if ($currentModuleGroup !== $moduleSectionKey): ?>
                            <?php $currentModuleGroup = $moduleSectionKey; ?>
                            <div class="c-module-section c-module-section--<?= htmlspecialchars($sectionClass) ?>">
                                <div>
                                    <strong><?= htmlspecialchars($sectionMeta['title']) ?></strong>
                                    <span><?= htmlspecialchars($sectionMeta['description']) ?></span>
                                </div>
                                <span class="c-module-section__count"><?= (int)($moduleSectionCounts[$moduleSectionKey] ?? 0) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="c-module-item <?= htmlspecialchars($moduleItemClass) ?>">
                            <div class="c-module-main">
                                <div>
                                    <strong><?= htmlspecialchars($module['label']) ?></strong>
                                    <span><?= htmlspecialchars($module['slug']) ?></span>
                                </div>

                                <p><?= htmlspecialchars($module['description']) ?></p>

                                <div class="c-module-badges">
                                    <span class="c-badge c-badge--neutral"><?= htmlspecialchars($module['category']) ?></span>
                                    <span class="c-badge c-badge--neutral">v<?= htmlspecialchars($module['version']) ?></span>
                                    <?php if (($module['kind'] ?? 'main') === 'addon'): ?>
                                        <span class="c-badge c-badge--info">Acoplável</span>
                                    <?php endif; ?>
                                    <?php if ($module['slug'] === 'financeiro'): ?>
                                        <span class="c-badge c-badge--neutral">
                                            Modo: <?= htmlspecialchars(financeModeLabelFromSettings($financeMode, $baseParticipantSettings)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($module['slug'] === 'participantes'): ?>
                                        <span class="c-badge c-badge--neutral">
                                            Modo: <?= htmlspecialchars($participantContextOption === 'custom' ? 'Personalizado' : $participantLabel) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($module['installed']): ?>
                                        <span class="c-badge c-badge--success">Instalado</span>
                                    <?php else: ?>
                                        <span class="c-badge c-badge--warning">Disponível</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="c-module-summary">
                                <span><?= htmlspecialchars(moduleCommercialCategoryLabel((string)$module['commercial_category'])) ?></span>
                                <strong><?= htmlspecialchars(moduleCommercialStatusLabel((int)$module['commercial_status'])) ?></strong>
                            </div>

                            <div class="c-module-summary">
                                <span>Mensal</span>
                                <strong><?= htmlspecialchars(modulePriceLabel($module['monthly_price'])) ?></strong>
                            </div>

                            <div class="c-module-summary">
                                <span>Anual</span>
                                <strong><?= htmlspecialchars(modulePriceLabel($module['annual_price'])) ?></strong>
                            </div>

                            <div class="c-module-actions">
                                <?php if ($laboratoryEnabled): ?>
                                    <button class="c-btn-secondary" type="button" data-module-config="<?= htmlspecialchars($panelId) ?>">
                                        Editar modulo
                                    </button>
                                <?php else: ?>
                                    <span class="c-badge c-badge--neutral">Somente leitura</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($laboratoryEnabled): ?>
                            <div class="c-module-config" id="<?= htmlspecialchars($panelId) ?>" hidden>
                                <div class="c-module-panel-actions">
                                    <?php if ($module['installed'] && in_array($module['slug'], ['participantes', 'financeiro'], true)): ?>
                                        <form method="post" action="/app/actions/bases/module_apply_settings.php" onsubmit="return confirm('Aplicar a configuração atual deste módulo aos projetos existentes desta base?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">
                                            <input type="hidden" name="module" value="<?= htmlspecialchars($module['slug']) ?>">
                                            <button class="c-btn-secondary c-btn-apply-settings" title="Atualiza os projetos existentes com a configuração desta base">
                                                Aplicar aos projetos
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (!$module['installed']): ?>
                                        <?php if ((int)($base['is_protected'] ?? 0) === 1): ?>
                                            <span class="c-module-locked-badge">Base protegida</span>
                                        <?php elseif (!empty($missingRequiredModules)): ?>
                                            <span class="c-module-locked-badge">Requer: <?= htmlspecialchars(implode(', ', $missingRequiredModules)) ?></span>
                                        <?php else: ?>
                                            <form method="post" action="/app/actions/bases/module_install.php" onsubmit="return confirm('Instalar este módulo nesta base?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">
                                                <input type="hidden" name="module" value="<?= htmlspecialchars($module['slug']) ?>">
                                                <button class="c-btn-secondary">Instalar</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php elseif ((int)($base['is_protected'] ?? 0) !== 1): ?>
                                        <form method="post" action="/app/actions/bases/module_uninstall.php" onsubmit="return confirm('Desinstalar este módulo da base? Os dados dos projetos existentes não serão apagados, mas a base deixará de enviar este módulo em novas sincronizações.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">
                                            <input type="hidden" name="module" value="<?= htmlspecialchars($module['slug']) ?>">
                                            <button class="c-btn-secondary c-btn-uninstall">
                                                Desinstalar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="c-module-locked-badge">Base protegida</span>
                                    <?php endif; ?>
                                </div>

                                <form id="<?= htmlspecialchars($formId) ?>" method="post" action="/app/actions/bases/module_commercial_save.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="base_id" value="<?= (int)$base['id'] ?>">
                                    <input type="hidden" name="module" value="<?= htmlspecialchars($module['slug']) ?>">

                                    <div class="c-module-config-grid">
                                        <div class="c-form-group">
                                            <label>Categoria comercial</label>
                                            <select class="c-input" name="commercial_category">
                                                <option value="included" <?= $module['commercial_category'] === 'included' ? 'selected' : '' ?>>Incluído</option>
                                                <option value="extra" <?= $module['commercial_category'] === 'extra' ? 'selected' : '' ?>>Extra</option>
                                                <option value="coming_soon" <?= $module['commercial_category'] === 'coming_soon' ? 'selected' : '' ?>>Em breve</option>
                                            </select>
                                        </div>

                                        <div class="c-form-group">
                                            <label>Visibilidade</label>
                                            <select class="c-input" name="status">
                                                <option value="1" <?= (int)$module['commercial_status'] === 1 ? 'selected' : '' ?>>Visível</option>
                                                <option value="0" <?= (int)$module['commercial_status'] !== 1 ? 'selected' : '' ?>>Oculto</option>
                                            </select>
                                        </div>

                                        <div class="c-form-group">
                                            <label>Mensal</label>
                                            <input class="c-input" name="monthly_price" placeholder="0,00" value="<?= $module['monthly_price'] !== null ? htmlspecialchars(number_format((float)$module['monthly_price'], 2, ',', '.')) : '' ?>">
                                        </div>

                                        <div class="c-form-group">
                                            <label>Anual</label>
                                            <input class="c-input" name="annual_price" placeholder="0,00" value="<?= $module['annual_price'] !== null ? htmlspecialchars(number_format((float)$module['annual_price'], 2, ',', '.')) : '' ?>">
                                        </div>

                                        <div class="c-form-group">
                                            <label>Ordem</label>
                                            <input class="c-input" name="display_order" type="number" value="<?= (int)$module['display_order'] ?>">
                                        </div>
                                    </div>

                                    <?php if ($module['slug'] === 'participantes'): ?>
                                        <div class="c-module-special">
                                            <h3>Configuração da base</h3>
                                            <p>Define como esta base chama seus participantes. Projetos novos já nascem com esse padrão. Projetos existentes só mudam quando você usar Aplicar aos projetos.</p>

                                            <div class="c-module-config-grid">
                                                <div class="c-form-group">
                                                    <label>Tipo</label>
                                                    <select class="c-input" name="participant_context" data-participant-context>
                                                        <?php foreach ($participantTypeOptions as $typeSlug => $typeOption): ?>
                                                            <option
                                                                value="<?= htmlspecialchars($typeSlug) ?>"
                                                                data-singular="<?= htmlspecialchars($typeOption['singular']) ?>"
                                                                data-plural="<?= htmlspecialchars($typeOption['plural']) ?>"
                                                                <?= $participantContextOption === $typeSlug ? 'selected' : '' ?>
                                                            >
                                                                <?= htmlspecialchars($typeOption['plural']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <option value="custom" <?= $participantContextOption === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                                                    </select>
                                                </div>

                                                <div class="c-form-group" data-participant-custom>
                                                    <label>Singular</label>
                                                    <input class="c-input" name="participant_label" value="<?= htmlspecialchars($participantLabel) ?>">
                                                </div>

                                                <div class="c-form-group" data-participant-custom>
                                                    <label>Plural</label>
                                                    <input class="c-input" name="participant_label_plural" value="<?= htmlspecialchars($participantLabelPlural) ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($module['slug'] === 'financeiro'): ?>
                                        <div class="c-module-special">
                                            <h3>Modo do financeiro</h3>
                                            <p>Define se esta base usa o financeiro como caixa pessoal ou integrado ao módulo Participantes. Projetos novos já nascem com esse padrão.</p>

                                            <div class="c-module-config-grid">
                                                <div class="c-form-group">
                                                    <label>Modo</label>
                                                    <select class="c-input" name="finance_mode">
                                                        <option value="personal" <?= $financeMode === 'personal' ? 'selected' : '' ?>>Pessoal</option>
                                                        <option value="participants" <?= $financeMode !== 'personal' ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($financeLinkedLabel) ?>
                                                        </option>
                                                    </select>
                                                    <?php if (empty($baseParticipantSettings)): ?>
                                                        <small>Instale/configure Participantes antes de usar o modo vinculado.</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <button class="c-btn-secondary">Salvar configuração</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<style>
.c-module-list {
    display: grid;
    gap: 10px;
}

.c-module-section {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-top: 4px;
    padding: 10px 12px;
    border: 1px solid rgba(148, 163, 184, .22);
    background: rgba(15, 23, 42, .28);
}

.c-module-section:first-child {
    margin-top: 0;
}

.c-module-section > div {
    display: grid;
    gap: 3px;
}

.c-module-section strong {
    font-size: .92rem;
}

.c-module-section span {
    color: var(--muted);
    font-size: .82rem;
}

.c-module-section__count {
    min-width: 26px;
    border: 1px solid rgba(148, 163, 184, .22);
    border-radius: 6px;
    color: #e5e7eb;
    font-size: .78rem;
    font-weight: 800;
    line-height: 1;
    padding: 6px 8px;
    text-align: center;
}

.c-module-section--installed-addon {
    border-color: rgba(14, 165, 233, .28);
    background: rgba(14, 165, 233, .06);
}

.c-module-section--available-main {
    border-color: rgba(245, 158, 11, .3);
    background: rgba(245, 158, 11, .06);
}

.c-module-section--available-addon {
    border-color: rgba(14, 165, 233, .3);
    background: rgba(14, 165, 233, .05);
}

.c-page-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.c-module-protection {
    border-color: rgba(59, 130, 246, .32);
    background: rgba(59, 130, 246, .08);
}

.c-module-protection p {
    color: var(--muted);
    margin: 6px 0 0;
}

.c-btn-unlock {
    border-color: rgba(34, 197, 94, .45);
    background: rgba(34, 197, 94, .12);
}

.c-btn-lock {
    border-color: rgba(245, 158, 11, .45);
    background: rgba(245, 158, 11, .1);
}

.c-module-item {
    border: 1px solid var(--border);
    background: rgba(15, 23, 42, .42);
    padding: 12px;
}

.c-module-item--main {
    border-left: 3px solid rgba(34, 197, 94, .55);
}

.c-module-item--addon {
    border-color: rgba(14, 165, 233, .32);
    border-left: 3px solid rgba(14, 165, 233, .7);
    background: linear-gradient(90deg, rgba(14, 165, 233, .08), rgba(15, 23, 42, .42) 34%);
}

.c-module-item--available {
    border-color: rgba(245, 158, 11, .26);
    border-left: 3px solid rgba(245, 158, 11, .62);
    background: linear-gradient(90deg, rgba(245, 158, 11, .07), rgba(15, 23, 42, .42) 34%);
}

.c-module-item--available-addon {
    border-color: rgba(14, 165, 233, .3);
    border-left: 3px solid rgba(14, 165, 233, .68);
    background: linear-gradient(90deg, rgba(14, 165, 233, .07), rgba(15, 23, 42, .42) 34%);
}

.c-module-main,
.c-module-summary,
.c-module-actions {
    min-width: 0;
}

.c-module-item {
    display: grid;
    grid-template-columns: minmax(280px, 1fr) 110px 110px 110px 160px;
    gap: 12px;
    align-items: center;
}

.c-module-main strong {
    display: block;
}

.c-module-main span,
.c-module-summary span {
    display: block;
    color: var(--muted);
    font-size: .78rem;
}

.c-module-main p {
    margin: 5px 0 8px;
    color: var(--muted);
    font-size: .88rem;
}

.c-module-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.c-module-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 7px;
}

.c-module-actions form {
    margin: 0;
}

.c-module-actions button,
.c-module-actions .c-btn-secondary {
    width: 100%;
}

.c-module-locked-badge {
    border: 1px solid rgba(148, 163, 184, .28);
    background: rgba(148, 163, 184, .08);
    color: var(--muted);
    display: block;
    font-size: .82rem;
    padding: 9px 10px;
    text-align: center;
}

.c-btn-apply-settings {
    border-color: rgba(59, 130, 246, .5);
    background: rgba(59, 130, 246, .12);
}

.c-btn-uninstall {
    border-color: rgba(245, 158, 11, .45);
    background: rgba(245, 158, 11, .1);
    color: #f8d28a;
}

.c-module-config {
    grid-column: 1 / -1;
    border-top: 1px solid var(--border);
    margin-top: 4px;
    padding-top: 14px;
}

.c-module-panel-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.c-module-panel-actions form {
    margin: 0;
}

.c-module-panel-actions .c-btn-secondary,
.c-module-panel-actions .c-module-locked-badge {
    min-height: 34px;
}

.c-module-config-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(120px, 1fr));
    gap: 12px;
    align-items: end;
}

.c-module-special {
    border: 1px solid rgba(59, 130, 246, .28);
    background: rgba(59, 130, 246, .06);
    margin: 14px 0;
    padding: 14px;
}

.c-module-special h3 {
    margin: 0 0 6px;
}

.c-module-special p {
    color: var(--muted);
    margin: 0 0 12px;
}

.c-module-config[hidden] {
    display: none;
}

@media (max-width: 980px) {
    .c-module-section {
        align-items: stretch;
        flex-direction: column;
    }

    .c-module-section__count {
        align-self: flex-start;
    }

    .c-module-item {
        grid-template-columns: 1fr;
    }

    .c-module-config-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.querySelectorAll('[data-module-config]').forEach((button) => {
    button.addEventListener('click', () => {
        const target = document.getElementById(button.dataset.moduleConfig);
        if (!target) return;

        const willOpen = target.hidden;

        document.querySelectorAll('.c-module-config').forEach((panel) => {
            panel.hidden = true;
        });

        document.querySelectorAll('[data-module-config]').forEach((item) => {
            item.textContent = 'Editar módulo';
        });

        target.hidden = !willOpen;
        button.textContent = willOpen ? 'Fechar' : 'Editar módulo';
    });
});

function syncParticipantCustomFields(scope) {
    const select = scope.querySelector('[data-participant-context]');
    const customFields = scope.querySelectorAll('[data-participant-custom]');
    const singularInput = scope.querySelector('input[name="participant_label"]');
    const pluralInput = scope.querySelector('input[name="participant_label_plural"]');

    if (!select || customFields.length === 0) return;

    const update = () => {
        const selected = select.options[select.selectedIndex];

        if (select.value !== 'custom' && selected) {
            if (singularInput && selected.dataset.singular) singularInput.value = selected.dataset.singular;
            if (pluralInput && selected.dataset.plural) pluralInput.value = selected.dataset.plural;
        }

        customFields.forEach((field) => {
            field.style.display = select.value === 'custom' ? '' : 'none';
        });
    };

    select.addEventListener('change', update);
    update();
}

document.querySelectorAll('.c-module-config').forEach(syncParticipantCustomFields);
</script>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$moduleRuleText = !$laboratoryEnabled
    ? '<p>Em producao, esta tela mostra os modulos publicados nesta base oficial. Ajustes estruturais devem ser feitos no laboratorio e enviados por deploy.</p>'
    : ($showAvailableModules
        ? '<p>Os modulos disponiveis aparecem na lista, mas cada alteracao fica dentro do painel do proprio modulo.</p><p>Projetos ja criados devem receber a atualizacao pelo botao Sincronizar Modulos no projeto ou Aplicar aos projetos quando disponivel.</p>'
        : '<p>A tela mostra apenas modulos instalados. Use Ver disponiveis somente quando precisar instalar outro modulo.</p><p>Configuracao, instalacao e desinstalacao ficam dentro do painel individual de cada modulo.</p>');

$rightSidebarContent = '
<div class="c-card">
    <h3>Base</h3>
    <p><strong>Slug:</strong> '.htmlspecialchars($base['slug']).'</p>
    <p><strong>Projetos:</strong> '.(int)$base['total_projects'].'</p>
</div>

<br>

<div class="c-card">
    <h3>Regra</h3>
    '.$moduleRuleText.'
</div>
';

require APP_PATH . '/views/layout_admin.php';
