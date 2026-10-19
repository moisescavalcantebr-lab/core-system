<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
    $runtime=mailPilotRuntime($pdo,$config,$owner,mailCenterId($_SESSION['mail_oauth']['account']??null));$private=MailPilotConfig::load($config,ROOT_PATH);
    $pending=MailOAuthService::consume($_SESSION,$_GET,$owner,$private['google']['redirect_uri']);
    $runtime['oauth']->complete($pending,$_GET['code']);flash('success','Gmail conectado à conta correta. Você já pode iniciar uma atualização manual.');header('Location: /web/admin/central_contas/accounts.php',true,303);exit;
}catch(Throwable $error){mailPilotError($error);}
