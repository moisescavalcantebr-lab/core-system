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
$stmt = $pdo->prepare("SELECT id, status, deletion_requested_at FROM projects WHERE id = :id");
$stmt->execute(['id' => $id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
header('Location: /public/admin/projects/view.php?id='.$id);
    exit;
}

/* Evitar agendar duas vezes */
if (!empty($project['deletion_requested_at']) || $project['status'] === 'deleted') {
    header('Location: /web/admin/projects/view.php?id='.$id);
    exit;
}

/* Agendar exclusão */
$pdo->prepare("
    UPDATE projects
    SET status = 'blocked',
        deletion_requested_at = NOW(),
        deletion_scheduled_at = DATE_ADD(NOW(), INTERVAL 30 DAY),
        deletion_canceled_at = NULL
    WHERE id = :id
")->execute(['id' => $id]);

/* Log */
$pdo->prepare("
    INSERT INTO project_logs (project_id, action, message, level)
    VALUES (:project_id, 'deletion_scheduled', 'Exclusão do projeto agendada para 30 dias.', 'warning')
")->execute([
    'project_id' => $id
]);

header('Location: /web/admin/projects/view.php?id='.$id);
exit;
