<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);
$mode = (string)($_POST['mode'] ?? 'all');
$allowedModes = ['projects', 'modules', 'all'];

if ($baseId <= 0 || !in_array($mode, $allowedModes, true)) {
    flash('error', 'Sincronizacao invalida.');
    header('Location: /web/admin/bases/index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = ?");
$stmt->execute([$baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    flash('error', 'Base nao encontrada.');
    header('Location: /web/admin/bases/index.php');
    exit;
}

$basePath = BASES_PATH . '/' . $base['slug'];
if (!is_dir($basePath)) {
    flash('error', 'Pasta da base nao encontrada.');
    header('Location: /web/admin/bases/index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, b.slug AS base_slug
    FROM projects p
    INNER JOIN bases b ON b.id = p.base_id
    WHERE p.base_id = ?
      AND p.status <> 'deleted'
    ORDER BY p.id ASC
");
$stmt->execute([$baseId]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

function baseSyncCleanOldFiles(string $baseDir, string $projectDir): void
{
    if (!is_dir($projectDir)) {
        return;
    }

    $files = scandir($projectDir);
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $projectFile = $projectDir . '/' . $file;
        $baseFile = $baseDir . '/' . $file;

        if (str_contains($projectFile, 'database.php') || str_contains($projectFile, 'project.json')) {
            continue;
        }

        if (!file_exists($baseFile)) {
            if (is_file($projectFile)) {
                @chmod($projectFile, 0644);
                @unlink($projectFile);
            } elseif (is_dir($projectFile)) {
                @rmdir($projectFile);
            }
            continue;
        }

        if (is_dir($projectFile) && is_dir($baseFile)) {
            baseSyncCleanOldFiles($baseFile, $projectFile);
        }
    }
}

function baseSyncEnsureWritableTarget(string $destFile, array &$stats): bool
{
    $destDir = dirname($destFile);

    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        $stats['failed'][] = $destDir . ' (sem permissao para criar pasta)';
        return false;
    }

    @chmod($destDir, 0755);

    if (file_exists($destFile)) {
        @chmod($destFile, 0644);
        if (!is_writable($destFile)) {
            $stats['failed'][] = $destFile . ' (sem permissao de escrita)';
            return false;
        }
    } elseif (!is_writable($destDir)) {
        $stats['failed'][] = $destDir . ' (sem permissao de escrita)';
        return false;
    }

    return true;
}

function baseSyncCopyFile(string $sourceFile, string $destFile, array &$stats): void
{
    if (!baseSyncEnsureWritableTarget($destFile, $stats)) {
        return;
    }

    $tmpFile = $destFile . '.sync_tmp';
    @unlink($tmpFile);

    if (!@copy($sourceFile, $tmpFile)) {
        $stats['failed'][] = $destFile . ' (falha ao preparar copia)';
        @unlink($tmpFile);
        return;
    }

    @chmod($tmpFile, 0644);

    if (!@rename($tmpFile, $destFile)) {
        if (!@copy($tmpFile, $destFile)) {
            $stats['failed'][] = $destFile . ' (falha ao substituir arquivo)';
            @unlink($tmpFile);
            return;
        }
        @unlink($tmpFile);
    }

    $stats['copied']++;
}

function baseSyncFolder(string $source, string $dest, array &$stats): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($dest) && !@mkdir($dest, 0755, true) && !is_dir($dest)) {
        $stats['failed'][] = $dest . ' (sem permissao para criar pasta)';
        return;
    }

    $files = scandir($source);
    if ($files === false) {
        $stats['failed'][] = $source . ' (falha ao ler pasta)';
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destFile = $dest . '/' . $file;

        if (str_contains($destFile, 'database.php') || str_contains($destFile, 'project.json')) {
            continue;
        }

        if (is_dir($sourceFile)) {
            baseSyncFolder($sourceFile, $destFile, $stats);
            continue;
        }

        if (!file_exists($destFile) || md5_file($sourceFile) !== md5_file($destFile)) {
            baseSyncCopyFile($sourceFile, $destFile, $stats);
        }
    }
}

function baseSyncRefreshFinanceBaseShell(array $base, string $basePath, array &$stats): void
{
    if ((string)($base['slug'] ?? '') !== 'financeiro') {
        return;
    }

    $coreBasePath = BASES_PATH . '/base';
    $files = [
        'web/admin/dashboard.php',
    ];

    foreach ($files as $relativeFile) {
        $sourceFile = $coreBasePath . '/' . $relativeFile;
        $destFile = $basePath . '/' . $relativeFile;

        if (!is_file($sourceFile)) {
            continue;
        }

        if (!is_file($destFile) || md5_file($sourceFile) !== md5_file($destFile)) {
            baseSyncCopyFile($sourceFile, $destFile, $stats);
        }
    }
}

function baseSyncProjectFiles(array $project, string $basePath): array
{
    $projectPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/');
    $stats = ['copied' => 0, 'failed' => []];

    baseSyncCleanOldFiles($basePath . '/app', $projectPath . '/app');
    baseSyncCleanOldFiles($basePath . '/web', $projectPath . '/web');
    baseSyncFolder($basePath . '/app', $projectPath . '/app', $stats);
    baseSyncFolder($basePath . '/web', $projectPath . '/web', $stats);

    $baseConfigPath = $basePath . '/project.json';
    $projectConfigPath = $projectPath . '/project.json';

    if (is_file($baseConfigPath) && is_file($projectConfigPath)) {
        $baseConfig = json_decode((string)file_get_contents($baseConfigPath), true);
        $projectConfig = json_decode((string)file_get_contents($projectConfigPath), true);

        if (is_array($baseConfig) && is_array($projectConfig)) {
            $projectConfig['version'] = $baseConfig['version'] ?? '1.0';
            file_put_contents($projectConfigPath, json_encode($projectConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    return $stats;
}

function baseSyncModuleFolder(string $source, string $destination, array &$stats): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
        $stats['failed'][] = $destination . ' (sem permissao para criar pasta)';
        return;
    }

    $files = scandir($source);
    if ($files === false) {
        $stats['failed'][] = $source . ' (falha ao ler pasta)';
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;

        if (is_dir($sourceFile)) {
            baseSyncModuleFolder($sourceFile, $destinationFile, $stats);
            continue;
        }

        if (!is_file($destinationFile) || md5_file($sourceFile) !== md5_file($destinationFile)) {
            baseSyncCopyFile($sourceFile, $destinationFile, $stats);
        }
    }
}

function baseSyncRefreshInstalledBaseModules(string $basePath, array &$stats): void
{
    $baseModulesPath = $basePath . '/modules';
    $rootModulesPath = ROOT_PATH . '/modules';

    if (!is_dir($baseModulesPath) || !is_dir($rootModulesPath)) {
        return;
    }

    $modules = scandir($baseModulesPath);
    if ($modules === false) {
        $stats['failed'][] = $baseModulesPath . ' (falha ao ler modulos instalados)';
        return;
    }

    foreach ($modules as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $installedManifest = $baseModulesPath . '/' . $moduleSlug . '/module.json';
        $rootModulePath = $rootModulesPath . '/' . $moduleSlug;
        $rootManifest = $rootModulePath . '/module.json';

        if (!is_file($installedManifest) || !is_file($rootManifest)) {
            continue;
        }

        baseSyncRefreshModuleFiles($rootModulePath, $baseModulesPath . '/' . $moduleSlug, $stats);

        $manifest = json_decode((string)file_get_contents($rootManifest), true);
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
                baseSyncRefreshModuleFiles($sourcePath, $destinationPath, $stats);
            } elseif (is_file($sourcePath)) {
                baseSyncCopyFile($sourcePath, $destinationPath, $stats);
            }
        }
    }
}

function baseSyncRefreshModuleFiles(string $source, string $destination, array &$stats): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
        $stats['failed'][] = $destination . ' (sem permissao para criar pasta)';
        return;
    }

    $files = scandir($source);
    if ($files === false) {
        $stats['failed'][] = $source . ' (falha ao ler pasta)';
        return;
    }

    foreach ($files as $file) {
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
            baseSyncRefreshModuleFiles($sourceFile, $destinationFile, $stats);
            continue;
        }

        if (!is_file($destinationFile) || md5_file($sourceFile) !== md5_file($destinationFile)) {
            baseSyncCopyFile($sourceFile, $destinationFile, $stats);
        }
    }
}

function baseSyncProjectDatabaseConnection(array $project): ?PDO
{
    $configPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/') . '/app/config/database.php';
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

function baseSyncExecuteModuleSchema(?PDO $projectPdo, string $schemaPath): void
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

function baseSyncProjectModules(array $project, string $basePath): array
{
    $projectPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/');
    $baseModulesPath = $basePath . '/modules';
    $stats = ['copied' => 0, 'failed' => [], 'modules' => []];

    if (!is_dir($baseModulesPath)) {
        return $stats;
    }

    $projectPdo = baseSyncProjectDatabaseConnection($project);
    $modules = scandir($baseModulesPath);

    if ($modules === false) {
        $stats['failed'][] = $baseModulesPath . ' (falha ao ler modulos)';
        return $stats;
    }

    foreach ($modules as $moduleSlug) {
        if ($moduleSlug === '.' || $moduleSlug === '..') {
            continue;
        }

        $modulePath = $baseModulesPath . '/' . $moduleSlug;
        $manifestPath = $modulePath . '/module.json';

        if (!is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            continue;
        }

        baseSyncModuleFolder($modulePath, $projectPath . '/modules/' . $moduleSlug, $stats);

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
                baseSyncModuleFolder($sourcePath, $destinationPath, $stats);
            } elseif (is_file($sourcePath)) {
                baseSyncCopyFile($sourcePath, $destinationPath, $stats);
            }
        }

        $schema = $manifest['database']['schema'] ?? '';
        if ($schema) {
            baseSyncExecuteModuleSchema($projectPdo, $modulePath . '/' . ltrim((string)$schema, '/'));
        }

        $stats['modules'][] = (string)($manifest['label'] ?? $moduleSlug);
    }

    return $stats;
}

$summary = [
    'total' => count($projects),
    'synced' => 0,
    'failed' => 0,
    'files' => 0,
    'modules' => 0,
];

if ($mode === 'modules' || $mode === 'all') {
    $refreshStats = ['copied' => 0, 'failed' => []];
    baseSyncRefreshInstalledBaseModules($basePath, $refreshStats);
    $summary['files'] += (int)$refreshStats['copied'];
}

if ($mode === 'projects' || $mode === 'all') {
    $refreshStats = ['copied' => 0, 'failed' => []];
    baseSyncRefreshFinanceBaseShell($base, $basePath, $refreshStats);
    $summary['files'] += (int)$refreshStats['copied'];
}

foreach ($projects as $project) {
    $messages = [];
    $hasFailures = false;

    if ($mode === 'projects' || $mode === 'all') {
        $stats = baseSyncProjectFiles($project, $basePath);
        $summary['files'] += (int)$stats['copied'];
        $messages[] = 'Arquivos da base: ' . (int)$stats['copied'];

        if (!empty($stats['failed'])) {
            $hasFailures = true;
            $messages[] = 'Falhas em arquivos: ' . implode(', ', array_slice($stats['failed'], 0, 5));
        }
    }

    if ($mode === 'modules' || $mode === 'all') {
        $stats = baseSyncProjectModules($project, $basePath);
        $summary['files'] += (int)$stats['copied'];
        $summary['modules'] += count($stats['modules']);
        $messages[] = empty($stats['modules'])
            ? 'Nenhum modulo sincronizado'
            : 'Modulos: ' . implode(', ', $stats['modules']);

        if (!empty($stats['failed'])) {
            $hasFailures = true;
            $messages[] = 'Falhas em modulos: ' . implode(', ', array_slice($stats['failed'], 0, 5));
        }
    }

    $summary[$hasFailures ? 'failed' : 'synced']++;

    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (?, 'base_batch_sync', ?, ?)
    ")->execute([
        (int)$project['id'],
        'Sincronizacao em lote da base "' . $base['slug'] . '": ' . implode('. ', $messages),
        $hasFailures ? 'warning' : 'info',
    ]);
}

$modeLabel = match ($mode) {
    'projects' => 'projetos',
    'modules' => 'modulos',
    default => 'projetos e modulos',
};

flash(
    $summary['failed'] > 0 ? 'error' : 'success',
    'Sincronizacao de ' . $modeLabel . ' concluida. Projetos: ' . $summary['total'] .
    '. Sucesso: ' . $summary['synced'] .
    '. Com falha: ' . $summary['failed'] .
    '. Arquivos alterados: ' . $summary['files'] . '.'
);

header('Location: /web/admin/bases/index.php');
exit;
