<?php
$current = $_SERVER['REQUEST_URI'] ?? '';

function sidebarCurrentPath(): string
{
    return (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
}

function sidebarMenuMatches(string $url, string $key): bool
{
    $currentPath = sidebarCurrentPath();
    $targetPath = (string)(parse_url(PROJECT_URL . $url, PHP_URL_PATH) ?: $url);
    $targetDir = rtrim(str_replace('\\', '/', dirname($targetPath)), '/');

    if ($currentPath === $targetPath) {
        return true;
    }

    return $targetDir !== '' && str_starts_with($currentPath . '/', $targetDir . '/');
}

function sidebarIsActive(string $path): string
{
    return str_contains($_SERVER['REQUEST_URI'] ?? '', $path) ? 'active' : '';
}

function sidebarIcon(string $key): string
{
    $icons = [
        'dashboard' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-5h4v5h5v-9.5"/>',
        'financeiro' => '<path d="M12 3v18"/><path d="M17 7.5c-.8-1-2.2-1.5-4-1.5-2.2 0-4 1-4 2.7 0 1.8 1.8 2.4 4 2.8 2.4.5 4 1.1 4 2.9 0 1.7-1.8 2.6-4 2.6-2 0-3.6-.6-4.6-1.8"/>',
        'cartao_credito' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
        'divulgacao' => '<path d="M4 5h16v14H4z"/><path d="M8 9h8"/><path d="M8 13h5"/><path d="M16 13l2 2"/><path d="m18 13-2 2"/>',
        'participantes' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'jogadores' => '<circle cx="12" cy="12" r="9"/><path d="m8 9 4-3 4 3-1.5 5h-5L8 9Z"/><path d="m9.5 14-2 4"/><path d="m14.5 14 2 4"/>',
        'meu_time' => '<path d="M4 5h16v10a8 8 0 0 1-8 6 8 8 0 0 1-8-6V5Z"/><path d="M8 9h8"/><path d="M8 13h8"/>',
        'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'upgrade' => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/><path d="M5 21h14"/>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1a2 2 0 1 1 0 4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
    ];
    $path = $icons[$key] ?? '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8"/><path d="M8 13h8"/><path d="M8 17h5"/>';

    return '<span class="c-sidebar-link-icon" aria-hidden="true" style="width:16px;height:16px;min-width:16px;display:inline-flex;align-items:center;justify-content:center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;display:block;">' . $path . '</svg></span>';
}

if (!function_exists('participantMenuRouteSlug')) {
    function participantMenuRouteSlug(string $context, string $labelPlural = 'Participantes'): string
    {
        $slug = participantMenuNormalize($labelPlural);

        return $slug !== '' && !in_array($slug, ['participante', 'participantes'], true) ? $slug : 'participantes';
    }
}

if (!function_exists('participantMenuNormalize')) {
    function participantMenuNormalize(string $value): string
    {
        $value = trim($value);
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $normalized !== false ? $normalized : $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}

function projectModuleMenus(): array
{
    $modulesPath = PROJECT_PATH . '/modules';

    if (!is_dir($modulesPath)) {
        return [];
    }

    $menus = [];
    $installedModules = array_values(array_filter(scandir($modulesPath) ?: [], static function ($moduleSlug) use ($modulesPath) {
        return $moduleSlug !== '.'
            && $moduleSlug !== '..'
            && is_file($modulesPath . '/' . $moduleSlug . '/module.json');
    }));
    $participantContext = function_exists('getSetting')
        ? getSetting('participant_context', 'participante')
        : 'participante';
    $participantLabelPlural = function_exists('getSetting')
        ? getSetting('participant_label_plural', 'Participantes')
        : 'Participantes';
    $hiddenModules = [];
    $moduleManifests = [];

    foreach ($installedModules as $installedModuleSlug) {
        $installedManifestPath = $modulesPath . '/' . $installedModuleSlug . '/module.json';
        $installedManifest = json_decode((string)file_get_contents($installedManifestPath), true);

        if (!is_array($installedManifest)) {
            continue;
        }

        $moduleManifests[$installedModuleSlug] = $installedManifest;
        $context = $installedManifest['context'] ?? [];

        if (
            is_array($context)
            && !empty($context['hide_base_menu'])
            && (string)($context['base_module'] ?? '') !== ''
            && (string)($context['participant_context'] ?? '') === $participantContext
        ) {
            $hiddenModules[] = (string)$context['base_module'];
        }
    }

    foreach ($installedModules as $moduleSlug) {
        $manifest = $moduleManifests[$moduleSlug] ?? null;

        if (!is_array($manifest) || empty($manifest['menu']) || !is_array($manifest['menu'])) {
            continue;
        }

        if (in_array($moduleSlug, $hiddenModules, true)) {
            continue;
        }

        $menu = $manifest['menu'];

        if (empty($menu['label']) || empty($menu['url']) || empty($menu['key'])) {
            continue;
        }

        $label = (string)$menu['label'];
        $url = (string)$menu['url'];
        $key = (string)$menu['key'];

        if ($moduleSlug === 'participantes' && $participantContext !== 'participante') {
            $label = $participantLabelPlural;
            $routeSlug = participantMenuRouteSlug($participantContext, $participantLabelPlural);
            $url = '/admin/' . $routeSlug . '/index.php';
            $key = $routeSlug;
        }

        $menus[] = [
            'slug' => $moduleSlug,
            'label' => $label,
            'url' => $url,
            'key' => $key,
            'kind' => (string)($manifest['kind'] ?? 'module'),
            'attach_to' => array_values(array_filter(array_map('strval', (array)($manifest['attach_to'] ?? [])))),
            'order' => (int)($menu['order'] ?? 100),
        ];
    }

    usort($menus, function ($a, $b) {
        if ($a['order'] === $b['order']) {
            return strcmp($a['label'], $b['label']);
        }

        return $a['order'] <=> $b['order'];
    });

    return $menus;
}

$moduleMenus = projectModuleMenus();
$role = projectUserRole();
$activeModule = null;

foreach ($moduleMenus as $moduleMenu) {
    if (sidebarMenuMatches((string)$moduleMenu['url'], (string)$moduleMenu['key'])) {
        $activeModule = $moduleMenu;
        break;
    }
}

$relatedModuleSlugs = $activeModule ? (array)($activeModule['attach_to'] ?? []) : [];
?>

<nav class="c-sidebar-nav">

    <?php if ($role === 'ADMIN'): ?>
        <div class="c-sidebar-section">

            <div class="c-sidebar-title">Principal</div>

            <a href="<?= PROJECT_URL ?>/admin/dashboard.php"
               class="c-sidebar-link <?= sidebarIsActive('/admin/dashboard') ?>">
                <?= sidebarIcon('dashboard') ?>
                Dashboard
            </a>

        </div>
    <?php endif; ?>

    <?php if ($role === 'ADMIN' && !empty($moduleMenus)): ?>

        <div class="c-sidebar-section">

            <div class="c-sidebar-title">Modulos</div>

            <?php foreach ($moduleMenus as $moduleMenu): ?>

                <?php
                    $isActive = sidebarMenuMatches((string)$moduleMenu['url'], (string)$moduleMenu['key']);
                    $isRelated = !$isActive && in_array((string)$moduleMenu['slug'], $relatedModuleSlugs, true);
                ?>
                <a href="<?= PROJECT_URL . $moduleMenu['url'] ?>"
                   class="c-sidebar-link <?= $isActive ? 'active' : ($isRelated ? 'related' : '') ?>">
                    <?= sidebarIcon((string)$moduleMenu['key']) ?>
                    <?= htmlspecialchars($moduleMenu['label']) ?>
                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="c-sidebar-section">

        <div class="c-sidebar-title">Sistema</div>

        <a href="<?= PROJECT_URL ?>/admin/profile/index.php"
           class="c-sidebar-link <?= sidebarIsActive('profile') ?>">
            <?= sidebarIcon('profile') ?>
            Perfil
        </a>

        <?php if ($role === 'ADMIN'): ?>
            <a href="<?= PROJECT_URL ?>/admin/upgrade/index.php"
               class="c-sidebar-link <?= sidebarIsActive('/admin/upgrade') ?>">
                <?= sidebarIcon('upgrade') ?>
                Upgrade
            </a>

            <a href="<?= PROJECT_URL ?>/admin/settings/index.php"
               class="c-sidebar-link <?= sidebarIsActive('settings') ?>">
                <?= sidebarIcon('settings') ?>
                Configurações
            </a>
        <?php endif; ?>

    </div>

</nav>
