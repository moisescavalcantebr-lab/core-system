<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectInstaller.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
header('Location: /public/admin/projects/view.php?id='.$id);
	exit;
}

/* =========================
   BUSCAR STATUS ATUAL
========================= */

$stmt = $pdo->prepare("
    SELECT status 
    FROM projects 
    WHERE id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$current = $stmt->fetchColumn();

if (!$current || $current === 'deleted') {
header('Location: /public/admin/projects/view.php?id='.$id);
	exit;
}

/* =========================
   DEFINIR PRÓXIMO STATUS
========================= */

$newStatus = match ($current) {
    'pending' => 'active',
    'active'  => 'blocked',
    'blocked' => 'active',
    default   => $current
};

/* =========================
   ATUALIZAR + SINCRONIZAR
========================= */

try {

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE projects
        SET status = :status
        WHERE id = :id
    ")->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    /* Sincronizar JSON */
    ProjectInstaller::syncFromDatabase($pdo, $id);

    /* Log */
    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (:project_id, 'status_changed', :message, 'info')
    ")->execute([
        'project_id' => $id,
        'message'    => "Status alterado para '{$newStatus}'"
    ]);

    $pdo->commit();

} catch (Throwable $e) {

    $pdo->rollBack();
    die('Erro ao alterar status: ' . $e->getMessage());
}

header('Location: /public/admin/projects/view.php?id='.$id);
exit;