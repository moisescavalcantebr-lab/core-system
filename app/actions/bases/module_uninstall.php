<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);
$moduleSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower((string)($_POST['module'] ?? '')));

function moduleUninstallRemovePath(string $path, string $allowedRoot): void
{
    $allowedRoot = rtrim(str_replace('\\', '/', $allowedRoot), '/');
    $normalizedPath = str_replace('\\', '/', $path);

    if (!str_starts_with($normalizedPath, $allowedRoot . '/')) {
        throw new RuntimeException('Caminho fora da base.');
    }

    if (is_file($path) || is_link($path)) {
        @chmod($path, 0644);
        if (!@unlink($path) && file_exists($path)) {
            throw new RuntimeException('Nao foi possivel remover o arquivo: ' . $path);
        }
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        moduleUninstallRemovePath($path . '/' . $file, $allowedRoot);
    }

    @chmod($path, 0755);
    if (!@rmdir($path) && is_dir($path)) {
        throw new RuntimeException('Nao foi possivel remover a pasta: ' . $path);
    }
}

function moduleUninstallRemoveSchemaBlock(string $basePath, string $moduleSlug): void
{
    $schemaPath = $basePath . '/app/database/schema.sql';

    if (!is_file($schemaPath)) {
        return;
    }

    $content = (string)file_get_contents($schemaPath);
    $marker = '-- MODULE: ' . $moduleSlug;

    if (!str_contains($content, $marker)) {
        return;
    }

    $pattern = "/\\n{0,2}-- ={20,}\\R" . preg_quote($marker, '/') . "\\R-- ={20,}\\R.*?(?=\\R\\R-- ={20,}\\R-- MODULE: |\\z)/s";
    $content = preg_replace($pattern, '', $content) ?? $content;

    file_put_contents($schemaPath, rtrim($content) . PHP_EOL);
}

function moduleUninstallRemoveSafeCopyTargets(string $targetRoot, string $moduleSlug, array $manifest, string $modulePath): void
{
    foreach (($manifest['copy'] ?? []) as $copy) {
        if (!is_array($copy)) {
            continue;
        }

        $to = trim((string)($copy['to'] ?? ''), '/');

        if ($to === '' || str_contains($to, '..')) {
            continue;
        }

        $basename = basename($to);
        $isSafeModuleDirectory = str_starts_with($to, 'web/admin/') && $basename === $moduleSlug;
        $sourcePath = $modulePath . '/' . trim((string)($copy['from'] ?? ''), '/');
        $isSafePublicFile = str_starts_with($to, 'web/') && is_file($sourcePath);

        if (!$isSafeModuleDirectory && !$isSafePublicFile) {
            continue;
        }

        moduleUninstallRemovePath($targetRoot . '/' . $to, $targetRoot);
    }
}

function moduleUninstallRemoveFromProjects(PDO $pdo, int $baseId, string $moduleSlug, array $manifest, string $modulePath): int
{
    $stmt = $pdo->prepare("
        SELECT id, path
        FROM projects
        WHERE base_id = ?
          AND status <> 'deleted'
    ");
    $stmt->execute([$baseId]);

    $updated = 0;

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $project) {
        $projectPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/');

        if (!is_dir($projectPath)) {
            continue;
        }

        moduleUninstallRemoveSafeCopyTargets($projectPath, $moduleSlug, $manifest, $modulePath);
        moduleUninstallRemovePath($projectPath . '/modules/' . $moduleSlug, $projectPath);

        $configPath = $projectPath . '/project.json';
        if (is_file($configPath)) {
            $config = json_decode((string)file_get_contents($configPath), true);
            if (is_array($config) && is_array($config['modules'] ?? null)) {
                $config['modules'] = array_values(array_filter(
                    $config['modules'],
                    fn($module) => strtolower((string)$module) !== $moduleSlug
                ));
                file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            }
        }

        $pdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'module_uninstall_sync', ?, 'info')
        ")->execute([
            (int)$project['id'],
            'Modulo removido dos arquivos do projeto: ' . $moduleSlug . '. Dados do banco preservados.',
        ]);

        $updated++;
    }

    return $updated;
}

try {
    if ($baseId <= 0 || $moduleSlug === '') {
        throw new RuntimeException('Dados invalidos.');
    }

    $stmt = $pdo->prepare('SELECT * FROM bases WHERE id = ? LIMIT 1');
    $stmt->execute([$baseId]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$base || (string)$base['slug'] === 'base') {
        throw new RuntimeException('Base invalida.');
    }

    if ((int)($base['is_protected'] ?? 0) === 1) {
        throw new RuntimeException('Desbloqueie a base antes de desinstalar modulos.');
    }

    $basePath = BASES_PATH . '/' . $base['slug'];
    $baseModulePath = $basePath . '/modules/' . $moduleSlug;
    $manifestPath = is_file($baseModulePath . '/module.json')
        ? $baseModulePath . '/module.json'
        : ROOT_PATH . '/modules/' . $moduleSlug . '/module.json';

    if (!is_dir($basePath) || !is_dir($baseModulePath)) {
        throw new RuntimeException('Modulo nao instalado nesta base.');
    }

    $manifest = is_file($manifestPath) ? json_decode((string)file_get_contents($manifestPath), true) : [];
    $manifest = is_array($manifest) ? $manifest : [];

    $updatedProjects = moduleUninstallRemoveFromProjects($pdo, $baseId, $moduleSlug, $manifest, $baseModulePath);
    moduleUninstallRemoveSafeCopyTargets($basePath, $moduleSlug, $manifest, $baseModulePath);
    moduleUninstallRemovePath($baseModulePath, $basePath);
    moduleUninstallRemoveSchemaBlock($basePath, $moduleSlug);

    $stmt = $pdo->prepare('UPDATE base_module_prices SET status = 0 WHERE base_id = ? AND module_slug = ?');
    $stmt->execute([$baseId, $moduleSlug]);

    flash('success', 'Modulo desinstalado da base e removido de ' . $updatedProjects . ' projeto(s). Dados do banco foram preservados.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('/web/admin/bases/modules.php?id=' . $baseId);
