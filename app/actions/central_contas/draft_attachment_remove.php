<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
try{$resourceAccount=mailCenterResourceAccount($pdo,$owner,'attachment',mailCenterId($_POST['attachment_id']??null));$runtime=mailPilotRuntime($pdo,$config,$owner,$resourceAccount);$draft=$runtime['attachments']->remove(mailCenterId($_POST['attachment_id']??null),mailCenterId($_POST['revision']??null));flash('success','Anexo removido do rascunho.');header('Location: /web/admin/central_contas/compose.php?draft='.$draft,true,303);exit;}catch(Throwable $error){mailPilotError($error);}
