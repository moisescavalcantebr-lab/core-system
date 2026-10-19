<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';$owner=mailCenterRequireOwner($pdo,$config);
try{$resourceAccount=mailCenterResourceAccount($pdo,$owner,'attachment',mailCenterId($_GET['id']??null));$runtime=mailPilotRuntime($pdo,$config,$owner,$resourceAccount);$file=$runtime['attachments']->download(mailCenterId($_GET['id']??null));header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="anexo"; filename*=UTF-8\'\''.rawurlencode($file['filename']));header('Content-Length: '.strlen($file['bytes']));echo $file['bytes'];exit;}catch(Throwable $error){mailPilotError($error,'/web/admin/central_contas/messages.php');}
