<?php
declare(strict_types=1);

function mailPilotLoadCode(): void
{
    if(!is_file(ROOT_PATH.'/vendor/autoload.php'))throw new DomainException('As dependências do piloto ainda não estão disponíveis.');
    require_once ROOT_PATH.'/vendor/autoload.php';
    foreach(['MailProviderException','MailTokenCipher','MailZohoConfig','MailOutlookConfig','MailPilotConfig','MailHttpClient','MailProviderInterface','MailMutableProviderInterface','providers/GmailMailProvider','providers/ZohoMailProvider','providers/OutlookMailProvider','MailProviderRegistry','MailMessageService','MailLocalOrganizationService','MailOAuthService','MailRemoteMessageService','MailSyncService','MailSyncAllService','MailHtmlRenderer','MailAttachmentService','MailSendService']as$file)require_once APP_PATH.'/services/mail/'.$file.'.php';
}
function mailPilotReady(PDO $pdo,array $config,string $provider='gmail'): bool
{
    try{
        mailPilotLoadCode();if(($config['mail_center']['oauth_log_redaction_verified']??false)!==true)return false;
        if(!$pdo->query("SELECT 1 FROM core_mail_schema_versions WHERE version='002_pilot'")->fetchColumn()||!mailCenterOrganizationReady($pdo))return false;
        $private=MailPilotConfig::load($config,ROOT_PATH);MailPilotConfig::forProvider($private,$config,$provider);return true;
    }catch(Throwable $e){return false;}
}
function mailPilotRuntime(PDO $pdo,array $config,int $owner,int $account=0): array
{
    mailPilotLoadCode();
    $private=MailPilotConfig::load($config,ROOT_PATH);$cipher=new MailTokenCipher($private['keys'],$private['active_key']);
    $store=new MailMessageService($pdo,$owner,$cipher,$account?:null);$box=$account?$store->account($account,false):null;$provider=$box['provider_code']??'gmail';
    if(!mailPilotReady($pdo,$config,$provider))throw new DomainException('A integração aguarda configuração privada, HTTPS e revisão dos logs.');
    if(PHP_SAPI!=='cli')MailPilotConfig::requireHttps($config,$_SERVER);
    $settings=MailPilotConfig::forProvider($private,$config,$provider);
    $settings['remote_organization_enabled']=($config['mail_center']['remote_organization_enabled']??false)===true;
    $settings['gmail_permanent_delete_enabled']=($config['mail_center']['gmail_permanent_delete_enabled']??false)===true;
    if($box&&in_array($provider,['zoho','outlook'],true)&&strtolower($box['email'])!==$settings['pilot_email'])throw new DomainException('Esta caixa ainda não foi autorizada na configuração privada.');
    $lookup=static function(string $remote)use($pdo,$owner,$account):?string{$s=$pdo->prepare('SELECT f.remote_id FROM core_mail_message_folders mf JOIN core_mail_messages m ON m.id=mf.message_id AND m.account_id=mf.account_id AND m.owner_user_id=mf.owner_user_id JOIN core_mail_folders f ON f.id=mf.folder_id AND f.account_id=mf.account_id AND f.owner_user_id=mf.owner_user_id WHERE m.account_id=? AND m.owner_user_id=? AND m.remote_id=? LIMIT 1');$s->execute([$account,$owner,$remote]);$value=$s->fetchColumn();return $value===false?null:(string)$value;};
    $http=new MailHttpClient();$api=MailProviderRegistry::forCode($provider,$http,$settings,$lookup);
    $oauth=new MailOAuthService($store,$api,$settings,$http);$attachments=new MailAttachmentService($store,$oauth,$api,(string)($private['attachments_dir']??''),ROOT_PATH);
    return ['store'=>$store,'oauth'=>$oauth,'api'=>$api,'attachments'=>$attachments,'send'=>new MailSendService($store,$oauth,$api,$attachments),'sync'=>new MailSyncService($store,$oauth,$api),'organization'=>new MailRemoteMessageService($store,$oauth,$api,$settings['remote_organization_enabled'])];
}
function mailPilotError(Throwable $error,string $location='/web/admin/central_contas/accounts.php'): never
{
    if($error instanceof OutOfBoundsException){http_response_code(404);exit('Registro indisponível.');}
    $message=$error instanceof DomainException||$error instanceof InvalidArgumentException||$error instanceof MailProviderException?$error->getMessage():'Não foi possível concluir a operação. Nenhum detalhe sensível foi registrado.';
    flash('error',$message);header('Location: '.$location,true,303);exit;
}
function mailPilotStatus(string $state): string
{
    return match($state){'queued'=>'Na fila','sending'=>'Envio em andamento','accepted'=>'Aceito pelo provedor','confirmed'=>'Registrado nos enviados','failed'=>'Falha no envio','unknown'=>'Resultado incerto — confira antes de reenviar',default=>'Indisponível'};
}
