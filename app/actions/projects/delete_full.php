<?php
require __DIR__ . '/../../bootstrap/bootstrap.php';
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

if ($project['status'] !== 'deleted') {
    die('Projeto precisa estar como DELETED.');
}

/* =========================
REMOVER ARQUIVOS
========================= */

$projectPath = ROOT_PATH . '/' . ltrim($project['path'], '/');

if (is_dir($projectPath)) {

    function deleteFolder($dir) {
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = "$dir/$file";

            is_dir($path)
                ? deleteFolder($path)
                : unlink($path);
        }

        return rmdir($dir);
    }

    deleteFolder($projectPath);
}

/* =========================
REMOVER BANCO (SE EXISTIR)
========================= */

try {

    $configPath = $projectPath . '/app/config/database.php';

    if (file_exists($configPath)) {

        $dbConf = require $configPath;

        $pdo->exec("DROP DATABASE `{$dbConf['name']}`");
    }

} catch (Throwable $e) {
    // opcional: logar erro
}

/* =========================
REMOVER DO CORE
========================= */

$pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);

flash('success', 'Projeto removido completamente.');
redirect('/public/admin/bases/projects.php?id='.$project['base_id']);