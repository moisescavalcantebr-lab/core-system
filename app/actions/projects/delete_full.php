<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    die('ID inválido');
}

/* =========================
BUSCAR PROJETO
========================= */

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);

$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die('Projeto não encontrado.');
}

/* =========================
SEGURANÇA: só deletado
========================= */

if (empty($project['deletion_scheduled_at']) || strtotime((string)$project['deletion_scheduled_at']) > time()) {
    die('Projeto ainda não completou o prazo de exclusão.');
}

$projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');
$dbName = null;
$configPath = $projectPath . '/app/config/database.php';

if (file_exists($configPath)) {
    $dbConf = require $configPath;
    $dbName = $dbConf['name'] ?? null;
}

/* =========================
REMOVER ARQUIVOS
========================= */

if (is_dir($projectPath)) {

    function deleteFolder(string $dir): void {
        @chmod($dir, 0775);

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($path) && !is_link($path)) {
                deleteFolder($path);
                continue;
            }

            @chmod($path, 0664);

            if (!@unlink($path)) {
                throw new RuntimeException('Não foi possível remover o arquivo: ' . $path);
            }
        }

        @chmod($dir, 0775);

        if (!@rmdir($dir)) {
            throw new RuntimeException('Não foi possível remover a pasta: ' . $dir);
        }
    }

    try {
        deleteFolder($projectPath);
    } catch (Throwable $e) {
        flash('error', 'Não foi possível remover os arquivos do projeto. Verifique permissões no servidor. Detalhe: ' . $e->getMessage());
        redirect('/web/admin/projects/view.php?id=' . $id);
    }
}

/* =========================
REMOVER BANCO (SE EXISTIR)
========================= */

try {

    if ($dbName) {
        $pdo->exec("DROP DATABASE `{$dbName}`");
    }

} catch (Throwable $e) {
    // opcional: logar erro
}

/* =========================
REMOVER DO CORE
========================= */

$pdo->prepare("DELETE FROM plan_refund_requests WHERE project_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM plan_upgrade_requests WHERE project_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM project_wallet_movements WHERE project_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM project_wallet_requests WHERE project_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM project_access_tokens WHERE project_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM project_logs WHERE project_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);

flash('success', 'Projeto removido completamente.');
redirect('/web/admin/projects/index.php');
