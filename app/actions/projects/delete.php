<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
header('Location: /public/admin/projects/view.php?id='.$id);
	exit;
}

/* Verificar se existe */
$stmt = $pdo->prepare("SELECT id, status FROM projects WHERE id = :id");
$stmt->execute(['id' => $id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
header('Location: /public/admin/projects/view.php?id='.$id);
    exit;
}

/* Evitar deletar duas vezes */
if ($project['status'] === 'deleted') {
    header('Location: /public/admin/projects/view.php?id='.$id);
    exit;
}

/* Soft delete */
$pdo->prepare("
    UPDATE projects
    SET status = 'deleted'
    WHERE id = :id
")->execute(['id' => $id]);

/* Log */
$pdo->prepare("
    INSERT INTO project_logs (project_id, action, message, level)
    VALUES (:project_id, 'deleted', 'Projeto marcado como deletado.', 'warning')
")->execute([
    'project_id' => $id
]);

header('Location: /public/admin/projects/view.php?id='.$id);
exit;
