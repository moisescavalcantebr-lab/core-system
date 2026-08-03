<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();
requireProjectAdmin();

require_once APP_PATH . '/helpers/core_bridge.php';

$title = 'Dashboard';

function dashboardNormalizePanel(array $panel, string $moduleSlug): ?array
{
    $type = (string)($panel['type'] ?? 'panel');
    $order = (int)($panel['order'] ?? 100);
    $size = (string)($panel['size'] ?? 'default');
    $html = (string)($panel['html'] ?? '');
    $title = trim((string)($panel['title'] ?? ''));
    $group = dashboardGroupKey((string)($panel['group'] ?? $panel['context'] ?? $moduleSlug));
    $groupLabel = trim((string)($panel['group_label'] ?? $panel['context_label'] ?? $panel['label'] ?? $moduleSlug));

    if ($html === '' && $title === '') {
        return null;
    }

    return [
        'type' => $type !== '' ? $type : 'panel',
        'module' => (string)($panel['module'] ?? $moduleSlug),
        'group' => $group !== '' ? $group : dashboardGroupKey($moduleSlug),
        'group_label' => $groupLabel !== '' ? $groupLabel : ucfirst(str_replace(['-', '_'], ' ', $moduleSlug)),
        'order' => $order,
        'size' => in_array($size, ['default', 'wide', 'compact'], true) ? $size : 'default',
        'html' => $html,
        'title' => $title,
        'value' => (string)($panel['value'] ?? '-'),
        'class' => preg_replace('/[^a-z0-9\-_ ]/i', '', (string)($panel['class'] ?? '')) ?? '',
    ];
}

function dashboardGroupKey(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\-_]/', '-', $value) ?? '';
    return trim($value, '-_');
}

function dashboardArrayIsList(array $items): bool
{
    return $items === [] || array_keys($items) === range(0, count($items) - 1);
}

function dashboardInstalledModules(): array
{
    global $pdo, $project;

    $modulesPath = PROJECT_PATH . '/modules';
    $installedModules = [];
    $dashboardPanels = [];

    if (!is_dir($modulesPath)) {
        return [$installedModules, $dashboardPanels];
    }

    foreach (scandir($modulesPath) ?: [] as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $modulePath = $modulesPath . '/' . $moduleSlug;
        $manifestPath = $modulePath . '/module.json';

        if (!is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            continue;
        }

        $installedModules[] = [
            'slug' => $moduleSlug,
            'label' => (string)($manifest['label'] ?? $moduleSlug),
            'description' => (string)($manifest['description'] ?? ''),
            'category' => (string)($manifest['category'] ?? 'Modulo'),
            'kind' => (string)($manifest['kind'] ?? 'main'),
            'menu' => is_array($manifest['menu'] ?? null) ? $manifest['menu'] : null,
        ];

        $dashboardPath = $modulePath . '/dashboard.php';
        if (is_file($dashboardPath)) {
            $panel = require $dashboardPath;

            if (is_array($panel) && dashboardArrayIsList($panel)) {
                foreach ($panel as $panelItem) {
                    if (is_array($panelItem)) {
                        $normalized = dashboardNormalizePanel($panelItem, (string)$moduleSlug);
                        if ($normalized) {
                            $dashboardPanels[] = $normalized;
                        }
                    }
                }
            } elseif (is_array($panel)) {
                $normalized = dashboardNormalizePanel($panel, (string)$moduleSlug);
                if ($normalized) {
                    $dashboardPanels[] = $normalized;
                }
            }
        }
    }

    usort($dashboardPanels, fn($a, $b) => [$a['order'], $a['module']] <=> [$b['order'], $b['module']]);

    return [$installedModules, $dashboardPanels];
}

[$installedModules, $dashboardPanels] = dashboardInstalledModules();
$mainModules = array_values(array_filter($installedModules, fn(array $module) => ($module['kind'] ?? 'main') !== 'addon'));
$addonModules = array_values(array_filter($installedModules, fn(array $module) => ($module['kind'] ?? 'main') === 'addon'));
$dashboardPanels = array_values(array_filter($dashboardPanels, fn(array $panel) => ($panel['module'] ?? '') !== 'participantes'));
$dashboardGroups = [];

foreach ($dashboardPanels as $panel) {
    $groupKey = dashboardGroupKey((string)($panel['group'] ?? 'geral'));

    if ($groupKey === '') {
        $groupKey = 'geral';
    }

    if (!isset($dashboardGroups[$groupKey])) {
        $dashboardGroups[$groupKey] = [
            'key' => $groupKey,
            'label' => (string)($panel['group_label'] ?? ucfirst($groupKey)),
            'panels' => [],
        ];
    }

    $dashboardGroups[$groupKey]['panels'][] = $panel;
}

$activeDashboardGroup = '';
if (!empty($dashboardGroups)) {
    $requestedGroup = dashboardGroupKey((string)($_GET['dashboard_group'] ?? ''));
    $defaultGroup = function_exists('getSetting') ? dashboardGroupKey((string)getSetting('dashboard_default_group', '')) : '';

    if ($requestedGroup !== '' && isset($dashboardGroups[$requestedGroup])) {
        $activeDashboardGroup = $requestedGroup;
    } elseif ($defaultGroup !== '' && isset($dashboardGroups[$defaultGroup])) {
        $activeDashboardGroup = $defaultGroup;
    } else {
        $activeDashboardGroup = (string)array_key_first($dashboardGroups);
    }

    $dashboardPanels = $dashboardGroups[$activeDashboardGroup]['panels'] ?? [];
}

function dashboardParticipantSummary(array $installedModules): ?array
{
    global $pdo;

    $hasParticipants = false;
    foreach ($installedModules as $module) {
        if (($module['slug'] ?? '') === 'participantes') {
            $hasParticipants = true;
            break;
        }
    }

    if (!$hasParticipants || !is_file(PROJECT_PATH . '/web/admin/participantes/helpers.php')) {
        return null;
    }

    require_once PROJECT_PATH . '/web/admin/participantes/helpers.php';

    try {
        $counts = $pdo->query("
            SELECT
                COALESCE(SUM(status = 'active'), 0) AS active_total,
                COALESCE(SUM(status = 'inactive'), 0) AS inactive_total,
                COALESCE(SUM(status = 'pending'), 0) AS pending_total
            FROM participants
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return null;
    }

    return [
        'label' => participantLabel(true),
        'url' => participantAdminUrl(),
        'active' => (int)($counts['active_total'] ?? 0),
        'pending' => (int)($counts['pending_total'] ?? 0),
        'inactive' => (int)($counts['inactive_total'] ?? 0),
    ];
}

$participantSummary = dashboardParticipantSummary($installedModules);
$hasDedicatedParticipantPanel = false;
foreach ($dashboardGroups as $group) {
    foreach (($group['panels'] ?? []) as $panel) {
        if (in_array((string)($panel['module'] ?? ''), ['jogadores', 'participantes'], true)) {
            $hasDedicatedParticipantPanel = true;
            break 2;
        }
    }
}
$showParticipantSummary = false;

ob_start();
?>

<style>
.dash-home {
    display: grid;
    gap: 16px;
}

.dash-header-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.dash-group-tabs {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 6px;
}

.dash-group-tab {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-secondary);
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 12px;
    line-height: 1;
    text-decoration: none;
}

.dash-group-tab.is-active {
    border-color: rgba(59, 130, 246, .75);
    color: var(--text-primary);
    background: rgba(59, 130, 246, .12);
}

.dash-system-grid,
.dash-panel-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.dash-module {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.dash-module-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.dash-module-head h3,
.dash-module-head p,
.dash-chart-card h4,
.dash-list-card h4 {
    margin: 0;
}

.dash-module-head p,
.dash-metric-card small,
.dash-chart-card p,
.dash-list-card p {
    color: var(--text-secondary);
    font-size: 12px;
}

.dash-finance-layout,
.dash-football-layout,
.dash-metric-card,
.dash-pair-card,
.dash-chart-card,
.dash-status-card,
.dash-list-card {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .18);
    padding: 12px;
    min-width: 0;
}

.dash-finance-layout {
    display: grid;
    gap: 12px;
}

.dash-football-layout {
    display: grid;
    gap: 12px;
}

.dash-football-card .dash-pair-card,
.dash-football-card .dash-status-card {
    border: 0;
    background: transparent;
    padding: 0;
}

.dash-football-card .dash-pair-card {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.dash-football-card .dash-pair-card > div {
    overflow: visible;
}

.dash-football-metrics--secondary {
    margin-top: 6px;
}

.dash-card-subtitle {
    margin-top: 12px;
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 800;
}

.dash-match-highlight {
    width: fit-content;
    max-width: 100%;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    padding: 7px 10px;
    border: 1px solid rgba(59, 130, 246, .38);
    background: rgba(59, 130, 246, .08);
    color: var(--text-primary);
    font-size: 14px;
    line-height: 1.2;
}

.dash-match-highlight small {
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 800;
}

.dash-card-action {
    width: fit-content;
    margin-top: 6px;
}

.dash-metric-card,
.dash-pair-card > div {
    position: relative;
    overflow: hidden;
}

.dash-metric-card span,
.dash-pair-card span,
.dash-status-row span {
    display: block;
    color: var(--text-secondary);
    font-size: 12px;
}

.dash-metric-card strong {
    display: block;
    margin-top: 4px;
    font-size: 24px;
}

.dash-pair-card {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.dash-pair-card strong,
.dash-status-row strong {
    display: block;
    margin-top: 4px;
    font-size: 18px;
}

.dash-sparkline {
    width: 100%;
    height: 52px;
    display: block;
    margin-top: 18px;
    opacity: .9;
}

.dash-pair-card .dash-sparkline {
    height: 38px;
    margin-top: 20px;
}

.dash-sparkline polyline {
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.dash-sparkline--balance polyline { stroke: #38bdf8; }
.dash-sparkline--income polyline { stroke: #22c55e; }
.dash-sparkline--expense polyline { stroke: #ef4444; }

.dash-good { color: #22c55e; }
.dash-bad { color: #ef4444; }

.dash-bars {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
    align-items: end;
    min-height: 122px;
    margin-top: 12px;
}

.dash-bar-group {
    display: grid;
    gap: 6px;
    align-items: end;
    min-width: 0;
}

.dash-bar-pair {
    display: flex;
    gap: 4px;
    align-items: end;
    justify-content: center;
    min-height: 88px;
}

.dash-bar {
    width: 12px;
    min-height: 4px;
    display: block;
}

.dash-bar--income,
.dash-stack-active { background: #22c55e; }
.dash-bar--expense,
.dash-stack-inactive { background: #ef4444; }
.dash-stack-pending { background: #f59e0b; }

.dash-bar-group small {
    color: var(--text-secondary);
    font-size: 10px;
    text-align: center;
}

.dash-rank {
    display: grid;
    gap: 10px;
    margin-top: 12px;
}

.dash-rank-row {
    display: grid;
    grid-template-columns: minmax(80px, 120px) minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    font-size: 12px;
}

.dash-rank-row div {
    height: 8px;
    overflow: hidden;
    background: rgba(148, 163, 184, .18);
}

.dash-rank-row i {
    display: block;
    height: 100%;
    background: #3b82f6;
}

.dash-pie-card {
    min-height: 156px;
}

.dash-pie-wrap {
    display: grid;
    grid-template-columns: 108px minmax(0, 1fr);
    gap: 12px;
    align-items: center;
    margin-top: 12px;
}

.dash-pie {
    width: 102px;
    height: 102px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    position: relative;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .08);
}

.dash-pie::after {
    content: "";
    position: absolute;
    width: 54px;
    height: 54px;
    border-radius: 50%;
    background: var(--bg-card);
    box-shadow: 0 0 0 1px rgba(148, 163, 184, .16);
}

.dash-pie span {
    position: relative;
    z-index: 1;
    max-width: 50px;
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 800;
    line-height: 1.15;
    text-align: center;
}

.dash-pie-legend {
    display: grid;
    gap: 8px;
    min-width: 0;
}

.dash-pie-legend-row {
    display: grid;
    grid-template-columns: 10px minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    color: var(--text-secondary);
    font-size: 12px;
}

.dash-pie-legend-row i {
    width: 10px;
    height: 10px;
    display: block;
}

.dash-pie-legend-row span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dash-pie-legend-row strong {
    color: var(--text-primary);
    font-size: 12px;
}

.dash-upgrade-box {
    display: grid;
    gap: 8px;
    min-height: 112px;
    margin-top: 12px;
    padding: 12px;
    align-content: center;
    border: 1px solid rgba(139, 92, 246, .36);
    background: rgba(139, 92, 246, .08);
}

.dash-upgrade-box strong {
    color: var(--text-primary);
}

.dash-upgrade-box p {
    margin: 0;
}

.dash-upgrade-box .c-btn-secondary {
    width: fit-content;
}

.dash-status-card {
    display: grid;
    gap: 10px;
}

.dash-status-row,
.dash-list-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.dash-stack {
    display: flex;
    height: 12px;
    overflow: hidden;
    background: rgba(148, 163, 184, .18);
}

.dash-stack i {
    display: block;
    min-width: 0;
}

.dash-list-card {
    display: grid;
    gap: 8px;
}

.dash-list-row {
    font-size: 12px;
    border-top: 1px solid rgba(148, 163, 184, .16);
    padding-top: 8px;
}

.dash-empty-state {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 16px;
}

.dash-empty-state h3,
.dash-empty-state p {
    margin: 0;
}

.dash-empty-state p {
    margin-top: 8px;
    color: var(--text-secondary);
}

.dash-panel-card--wide {
    grid-column: 1 / -1;
}

.dash-participant-pill {
    min-height: 34px;
    display: inline-grid;
    grid-template-columns: auto auto auto auto;
    gap: 10px;
    align-items: center;
    padding: 0 12px;
    border: 1px solid rgba(139, 92, 246, .45);
    background: rgba(139, 92, 246, .08);
    color: var(--text-primary);
    text-decoration: none;
}

.dash-participant-pill span {
    color: var(--text-secondary);
    font-size: 11px;
}

.dash-participant-pill strong {
    font-size: 13px;
}

.dash-participant-pill b {
    font-size: 12px;
    font-weight: 800;
}

.dash-participant-pill:hover {
    border-color: rgba(139, 92, 246, .75);
    background: rgba(139, 92, 246, .14);
    color: var(--text-primary);
    text-decoration: none;
}

@media (min-width: 680px) {
    .dash-system-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .dash-finance-layout {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

}

@media (min-width: 1100px) {
    .dash-panel-grid {
        grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
        align-items: start;
    }

    .dash-module--finance,
    .dash-module--divulgacao,
    .dash-module--football {
        grid-column: 1 / -1;
    }

    .dash-module--finance .dash-finance-layout,
    .dash-module--divulgacao .dash-finance-layout,
    .dash-module--football .dash-football-layout {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .dash-module--finance {
        padding: 0;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .dash-module--finance .dash-module-head {
        padding: 0;
    }

    .dash-module--finance .dash-finance-layout {
        padding: 0;
        border: 0;
        background: transparent;
        box-shadow: none;
    }

    .dash-header-actions,
    .dash-group-tabs {
        width: 100%;
    }

    .dash-group-tab {
        flex: 1 1 auto;
        text-align: center;
    }

    .dash-participant-pill {
        width: 100%;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .dash-participant-pill span {
        grid-column: 1 / -1;
    }

    .dash-pie-wrap {
        grid-template-columns: 1fr;
        justify-items: center;
    }

    .dash-pie-legend {
        width: 100%;
    }
}
</style>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Dashboard</h1>
            <p class="c-page-subtitle">Visao geral do projeto e dos modulos instalados</p>
        </div>

        <?php if ($showParticipantSummary || count($dashboardGroups) > 1): ?>
            <div class="c-page-actions dash-header-actions">
                <?php if (count($dashboardGroups) > 1): ?>
                    <div class="dash-group-tabs" aria-label="Painel da dashboard">
                        <?php foreach ($dashboardGroups as $group): ?>
                            <?php $isActiveGroup = ($group['key'] ?? '') === $activeDashboardGroup; ?>
                            <a
                                class="dash-group-tab <?= $isActiveGroup ? 'is-active' : '' ?>"
                                href="?dashboard_group=<?= urlencode((string)$group['key']) ?>"
                            >
                                <?= htmlspecialchars((string)$group['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($showParticipantSummary): ?>
                <a href="<?= htmlspecialchars($participantSummary['url']) ?>" class="dash-participant-pill">
                    <span><?= htmlspecialchars((string)$participantSummary['label']) ?></span>
                    <b>Ativos <?= (int)$participantSummary['active'] ?></b>
                    <b>Pendentes <?= (int)$participantSummary['pending'] ?></b>
                    <b>Inativos <?= (int)$participantSummary['inactive'] ?></b>
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="c-page-content dash-home">

        <?php if (!empty($dashboardPanels)): ?>
            <div class="dash-panel-grid">
                <?php foreach ($dashboardPanels as $panel): ?>
                    <?php if (!empty($panel['html'])): ?>
                        <?= $panel['html'] ?>
                    <?php elseif (!empty($panel['title'])): ?>
                        <div class="c-dashboard-card dash-panel-card--<?= htmlspecialchars((string)$panel['size']) ?> <?= htmlspecialchars((string)$panel['class']) ?>">
                            <h4><?= htmlspecialchars((string)$panel['title']) ?></h4>
                            <div class="c-metric"><?= htmlspecialchars((string)($panel['value'] ?? '-')) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($dashboardPanels)): ?>
            <div class="dash-empty-state">
                <h3><?= empty($installedModules) ? 'Nenhum modulo instalado ainda.' : 'Nenhum indicador disponivel.' ?></h3>
                <p><?= empty($installedModules) ? 'Os dados principais aparecem aqui quando a base receber modulos.' : 'Os modulos instalados ainda nao enviaram dados para a dashboard.' ?></p>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
