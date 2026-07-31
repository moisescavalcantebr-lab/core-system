<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$laboratoryEnabled = coreLaboratoryEnabled();
$environmentLabel = coreEnvironmentLabel();

/*
|--------------------------------------------------------------------------
| FILTRO
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

/*
|--------------------------------------------------------------------------
| BASES REGISTRADAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
SELECT 
    b.*,
    (SELECT COUNT(*) FROM projects p WHERE p.base_id = b.id) AS total_projects,
    (SELECT COUNT(*) FROM bases child WHERE child.cloned_from_id = b.id) AS total_clones
FROM bases b
ORDER BY b.id ASC
");

$registered = $stmt->fetchAll(PDO::FETCH_ASSOC);

$registeredMap = [];
foreach ($registered as $base) {
    $registeredMap[$base['slug']] = $base;
}

function baseInstalledModulesCount(string $slug): int
{
    $modulesPath = BASES_PATH . '/' . $slug . '/modules';

    if (!is_dir($modulesPath)) {
        return 0;
    }

    $count = 0;
    foreach (scandir($modulesPath) ?: [] as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        if (is_file($modulesPath . '/' . $moduleSlug . '/module.json')) {
            $count++;
        }
    }

    return $count;
}

/*
|--------------------------------------------------------------------------
| PASTAS FÍSICAS
|--------------------------------------------------------------------------
*/

$baseFolders = [];
if (is_dir(BASES_PATH)) {
    $baseFolders = array_values(array_filter(
        scandir(BASES_PATH) ?: [],
        fn($f) => $f !== '.' && $f !== '..' && is_dir(BASES_PATH . '/' . $f)
    ));
}

$baseFolderMap = array_fill_keys($baseFolders, true);
$baseSlugs = array_values(array_unique(array_merge($baseFolders, array_keys($registeredMap))));

usort($baseSlugs, function ($a, $b) use ($registeredMap) {
    $aRegistered = isset($registeredMap[$a]);
    $bRegistered = isset($registeredMap[$b]);

    if ($aRegistered && $bRegistered) {
        return ((int)$registeredMap[$a]['id']) <=> ((int)$registeredMap[$b]['id']);
    }

    if ($aRegistered !== $bRegistered) {
        return $aRegistered ? -1 : 1;
    }

    return strnatcasecmp((string)$a, (string)$b);
});

/*
|--------------------------------------------------------------------------
| FILTRO
|--------------------------------------------------------------------------
*/

if ($search) {
    $baseSlugs = array_values(array_filter($baseSlugs, function($folder) use ($search, $registeredMap) {
        $needle = strtolower($search);
        $name = strtolower((string)($registeredMap[$folder]['name'] ?? ''));

        return str_contains(strtolower($folder), $needle)
            || ($name !== '' && str_contains($name, $needle));
    }));
}

$totalBases = count($baseSlugs);

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Bases';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Bases</h1>
            <p class="c-page-subtitle">Clonagem e gerenciamento de bases</p>
        </div>

        <div class="c-page-actions">
            <span class="c-badge c-badge--info">
                Clonagem de Bases
            </span>
        </div>

    </div>

    <div class="c-page-content">

        <?php flash_show(); ?>

        <div class="c-card">
            <form method="get">
                <div class="c-form-grid">
                    <div class="c-form-group">
                        <label>Buscar base</label>
                        <input type="text" name="search"
                               placeholder="Buscar base..."
                               class="c-input"
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <button class="c-btn-secondary">
                    Filtrar
                </button>
            </form>
        </div>

        <div class="c-table-wrapper">

            <table class="c-table c-bases-table">

                <thead>
                <tr>
                    <th>Slug</th>
                    <th>Nome</th>
                    <th>Projetos</th>
                    <th>Status</th>
                    <th style="text-align:right;">Ações</th>
                </tr>
                </thead>

                <tbody>

                <?php if (empty($baseSlugs)): ?>

                    <tr>
                        <td colspan="5" class="c-text-center">
                            Nenhuma base encontrada.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($baseSlugs as $folder): ?>

                        <?php
                        $isRegistered = isset($registeredMap[$folder]);
                        $baseData = $registeredMap[$folder] ?? null;
                        $hasFolder = isset($baseFolderMap[$folder]) && is_dir(BASES_PATH . '/' . $folder);
                        $isProtected = $isRegistered && (int)($baseData['is_protected'] ?? 0) === 1;
                        $installedModulesCount = ($isRegistered && $hasFolder) ? baseInstalledModulesCount($folder) : 0;
                        $actionsPanelId = 'base-actions-' . preg_replace('/[^a-z0-9\-_]/', '-', strtolower((string)$folder));
                        $rowClasses = [];
                        if (!$isRegistered) {
                            $rowClasses[] = 'c-row-highlight';
                        }
                        if ($isRegistered && !$hasFolder) {
                            $rowClasses[] = 'c-row-missing';
                        }
                        ?>

                        <tr class="<?= htmlspecialchars(implode(' ', $rowClasses)) ?>">

                            <td><strong><?= htmlspecialchars($folder) ?></strong></td>

                            <td>
                                <?= $isRegistered ? htmlspecialchars($baseData['name']) : '—' ?>
                            </td>

                            <td>
                                <?php if ($isRegistered): ?>
                                    <span class="c-badge c-badge--neutral">
                                        <?= $baseData['total_projects'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="c-badge c-badge--neutral">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($isRegistered && $hasFolder): ?>
                                    <span class="c-badge c-badge--success">
                                        Pronta
                                    </span>
                                <?php elseif ($isProtected): ?>
                                    <span class="c-badge c-badge--warning">
                                        Protegida no Core
                                    </span>
                                <?php elseif ($isRegistered): ?>
                                    <span class="c-badge c-badge--warning">
                                        Pasta ausente
                                    </span>
                                <?php else: ?>
                                    <span class="c-badge c-badge--warning">
                                        Não registrada
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="c-bases-actions-cell" style="text-align:right;">
                                <button class="c-btn-secondary c-base-actions-toggle"
                                        type="button"
                                        data-base-actions="<?= htmlspecialchars($actionsPanelId) ?>">
                                    Ações
                                </button>
                            </td>

                        </tr>

                        <tr class="c-base-actions-panel-row" id="<?= htmlspecialchars($actionsPanelId) ?>" hidden>
                            <td colspan="5">

                                <?php if ($isRegistered && !$hasFolder): ?>

                                    <div class="c-bases-actions c-bases-actions-missing">
                                        <div class="c-bases-actions-row c-bases-actions-main">
                                            <span class="c-badge c-badge--warning">
                                                Arquivos ausentes em /bases/<?= htmlspecialchars($folder) ?>
                                            </span>

                                            <?php if ($isProtected): ?>
                                                <span class="c-badge c-badge--warning">
                                                    Exclusao bloqueada
                                                </span>
                                            <?php endif; ?>

                                            <a class="c-btn-secondary"
                                               href="/web/admin/bases/projects.php?id=<?= $baseData['id'] ?>">
                                                Projetos: <?= $baseData['total_projects'] ?>
                                            </a>
                                        </div>

                                        <p class="c-bases-missing-note">
                                            <?php if ($isProtected): ?>
                                                Esta base esta protegida e faz parte das bases oficiais do Core. Sincronize a pasta pelo Git/servidor antes de clonar, instalar modulos ou publicar novas alteracoes.
                                            <?php else: ?>
                                                Esta base existe no banco, mas a pasta da base nao foi encontrada no servidor. Restaure ou recrie a pasta antes de sincronizar, clonar ou instalar modulos.
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                <?php elseif ($isRegistered): ?>

                                    <div class="c-bases-actions">
                                        <div class="c-bases-actions-row c-bases-actions-main">

                                            <?php if ($laboratoryEnabled): ?>
                                                <a class="c-btn-secondary"
                                                   href="/web/admin/bases/clone.php?base_id=<?= $baseData['id'] ?>">
                                                    Clonar
                                                </a>
                                            <?php endif; ?>

                                            <a class="c-btn-secondary"
                                               href="/web/admin/bases/projects.php?id=<?= $baseData['id'] ?>">
                                                Projetos: <?= $baseData['total_projects'] ?>
                                            </a>

                                            <?php if ($folder !== 'base'): ?>

                                                <a class="c-btn-secondary"
                                                   href="/web/admin/bases/modules.php?id=<?= $baseData['id'] ?>">
                                                    Módulos: <?= $installedModulesCount ?>
                                                </a>

                                                <a class="c-btn-secondary"
                                                   href="/web/admin/bases/plans.php?id=<?= $baseData['id'] ?>">
                                                    Planos
                                                </a>

                                                <a class="c-btn-secondary"
                                                   href="/web/admin/bases/vitrine.php?id=<?= $baseData['id'] ?>">
                                                    Vitrine
                                                </a>

                                            <?php endif; ?>
                                        </div>

                                        <div class="c-bases-actions-row c-bases-actions-maintenance">

                                        <?php if (!$laboratoryEnabled): ?>

                                            <span class="c-badge c-badge--neutral">
                                                <?= htmlspecialchars($environmentLabel) ?>
                                            </span>

                                            <span class="c-bases-readonly-note">
                                                Base oficial. Ajustes estruturais ficam no laboratorio e chegam pelo deploy.
                                            </span>

                                        <?php elseif ($folder !== 'base'): ?>


                                            <?php if ((int)$baseData['total_projects'] > 0): ?>
                                                <form method="post" action="/app/actions/bases/sync_projects.php" class="c-inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="base_id" value="<?= (int)$baseData['id'] ?>">
                                                    <input type="hidden" name="mode" value="all">
                                                    <button class="c-btn-sync" title="Sincroniza arquivos da base e módulos nos projetos vinculados">Sync Tudo</button>
                                                </form>

                                                <form method="post" action="/app/actions/bases/sync_projects.php" class="c-inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="base_id" value="<?= (int)$baseData['id'] ?>">
                                                    <input type="hidden" name="mode" value="projects">
                                                    <button class="c-btn-sync" title="Sincroniza apenas arquivos da base">Projetos</button>
                                                </form>

                                                <form method="post" action="/app/actions/bases/sync_projects.php" class="c-inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="base_id" value="<?= (int)$baseData['id'] ?>">
                                                    <input type="hidden" name="mode" value="modules">
                                                    <button class="c-btn-sync" title="Sincroniza apenas módulos da base">Módulos</button>
                                                </form>
                                            <?php endif; ?>

                                            <a class="c-btn-secondary c-btn-protection <?= $baseData['is_protected'] ? 'is-unlock' : 'is-lock' ?>"
                                               href="/app/actions/bases/toggle_protection.php?id=<?= $baseData['id'] ?>">
                                                <?= $baseData['is_protected'] ? 'Desbloquear' : 'Bloquear' ?>
                                            </a>

                                            <?php if (!$baseData['is_protected']): ?>

                                                <form method="post" action="/app/actions/bases/update_schema.php" class="c-inline-form" onsubmit="return confirm('Atualizar o registro do schema desta base? Use isso quando a base estiver testada.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="base_id" value="<?= (int)$baseData['id'] ?>">
                                                    <button class="c-btn-schema" title="Registra o schema atual da base para futuras instalacoes">Atualizar Schema</button>
                                                </form>

                                                <?php if ((int)$baseData['total_projects'] > 0): ?>

                                                    <span class="c-badge c-badge--warning">
                                                        Com projetos
                                                    </span>

                                                <?php elseif ((int)$baseData['total_clones'] > 0): ?>

                                                    <span class="c-badge c-badge--warning">
                                                        Com clones
                                                    </span>

                                                <?php else: ?>

                                                    <a class="c-btn-danger c-btn-base-delete"
                                                       href="/app/actions/bases/delete.php?id=<?= $baseData['id'] ?>">
                                                        Excluir
                                                    </a>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <span class="c-badge c-badge--warning">
                                                    Protegida
                                                </span>

                                            <?php endif; ?>

                                        <?php else: ?>

                                            <span class="c-badge c-badge--warning">
                                                Protegida
                                            </span>

                                        <?php endif; ?>
                                        </div>

                                    </div>

                                <?php else: ?>

                                    <div class="c-bases-actions">
                                        <div class="c-bases-actions-row c-bases-actions-main">
                                            <?php if ($laboratoryEnabled): ?>
                                                <form method="post" action="/app/actions/bases/base_register.php" class="c-inline-form">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="slug" value="<?= htmlspecialchars($folder) ?>">
                                                    <button class="c-btn-register" title="Registra esta pasta como base pronta e desbloqueada no Core">
                                                        Registrar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="c-badge c-badge--warning">Nao registrada</span>
                                                <span class="c-bases-readonly-note">
                                                    Registre esta base no laboratorio e publique pelo deploy.
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php
$content = ob_get_clean();

$content .= '
<style>
.c-inline-form {
    display: inline-flex;
    margin: 0;
}

.c-base-actions-toggle {
    min-width: 92px;
}

.c-base-actions-panel-row[hidden] {
    display: none;
}

.c-base-actions-panel-row td {
    background: rgba(15, 23, 42, .28);
    border-top: 0;
    padding-top: 0;
}

.c-base-actions-panel-row .c-bases-actions {
    margin-left: auto;
}

.c-row-missing td {
    background: rgba(245, 158, 11, .045);
}

.c-bases-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    justify-content: flex-end;
    align-items: stretch;
    min-width: min(100%, 720px);
}

.c-bases-actions-missing {
    border: 1px solid rgba(245, 158, 11, .22);
    background: rgba(245, 158, 11, .06);
    padding: 10px;
    border-radius: 8px;
}

.c-bases-missing-note {
    margin: 4px 0 0;
    color: var(--muted-color);
    font-size: 12px;
    line-height: 1.45;
    text-align: right;
}

.c-bases-readonly-note {
    color: #9fb0c8;
    font-size: 12px;
    line-height: 1.35;
    max-width: 280px;
}

.c-bases-actions-row {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    flex-wrap: wrap;
    align-items: center;
}

.c-bases-actions-main {
    padding-bottom: 2px;
}

.c-bases-actions-maintenance {
    padding-top: 2px;
    border-top: 1px solid rgba(148, 163, 184, .14);
}

.c-bases-actions .c-btn-secondary,
.c-bases-actions .c-btn-danger,
.c-bases-actions .c-btn-sync,
.c-bases-actions .c-btn-register {
    min-height: 28px;
}

.c-btn-sync {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 7px 11px;
    border: 1px solid rgba(255, 255, 255, .78);
    border-radius: 8px;
    background: #f8fafc;
    color: #0f172a;
    font-size: 12px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 1px 0 rgba(255, 255, 255, .35) inset;
}

.c-btn-sync:hover {
    background: #ffffff;
    color: #020617;
}

.c-btn-register {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 7px 11px;
    border: 1px solid rgba(34, 197, 94, .52);
    border-radius: 8px;
    background: rgba(34, 197, 94, .16);
    color: #86efac;
    font-size: 12px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
}

.c-btn-register:hover {
    background: rgba(34, 197, 94, .24);
    border-color: rgba(34, 197, 94, .72);
}

.c-btn-protection {
    border-color: rgba(34, 197, 94, .38) !important;
}

.c-btn-protection.is-unlock {
    background: rgba(34, 197, 94, .12) !important;
    color: #86efac !important;
}

.c-btn-protection.is-unlock:hover {
    background: rgba(34, 197, 94, .2) !important;
    border-color: rgba(34, 197, 94, .58) !important;
}

.c-btn-protection.is-lock {
    background: rgba(245, 158, 11, .12) !important;
    border-color: rgba(245, 158, 11, .38) !important;
    color: #fbbf24 !important;
}

.c-btn-protection.is-lock:hover {
    background: rgba(245, 158, 11, .2) !important;
    border-color: rgba(245, 158, 11, .58) !important;
}

.c-btn-schema {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 7px 11px;
    border: 1px solid rgba(34, 197, 94, .52);
    border-radius: 8px;
    background: rgba(34, 197, 94, .14);
    color: #86efac;
    font-size: 12px;
    font-weight: 800;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
}

.c-btn-schema:hover {
    background: rgba(34, 197, 94, .22);
    border-color: rgba(34, 197, 94, .72);
}

.c-btn-base-delete {
    border-color: rgba(239, 68, 68, .55) !important;
    background: rgba(239, 68, 68, .14) !important;
    color: #fca5a5 !important;
}

.c-btn-base-delete:hover {
    background: rgba(239, 68, 68, .24) !important;
    border-color: rgba(239, 68, 68, .75) !important;
}

@media (max-width: 760px) {
    .c-bases-table thead {
        display: none;
    }

    .c-bases-table,
    .c-bases-table tbody,
    .c-bases-table tr,
    .c-bases-table td {
        display: block;
        width: 100%;
    }

    .c-bases-table tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr) auto auto;
        gap: 10px 12px;
        padding: 14px 12px;
        border-bottom: 1px solid var(--border-color);
    }

    .c-bases-table .c-base-actions-panel-row {
        display: block;
        padding: 0 12px 14px;
    }

    .c-bases-table .c-base-actions-panel-row[hidden] {
        display: none;
    }

    .c-bases-table .c-base-actions-panel-row td {
        display: block;
        padding: 12px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: rgba(15, 23, 42, .34);
    }

    .c-bases-table td {
        padding: 0;
        border: 0;
        min-width: 0;
    }

    .c-bases-actions-cell {
        text-align: right !important;
        padding-top: 0 !important;
    }

    .c-base-actions-toggle {
        min-height: 38px;
        width: 100%;
    }

    .c-bases-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        justify-content: stretch;
        min-width: 0;
    }

    .c-bases-actions-row {
        display: contents;
    }

    .c-bases-actions-maintenance {
        padding-top: 0;
        border-top: 0;
    }

    .c-bases-readonly-note {
        max-width: none;
    }

    .c-bases-actions .c-inline-form,
    .c-bases-actions .c-btn-secondary,
    .c-bases-actions .c-btn-danger,
    .c-bases-actions .c-btn-schema,
    .c-bases-actions .c-btn-sync,
    .c-bases-actions .c-btn-register,
    .c-bases-actions .c-badge {
        width: 100%;
    }

    .c-bases-actions .c-inline-form button,
    .c-bases-actions .c-btn-secondary,
    .c-bases-actions .c-btn-danger,
    .c-bases-actions .c-btn-schema,
    .c-bases-actions .c-btn-sync,
    .c-bases-actions .c-btn-register {
        justify-content: center;
        min-height: 42px;
        border-radius: 8px;
        white-space: normal;
    }
}

@media (max-width: 430px) {
    .c-bases-table tr {
        grid-template-columns: minmax(0, .95fr) minmax(0, 1.1fr) auto auto;
        gap: 8px;
    }
}
</style>
<script>
document.querySelectorAll("[data-base-actions]").forEach((button) => {
    button.addEventListener("click", () => {
        const target = document.getElementById(button.dataset.baseActions);
        if (!target) return;

        const willOpen = target.hidden;

        document.querySelectorAll(".c-base-actions-panel-row").forEach((panel) => {
            panel.hidden = true;
        });

        document.querySelectorAll("[data-base-actions]").forEach((item) => {
            item.textContent = "Ações";
        });

        target.hidden = !willOpen;
        button.textContent = willOpen ? "Fechar" : "Ações";
    });
});
</script>
';

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Informações</h3>

<p>Total: '.$totalBases.' base(s)</p>
<p>A base principal permanece limpa. Módulos entram nas bases de segmento.</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';
