<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$projectId = (int)$_POST['id'];

/* =========================
   BUSCAR PROJETO
========================= */

$stmt = $pdo->prepare("
    SELECT p.*, b.slug AS base_slug
    FROM projects p
    INNER JOIN bases b ON b.id = p.base_id
    WHERE p.id = :id
");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project || empty($project['path']) || empty($project['base_slug'])) {
    die('Projeto inválido.');
}

$baseSlug = trim((string)($project['base_slug'] ?? ''));
$basePath = BASES_PATH . '/' . $baseSlug;
$projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');

if (!is_dir($basePath)) {
    die('Base do projeto não encontrada.');
}

/* =========================
   FUNÇÃO: LIMPAR ARQUIVOS ANTIGOS
========================= */

function cleanOldFiles($baseDir, $projectDir)
{
    if (!is_dir($projectDir)) return;

    $files = scandir($projectDir);

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;

        $projFile = $projectDir . '/' . $file;
        $baseFile = $baseDir . '/' . $file;

        // ignorar arquivos sensíveis
        if (str_contains($projFile, 'database.php')) continue;
        if (str_contains($projFile, 'project.json')) continue;

        if (!file_exists($baseFile)) {

            if (is_file($projFile)) {
                @chmod($projFile, 0644);
                @unlink($projFile);
            }

            if (is_dir($projFile)) {
                // remove pasta vazia
                @rmdir($projFile);
            }
        }

        // recursivo
        if (is_dir($projFile) && is_dir($baseFile)) {
            cleanOldFiles($baseDir . '/' . $file, $projectDir . '/' . $file);
        }
    }
}

function syncEnsureWritableTarget(string $destFile, array &$stats): bool
{
    $destDir = dirname($destFile);

    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            $stats['failed'][] = $destDir . ' (sem permissao para criar pasta)';
            return false;
        }
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

function syncCopyFile(string $sourceFile, string $destFile, array &$stats): void
{
    if (!syncEnsureWritableTarget($destFile, $stats)) {
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

/* =========================
   FUNÇÃO: SINCRONIZAR
========================= */

function syncFolder($source, $dest, array &$stats)
{
    if (!is_dir($source)) return;

    if (!is_dir($dest)) {
        if (!mkdir($dest, 0755, true) && !is_dir($dest)) {
            $stats['failed'][] = $dest;
            return;
        }
    }

    $files = scandir($source);

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;

        $sourceFile = $source . '/' . $file;
        $destFile   = $dest . '/' . $file;

        // ignorar arquivos sensíveis
        if (str_contains($destFile, 'database.php')) continue;
        if (str_contains($destFile, 'project.json')) continue;

        if (is_dir($sourceFile)) {

            syncFolder($sourceFile, $destFile, $stats);

        } else {

            // copia apenas se não existe ou foi alterado
            if (!file_exists($destFile) || md5_file($sourceFile) !== md5_file($destFile)) {
                syncCopyFile($sourceFile, $destFile, $stats);
            }
        }
    }
}

/* =========================
   EXECUTAR SYNC
========================= */

// 1. limpar arquivos antigos
cleanOldFiles($basePath . '/app', $projectPath . '/app');
cleanOldFiles($basePath . '/web', $projectPath . '/web');

// 2. copiar novos/alterados
$syncStats = [
    'copied' => 0,
    'failed' => [],
];

syncFolder($basePath . '/app', $projectPath . '/app', $syncStats);
syncFolder($basePath . '/web', $projectPath . '/web', $syncStats);

/* =========================
   ATUALIZAR VERSÃO
========================= */

$baseConfigPath = $basePath . '/project.json';
$projectConfigPath = $projectPath . '/project.json';

if (file_exists($baseConfigPath) && file_exists($projectConfigPath)) {

    $baseConfig = json_decode(file_get_contents($baseConfigPath), true);
    $projectConfig = json_decode(file_get_contents($projectConfigPath), true);

    $projectConfig['version'] = $baseConfig['version'] ?? '1.0';

    file_put_contents(
        $projectConfigPath,
        json_encode($projectConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/* =========================
   LOG
========================= */

$logMessage = 'Projeto sincronizado com base "' . $baseSlug . '". Arquivos copiados/alterados: ' . $syncStats['copied'] . '.';

if (!empty($syncStats['failed'])) {
    $logMessage .= ' Falhas: ' . implode(', ', array_slice($syncStats['failed'], 0, 10));
}

$pdo->prepare("
    INSERT INTO project_logs (project_id, action, message)
    VALUES (:project_id, 'sync', :message)
")->execute([
    'project_id' => $projectId,
    'message' => $logMessage,
]);

/* =========================
   REDIRECT
========================= */

header("Location: /web/admin/projects/view.php?id={$projectId}&synced=1");
exit;
