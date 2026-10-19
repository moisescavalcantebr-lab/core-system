<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
try{
    $resource=($_POST['operation']??'')==='reconcile'?'outbox':'draft';$resourceId=mailCenterId($_POST[$resource==='outbox'?'outbox_id':'draft_id']??null);$runtime=mailPilotRuntime($pdo,$config,$owner,mailCenterResourceAccount($pdo,$owner,$resource,$resourceId));
    if(($_POST['operation']??'')==='reconcile')$row=$runtime['send']->reconcile(mailCenterId($_POST['outbox_id']??null));
    else{
        if(!is_string($_POST['submission_key']??null))throw new InvalidArgumentException('Identificador de envio inválido.');
        $row=$runtime['send']->submit(mailCenterId($_POST['draft_id']??null),mailCenterId($_POST['revision']??null),$_POST['submission_key'],($_POST['confirmed']??'')==='1');
    }
    flash($row['status']==='unknown'||$row['status']==='failed'?'warning':'success',mailPilotStatus($row['status']));header('Location: /web/admin/central_contas/compose.php?outbox='.(int)$row['id'],true,303);exit;
}catch(Throwable $error){mailPilotError($error);}
