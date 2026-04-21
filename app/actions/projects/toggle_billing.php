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
    header('Location: ../projects.php');
    exit;
}

/* =========================
   BUSCAR PROJETO + PLANO
========================= */

$stmt = $pdo->prepare("
SELECT p.billing_status, p.plan_id, pl.billing_cycle
FROM projects p
JOIN plans pl ON pl.id = p.plan_id
WHERE p.id = :id
");

$stmt->execute(['id'=>$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header('Location: ../projects.php');
    exit;
}

$current = $data['billing_status'];
$cycle   = $data['billing_cycle'];

/* =========================
   DEFINIR NOVO STATUS
========================= */

$newBilling = $current === 'active' ? 'suspended' : 'active';

/* =========================
   EXPIRAÇÃO AUTOMÁTICA
========================= */

$expiresAt = null;

if ($newBilling === 'active') {

    if ($cycle === 'monthly') {
        $expiresAt = date('Y-m-d', strtotime('+30 days'));
    }

    if ($cycle === 'annual') {
        $expiresAt = date('Y-m-d', strtotime('+365 days'));
    }
}

/* =========================
   UPDATE
========================= */

try {

$pdo->beginTransaction();

$pdo->prepare("
UPDATE projects
SET billing_status=:billing,
expires_at=:expires
WHERE id=:id
")->execute([
'billing'=>$newBilling,
'expires'=>$expiresAt,
'id'=>$id
]);

ProjectInstaller::syncFromDatabase($pdo,$id);

$pdo->prepare("
INSERT INTO project_logs (project_id,action,message,level)
VALUES (:p,'billing_changed',:m,'info')
")->execute([
'p'=>$id,
'm'=>"Billing alterado para {$newBilling}"
]);

$pdo->commit();

}catch(Throwable $e){

$pdo->rollBack();
die($e->getMessage());

}

header('Location: /public/admin/projects/view.php?id='.$id);
exit;