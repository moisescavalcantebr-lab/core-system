<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
try{$account=mailCenterId($_POST['account_id']??null);$runtime=mailPilotRuntime($pdo,$config,$owner,$account);$url=$runtime['oauth']->begin($account,$_SESSION);session_write_close();header('Location: '.$url,true,303);exit;}catch(Throwable $error){mailPilotError($error);}
