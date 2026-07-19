<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$projectId = (int)($_POST['id'] ?? 0);
$mode = $_POST['mode'] ?? 'selected';
$selectedModules = $_POST['modules'] ?? [];

if (!is_array($selectedModules)) {
    $selectedModules = [];
}

$stmt = $pdo->prepare("
    SELECT p.*, b.slug AS base_slug
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

if (!is_dir($baseModulesPath)) {
    header("Location: /web/admin/projects/view.php?id={$projectId}&modules=empty");
    exit;
}

function redirectProjectModulesSync(int $projectId, string $status): void
{
    header("Location: /web/admin/projects/view.php?id={$projectId}&{$status}=1");
    exit;
}

function ensureSyncDirectory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Nao foi possivel criar a pasta: ' . $directory);
    }
}

function copyModuleFile(string $sourceFile, string $destinationFile): bool
{
    if (is_file($destinationFile) && md5_file($sourceFile) === md5_file($destinationFile)) {
        return false;
    }

    $destinationDir = dirname($destinationFile);
    ensureSyncDirectory($destinationDir);

    if (is_file($destinationFile) && !is_writable($destinationFile)) {
        if (!@unlink($destinationFile)) {
            throw new RuntimeException('Sem permissao para substituir o arquivo: ' . $destinationFile);
        }
    }

    if (!@copy($sourceFile, $destinationFile)) {
        throw new RuntimeException('Falha ao copiar arquivo: ' . $destinationFile);
    }

    @chmod($destinationFile, 0644);

    return true;
}

function syncModuleFolder(string $source, string $destination): void
{
    if (!is_dir($source)) {
        return;
    }

    ensureSyncDirectory($destination);

    foreach (scandir($source) as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;

        if (is_dir($sourceFile)) {
            syncModuleFolder($sourceFile, $destinationFile);
            continue;
        }

        copyModuleFile($sourceFile, $destinationFile);
    }
}

function syncRefreshInstalledBaseModules(string $basePath): void
{
    $baseModulesPath = $basePath . '/modules';
    $rootModulesPath = ROOT_PATH . '/modules';

    if (!is_dir($baseModulesPath) || !is_dir($rootModulesPath)) {
        return;
    }

    foreach (scandir($baseModulesPath) ?: [] as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $baseModulePath = $baseModulesPath . '/' . $moduleSlug;
        $rootModulePath = $rootModulesPath . '/' . $moduleSlug;
        $manifestPath = $baseModulePath . '/module.json';
        $rootManifestPath = $rootModulePath . '/module.json';

        if (!is_file($manifestPath) || !is_file($rootManifestPath)) {
            continue;
        }

        syncRefreshModuleFiles($rootModulePath, $baseModulePath);

        $manifest = json_decode((string)file_get_contents($rootManifestPath), true);
        if (!is_array($manifest)) {
            continue;
        }

        foreach (($manifest['copy'] ?? []) as $copy) {
            if (!is_array($copy)) {
                continue;
            }

            $from = trim((string)($copy['from'] ?? ''), '/');
            $to = trim((string)($copy['to'] ?? ''), '/');

            if ($from === '' || $to === '' || str_contains($from, '..') || str_contains($to, '..')) {
                continue;
            }

            $sourcePath = $rootModulePath . '/' . $from;
            $destinationPath = $basePath . '/' . $to;

            if (is_dir($sourcePath)) {
                syncRefreshModuleFiles($sourcePath, $destinationPath);
            } elseif (is_file($sourcePath)) {
                copyModuleFile($sourcePath, $destinationPath);
            }
        }
    }
}

function syncRefreshModuleFiles(string $source, string $destination): void
{
    if (!is_dir($source)) {
        return;
    }

    ensureSyncDirectory($destination);

    foreach (scandir($source) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;
        $normalizedSourceFile = str_replace('\\', '/', $sourceFile);

        if (str_ends_with($normalizedSourceFile, '/database/schema.sql')) {
            continue;
        }

        if (is_dir($sourceFile)) {
            syncRefreshModuleFiles($sourceFile, $destinationFile);
            continue;
        }

        copyModuleFile($sourceFile, $destinationFile);
    }
}

function safeModuleSlug(string $slug): string
{
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
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
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function executeModuleSchema(?PDO $projectPdo, string $schemaPath): void
{
    if (!$projectPdo || !is_file($schemaPath)) {
        return;
    }

    $sql = file_get_contents($schemaPath);

    if ($sql === false || trim($sql) === '') {
        return;
    }

    $projectPdo->exec($sql);
}

syncRefreshInstalledBaseModules($basePath);

$availableModules = [];

foreach (scandir($baseModulesPath) as $moduleSlug) {
    if ($moduleSlug === '.' || $moduleSlug === '..') {
        continue;
    }

    if (is_file($baseModulesPath . '/' . $moduleSlug . '/module.json')) {
        $availableModules[] = $moduleSlug;
    }
}

if ($mode === 'all') {
    $selectedModules = $availableModules;
}

$selectedModules = array_values(array_unique(array_map('safeModuleSlug', $selectedModules)));
$projectPdo = projectDatabaseConnection($project);
$syncedModules = [];

try {
    foreach ($selectedModules as $moduleSlug) {
        if (!in_array($moduleSlug, $availableModules, true)) {
            continue;
        }

        $modulePath = $baseModulesPath . '/' . $moduleSlug;
        $manifestPath = $modulePath . '/module.json';
        $manifest = json_decode((string)file_get_contents($manifestPath), true);

        if (!is_array($manifest)) {
            continue;
        }

        syncModuleFolder($modulePath, $projectPath . '/modules/' . $moduleSlug);

        foreach (($manifest['copy'] ?? []) as $copy) {
            if (!is_array($copy)) {
                continue;
            }

            $from = trim((string)($copy['from'] ?? ''), '/');
            $to = trim((string)($copy['to'] ?? ''), '/');

            if ($from === '' || $to === '' || str_contains($from, '..') || str_contains($to, '..')) {
                continue;
            }

            $sourcePath = $modulePath . '/' . $from;
            $destinationPath = $projectPath . '/' . $to;

            if (is_dir($sourcePath)) {
                syncModuleFolder($sourcePath, $destinationPath);
            } elseif (is_file($sourcePath)) {
                copyModuleFile($sourcePath, $destinationPath);
            }
        }

        $schema = $manifest['database']['schema'] ?? '';

        if ($schema) {
            executeModuleSchema($projectPdo, $modulePath . '/' . ltrim((string)$schema, '/'));
        }

        $syncedModules[] = $manifest['label'] ?? $moduleSlug;
    }
} catch (Throwable $e) {
    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (:project_id, 'modules_sync', :message, 'error')
    ")->execute([
        'project_id' => $projectId,
        'message' => $e->getMessage(),
    ]);

    redirectProjectModulesSync($projectId, 'modules_sync_error');
}

$message = empty($syncedModules)
    ? 'Nenhum módulo sincronizado.'
    : 'Módulos sincronizados: ' . implode(', ', $syncedModules) . '.';

$pdo->prepare("
    INSERT INTO project_logs (project_id, action, message)
    VALUES (:project_id, 'modules_sync', :message)
")->execute([
    'project_id' => $projectId,
    'message' => $message,
]);

redirectProjectModulesSync($projectId, 'modules_synced');
