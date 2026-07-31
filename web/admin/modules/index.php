<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$modulesPath = ROOT_PATH . '/modules';
$modules = [];
$categories = [];
$segments = [];
$baseNames = [];
$totalBases = 0;
$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));
$segmentFilter = trim((string)($_GET['segment'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$kindFilter = trim((string)($_GET['kind'] ?? 'main'));

if (!in_array($kindFilter, ['main', 'addon', 'all'], true)) {
    $kindFilter = 'main';
}

$baseStmt = $pdo->query("SELECT slug, name FROM bases WHERE slug <> 'base' ORDER BY name ASC");
foreach ($baseStmt->fetchAll(PDO::FETCH_ASSOC) as $baseRow) {
    $baseNames[(string)$baseRow['slug']] = (string)$baseRow['name'];
}
$totalBases = count($baseNames);

function moduleRecommendedLabel(array $recommendedBases, array $baseNames): string
{
    $recommendedBases = array_values(array_filter(array_map('strval', $recommendedBases)));

    if (empty($recommendedBases)) {
        return 'Oculto';
    }

    if (in_array('geral', $recommendedBases, true) || count($recommendedBases) > 1) {
        return 'Geral';
    }

    $baseSlug = $recommendedBases[0];
    return $baseNames[$baseSlug] ?? $baseSlug;
}

if (is_dir($modulesPath)) {
    foreach (scandir($modulesPath) as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $modulePath = $modulesPath . '/' . $moduleSlug;
        $manifestPath = $modulePath . '/module.json';

        if (!is_dir($modulePath) || !is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            continue;
        }

        if ($moduleSlug === 'planos' || ($manifest['status'] ?? '') === 'legacy') {
            continue;
        }

        $installedBases = 0;

        if (is_dir(BASES_PATH)) {
            foreach (scandir(BASES_PATH) as $baseSlug) {
                if ($baseSlug === '.' || $baseSlug === '..' || $baseSlug === 'base') {
                    continue;
                }

                if (is_dir(BASES_PATH . '/' . $baseSlug . '/modules/' . $moduleSlug)) {
                    $installedBases++;
                }
            }
        }

        $modules[] = [
            'slug' => $moduleSlug,
            'label' => $manifest['label'] ?? $moduleSlug,
            'description' => $manifest['description'] ?? '',
            'version' => $manifest['version'] ?? '',
            'status' => $manifest['status'] ?? 'draft',
            'kind' => $manifest['kind'] ?? 'main',
            'category' => $manifest['category'] ?? 'Geral',
            'segments' => $manifest['segments'] ?? ['geral'],
            'recommended_bases' => $manifest['recommended_bases'] ?? ['geral'],
            'tags' => $manifest['tags'] ?? [],
            'tables' => $manifest['database']['tables'] ?? [],
            'copy' => $manifest['copy'] ?? [],
            'installed_bases' => $installedBases,
        ];

        $categories[$manifest['category'] ?? 'Geral'] = true;

        foreach (($manifest['segments'] ?? ['geral']) as $segment) {
            $segments[(string)$segment] = true;
        }
    }
}

$modules = array_values(array_filter($modules, function(array $module) use ($search, $categoryFilter, $segmentFilter, $statusFilter, $kindFilter) {
    $haystack = strtolower($module['label'] . ' ' . $module['slug'] . ' ' . $module['description'] . ' ' . implode(' ', $module['tags']));

    if ($kindFilter !== 'all' && ($module['kind'] ?? 'main') !== $kindFilter) {
        return false;
    }

    if ($search !== '' && !str_contains($haystack, strtolower($search))) {
        return false;
    }

    if ($categoryFilter !== '' && $module['category'] !== $categoryFilter) {
        return false;
    }

    if ($segmentFilter !== '' && !in_array($segmentFilter, $module['segments'], true)) {
        return false;
    }

    if ($statusFilter !== '' && $module['status'] !== $statusFilter) {
        return false;
    }

    return true;
}));

ksort($categories);
ksort($segments);
usort($modules, fn($a, $b) => strcmp($a['label'], $b['label']));

$title = 'Módulos';

ob_start();
?>

<div class="c-page c-modules-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Módulos</h1>
            <p class="c-page-subtitle">Biblioteca do core para instalar em bases de segmento</p>
        </div>

        <div class="c-page-actions c-module-page-actions">
            <a class="c-btn-secondary" href="/web/admin/bases/index.php">
                Ver Bases
            </a>
        </div>
    </div>

    <div class="c-page-content">

        <div class="c-card">
            <form method="get">
                <div class="c-module-kind-tabs">
                    <a class="c-btn-secondary <?= $kindFilter === 'main' ? 'is-active' : '' ?>" href="/web/admin/modules/index.php?kind=main">
                        Principais
                    </a>
                    <a class="c-btn-secondary <?= $kindFilter === 'addon' ? 'is-active' : '' ?>" href="/web/admin/modules/index.php?kind=addon">
                        Addons
                    </a>
                    <a class="c-btn-secondary <?= $kindFilter === 'all' ? 'is-active' : '' ?>" href="/web/admin/modules/index.php?kind=all">
                        Todos
                    </a>
                </div>

                <input type="hidden" name="kind" value="<?= htmlspecialchars($kindFilter) ?>">

                <div class="c-modules-filter-grid">
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
                        <label>Status</label>
                        <select class="c-input" name="status">
                            <option value="">Todos</option>
                            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        </select>
                    </div>
                </div>

                <button class="c-btn-secondary">Filtrar</button>
            </form>
        </div>

        <div class="c-card">

            <?php if (empty($modules)): ?>

                <p>Nenhum módulo encontrado na pasta raiz <strong>modules/</strong>.</p>

            <?php else: ?>

                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th>Versão</th>
                                <th>Tabelas</th>
                                <th>Bases</th>
                                <th>Status</th>
                                <th style="text-align:right;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($module['label']) ?></strong>
                                        <div><?= htmlspecialchars($module['slug']) ?></div>
                                    </td>
                                    <td>
                                        <span class="c-badge c-badge--<?= ($module['kind'] ?? 'main') === 'addon' ? 'info' : 'neutral' ?>">
                                            <?= ($module['kind'] ?? 'main') === 'addon' ? 'Addon' : 'Principal' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="c-badge c-badge--neutral">
                                            <?= htmlspecialchars($module['category']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($module['version']) ?></td>
                                    <td>
                                        <?php if (empty($module['tables'])): ?>
                                            <span class="c-badge c-badge--neutral">Sem tabelas</span>
                                        <?php else: ?>
                                            <span class="c-badge c-badge--info">
                                                <?= count($module['tables']) ?> tabela(s)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="c-badge <?= $module['installed_bases'] > 0 ? 'c-badge--success' : 'c-badge--neutral' ?>">
                                            <?= (int)$module['installed_bases'] ?>/<?= (int)$totalBases ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="c-badge c-badge--warning">
                                            <?= htmlspecialchars($module['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right; white-space:nowrap;">
                                        <a class="c-btn-secondary" href="/web/admin/modules/view.php?module=<?= urlencode($module['slug']) ?>">
                                            Ver detalhes
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>

    </div>

    <style>
    .c-module-kind-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .c-module-kind-tabs .is-active {
        border-color: rgba(59, 130, 246, .7);
        background: rgba(59, 130, 246, .18);
    }
    </style>

</div>

<style>
.c-module-page-actions {
    align-items: center;
}

body:has(.c-modules-page) .c-layout {
    grid-template-columns: 220px minmax(0, 1fr);
}

body:has(.c-modules-page) .c-content {
    max-width: none;
}

.c-modules-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) minmax(130px, 160px) minmax(130px, 160px) minmax(130px, 160px);
    gap: 10px;
}

@media (max-width: 900px) {
    .c-modules-filter-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Fluxo</h3>
    <p>Core mantém o módulo original.</p>
    <p>Base recebe uma cópia adaptável.</p>
    <p>Projeto sincroniza módulos a partir da base.</p>
</div>

<br>

<div class="c-card">
    <h3>Resumo</h3>
    <p><strong>Módulos:</strong> '.count($modules).'</p>
    <p><strong>Bases de segmento:</strong> '.$totalBases.'</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
