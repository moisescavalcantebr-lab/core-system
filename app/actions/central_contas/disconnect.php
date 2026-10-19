<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
try{if(($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Confirme a desconexão.');$account=mailCenterId($_POST['account_id']??null);$runtime=mailPilotRuntime($pdo,$config,$owner,$account);$ok=$runtime['oauth']->disconnect(mailCenterId($_POST['account_id']??null));flash('success',$ok?'Conta desconectada; revogação confirmada pelo provedor.':'Acesso local removido. Confira e revogue também o consentimento na sua conta do provedor.');header('Location: /web/admin/central_contas/accounts.php',true,303);exit;}catch(Throwable $error){mailPilotError($error);}
