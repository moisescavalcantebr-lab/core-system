<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$projectId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, b.name AS base_name, b.slug AS base_slug
    FROM projects p
    INNER JOIN bases b ON b.id = p.base_id
    WHERE p.id = :id
");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project || empty($project['path'])) {
    die('Projeto inválido.');
}

$basePath = BASES_PATH . '/' . $project['base_slug'];
$projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');
$baseModulesPath = $basePath . '/modules';

function moduleFileChanges(string $source, string $destination, string $relative = ''): array
{
    $changes = [];

    if (!is_dir($source)) {
        return $changes;
    }

    foreach (scandir($source) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;
        $relativeFile = trim($relative . '/' . $file, '/');

        if (is_dir($sourceFile)) {
            $changes = array_merge($changes, moduleFileChanges($sourceFile, $destinationFile, $relativeFile));
            continue;
        }

        if (!is_file($destinationFile)) {
            $changes[] = ['type' => 'novo', 'file' => $relativeFile];
            continue;
        }

        if (md5_file($sourceFile) !== md5_file($destinationFile)) {
            $changes[] = ['type' => 'alterado', 'file' => $relativeFile];
        }
    }

    return $changes;
}

function projectDatabaseConnection(array $project): ?PDO
{
    $configPath = ROOT_PATH . '/' . ltrim($project['path'], '/') . '/app/config/database.php';

    if (!is_file($configPath)) {
        return null;
    }

    $dbConfig = require $configPath;

    try {
        return new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
            $dbConfig['user'],
            $dbConfig['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable $e) {
        return null;
    }
}

$projectPdo = projectDatabaseConnection($project);
$modules = [];

if (is_dir($baseModulesPath)) {
    foreach (scandir($baseModulesPath) as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $manifestPath = $baseModulesPath . '/' . $moduleSlug . '/module.json';

        if (!is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            continue;
        }

        $fileChanges = moduleFileChanges(
            $baseModulesPath . '/' . $moduleSlug,
            $projectPath . '/modules/' . $moduleSlug,
            'modules/' . $moduleSlug
        );

        foreach (($manifest['copy'] ?? []) as $copy) {
            if (!is_array($copy)) {
                continue;
            }

            $from = trim((string)($copy['from'] ?? ''), '/');
            $to = trim((string)($copy['to'] ?? ''), '/');

            if ($from === '' || $to === '') {
                continue;
            }

            $fileChanges = array_merge(
                $fileChanges,
                moduleFileChanges(
                    $baseModulesPath . '/' . $moduleSlug . '/' . $from,
                    $projectPath . '/' . $to,
                    $to
                )
            );
        }

        $tables = $manifest['database']['tables'] ?? [];
        $missingTables = [];

        if ($projectPdo && is_array($tables)) {
            foreach ($tables as $table) {
                $check = $projectPdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    AND table_name = ?
                ");
                $check->execute([(string)$table]);

                if ((int)$check->fetchColumn() === 0) {
                    $missingTables[] = (string)$table;
                }
            }
        } elseif (is_array($tables)) {
            $missingTables = $tables;
        }

        $modules[] = [
            'slug' => $moduleSlug,
            'label' => $manifest['label'] ?? $moduleSlug,
            'version' => $manifest['version'] ?? '',
            'description' => $manifest['description'] ?? '',
            'file_changes' => $fileChanges,
            'missing_tables' => $missingTables,
            'installed' => is_dir($projectPath . '/modules/' . $moduleSlug),
        ];
    }
}

$title = 'Sincronizar Módulos';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Sincronizar Módulos</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($project['name']) ?> a partir da base <?= htmlspecialchars($project['base_name']) ?></p>
        </div>

        <a class="c-btn-secondary" href="/web/admin/projects/view.php?id=<?= (int)$project['id'] ?>">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <?php if (empty($modules)): ?>

            <div class="c-card">
                <p>A base deste projeto ainda não possui módulos instalados.</p>
            </div>

        <?php else: ?>

            <form method="post" action="/app/actions/projects/modules_sync.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$project['id'] ?>">

                <div class="c-card">
                    <div class="c-table-wrapper">
                        <table class="c-table">
                            <thead>
                                <tr>
                                    <th>Usar</th>
                                    <th>Módulo</th>
                                    <th>Status</th>
                                    <th>Arquivos</th>
                                    <th>Banco</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                    <?php
                                    $hasChanges = !empty($module['file_changes']) || !empty($module['missing_tables']);
                                    ?>
                                    <tr>
                                        <td>
                                            <input
                                                type="checkbox"
                                                name="modules[]"
                                                value="<?= htmlspecialchars($module['slug']) ?>"
                                                <?= $hasChanges ? 'checked' : '' ?>
                                            >
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($module['label']) ?></strong>
                                            <div><?= htmlspecialchars($module['description']) ?></div>
                                        </td>
                                        <td>
                                            <span class="c-badge <?= $module['installed'] ? 'c-badge--success' : 'c-badge--warning' ?>">
                                                <?= $module['installed'] ? 'Instalado' : 'Novo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (empty($module['file_changes'])): ?>
                                                <span class="c-badge c-badge--success">OK</span>
                                            <?php else: ?>
                                                <span class="c-badge c-badge--warning">
                                                    <?= count($module['file_changes']) ?> alteração(ões)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (empty($module['missing_tables'])): ?>
                                                <span class="c-badge c-badge--success">OK</span>
                                            <?php else: ?>
                                                <span class="c-badge c-badge--warning">
                                                    <?= count($module['missing_tables']) ?> tabela(s)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <button class="c-btn-primary" name="mode" value="selected">
                    Sincronizar Selecionados
                </button>

                <button class="c-btn-secondary" name="mode" value="all">
                    Sincronizar Todos
                </button>

            </form>

        <?php endif; ?>

    </div>

</div>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Segurança</h3>
    <p>Este sync não remove módulos, não apaga tabelas e não exclui dados.</p>
    <p>Ele copia arquivos da base e executa apenas schemas seguros com CREATE TABLE IF NOT EXISTS.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
