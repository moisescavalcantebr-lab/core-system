<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
    $account=mailCenterId($_SESSION['mail_oauth']['account']??null);mailPilotLoadCode();
    $settings=MailPilotConfig::forProvider(MailPilotConfig::load($config,ROOT_PATH),$config,'outlook');
    $pending=MailOAuthService::consume($_SESSION,$_GET,$owner,$settings['redirect_uri'],'outlook');
    $runtime=mailPilotRuntime($pdo,$config,$owner,$account);$runtime['oauth']->complete($pending,$_GET['code']);
    flash('success','Outlook conectado à conta correta. Você já pode atualizar a caixa.');
    header('Location: /web/admin/central_contas/accounts.php',true,303);exit;
}catch(Throwable $error){mailPilotError($error);}
