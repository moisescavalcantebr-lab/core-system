<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

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
    (SELECT COUNT(*) FROM projects p WHERE p.base_id = b.id) AS total_projects
FROM bases b
ORDER BY b.id ASC
");

$registered = $stmt->fetchAll(PDO::FETCH_ASSOC);

$registeredMap = [];
foreach ($registered as $base) {
    $registeredMap[$base['slug']] = $base;
}

/*
|--------------------------------------------------------------------------
| PASTAS FÍSICAS
|--------------------------------------------------------------------------
*/

$baseFolders = array_filter(
    scandir(BASES_PATH),
    fn($f) => $f !== '.' && $f !== '..' && is_dir(BASES_PATH . '/' . $f)
);

/*
|--------------------------------------------------------------------------
| FILTRO
|--------------------------------------------------------------------------
*/

if ($search) {
    $baseFolders = array_filter($baseFolders, function($folder) use ($search) {
        return str_contains(strtolower($folder), strtolower($search));
    });
}

$totalBases = count($baseFolders);

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

        <div class="c-table-wrapper">

            <table class="c-table">

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

                <?php if (empty($baseFolders)): ?>

                    <tr>
                        <td colspan="5" class="c-text-center">
                            Nenhuma base encontrada.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($baseFolders as $folder): ?>

                        <?php
                        $isRegistered = isset($registeredMap[$folder]);
                        $baseData = $registeredMap[$folder] ?? null;
                        ?>

                        <tr class="<?= !$isRegistered ? 'c-row-highlight' : '' ?>">

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
                                <?php if ($isRegistered): ?>
                                    <span class="c-badge c-badge--success">
                                        Pronta
                                    </span>
                                <?php else: ?>
                                    <span class="c-badge c-badge--warning">
                                        Não registrada
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td style="text-align:right;">

                                <?php if ($isRegistered): ?>

                                    <div style="display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap;">

                                        <a class="c-btn-secondary"
                                           href="/public/admin/bases/clone.php?base_id=<?= $baseData['id'] ?>">
                                            Clonar
                                        </a>

                                        <a class="c-btn-secondary"
                                           href="/public/admin/bases/projects.php?id=<?= $baseData['id'] ?>">
                                            Projetos: <?= $baseData['total_projects'] ?>
                                        </a>

                                        <?php if ($folder !== 'base'): ?>

                                            <a class="c-btn-secondary"
                                               href="/app/actions/bases/toggle_protection.php?id=<?= $baseData['id'] ?>">
                                                <?= $baseData['is_protected'] ? 'Desbloquear' : 'Bloquear' ?>
                                            </a>

                                            <?php if (!$baseData['is_protected']): ?>

                                                <a class="c-btn-danger"
                                                   href="/app/actions/bases/delete.php?id=<?= $baseData['id'] ?>">
                                                    Excluir
                                                </a>

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

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Filtro</h3>

<form method="get">

<input type="text" name="search"
       placeholder="Buscar base..."
       class="c-input"
       value="'.htmlspecialchars($search).'">

<br><br>

<button class="c-btn-secondary c-btn-block">
Buscar
</button>

</form>

</div>

<br>

<div class="c-card">

<h3>Informações</h3>

<p>Total: '.$totalBases.' base(s)</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';