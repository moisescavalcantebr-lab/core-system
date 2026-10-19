<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
try{mailPilotLoadCode();if(!mailCenterOrganizationReady($pdo))throw new DomainException('Organização local aguardando preparação.');(new MailLocalOrganizationService($pdo,$owner))->savePreferences($_POST['highlight_color']??null,$_POST['reading_mode']??null);flash('success','Preferências salvas.');header('Location: /web/admin/central_contas/accounts.php#mail-local-preferences',true,303);exit;}catch(Throwable $e){mailPilotError($e);}

