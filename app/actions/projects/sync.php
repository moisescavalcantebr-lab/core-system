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

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project || empty($project['path'])) {
    die('Projeto inválido.');
}

$basePath    = ROOT_PATH . '/bases/base';
$projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');

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
                unlink($projFile);
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

/* =========================
   FUNÇÃO: SINCRONIZAR
========================= */

function syncFolder($source, $dest)
{
    if (!is_dir($source)) return;

    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
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

            syncFolder($sourceFile, $destFile);

        } else {

            // copia apenas se não existe ou foi alterado
            if (!file_exists($destFile) || md5_file($sourceFile) !== md5_file($destFile)) {
                copy($sourceFile, $destFile);
            }
        }
    }
}

/* =========================
   EXECUTAR SYNC
========================= */

// 1. limpar arquivos antigos
cleanOldFiles($basePath . '/app', $projectPath . '/app');
cleanOldFiles($basePath . '/public', $projectPath . '/public');

// 2. copiar novos/alterados
syncFolder($basePath . '/app', $projectPath . '/app');
syncFolder($basePath . '/public', $projectPath . '/public');

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

$pdo->prepare("
    INSERT INTO project_logs (project_id, action, message)
    VALUES (:project_id, 'sync', 'Projeto sincronizado com sucesso.')
")->execute([
    'project_id' => $projectId
]);

/* =========================
   REDIRECT
========================= */

header("Location: /public/admin/projects/view.php?id={$projectId}&synced=1");
exit;