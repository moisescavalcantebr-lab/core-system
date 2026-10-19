<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';
require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);exit;}
    $account=mailCenterId($_SESSION['mail_oauth']['account']??null);
    mailPilotLoadCode();
    $private=MailPilotConfig::load($config,ROOT_PATH);
    $settings=MailPilotConfig::forProvider($private,$config,'zoho');
    $pending=MailOAuthService::consume($_SESSION,$_GET,$owner,$settings['redirect_uri'],'zoho');
    // Callback parameters cannot select the server receiving our client secret.
    if((isset($_GET['location'])&&$_GET['location']!==$settings['region']) || (isset($_GET['accounts-server'])&&$_GET['accounts-server']!==$settings['endpoints']['accounts']))throw new DomainException('Região Zoho diferente da configuração. Inicie novamente.');
    $runtime=mailPilotRuntime($pdo,$config,$owner,$account);
    $runtime['oauth']->complete($pending,$_GET['code']);
    flash('success','Zoho conectado à conta correta. Você já pode iniciar uma atualização manual.');
    header('Location: /web/admin/central_contas/accounts.php',true,303);exit;
}catch(Throwable $error){mailPilotError($error);}
