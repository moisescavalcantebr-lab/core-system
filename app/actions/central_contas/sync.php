<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();$account=0;
try{$account=mailCenterId($_POST['account_id']??null);$runtime=mailPilotRuntime($pdo,$config,$owner,$account);$result=$runtime['sync']->run($account);flash('success',$result['complete']?'Atualização concluída.':'Lote atualizado. Continue a atualização para carregar o restante.');header('Location: /web/admin/central_contas/messages.php?account='.$account,true,303);exit;}catch(Throwable $error){mailPilotError($error,'/web/admin/central_contas/messages.php'.($account?'?account='.$account:''));}
