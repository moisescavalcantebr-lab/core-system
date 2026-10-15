<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/module_sync.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
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

$syncedModules = [];

try {
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
    error_log('Module synchronization failed: ' . $e->getMessage());
    try {
        $pdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (:project_id, 'modules_sync', :message, 'error')
        ")->execute([
            'project_id' => $projectId,
            'message' => $e->getMessage(),
        ]);
    } catch (Throwable $logError) { error_log('Module synchronization log failed: ' . $logError->getMessage()); }
    flash('error', 'Nao foi possivel sincronizar os modulos. Confira as permissoes das pastas da base e do projeto e consulte os logs.');
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

flash('success', $message);
redirectProjectModulesSync($projectId, 'modules_synced');
