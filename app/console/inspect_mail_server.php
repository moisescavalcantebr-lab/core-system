<?php
declare(strict_types=1);
// Standalone read-only probe: may be piped to PHP over SSH; never loads bootstrap.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
function mailProbe(string $name,string|int|bool $value):void{echo $name.'='.(is_bool($value)?($value?'yes':'no'):$value).PHP_EOL;}
try{
    $root='/var/www/html';mailProbe('php',PHP_VERSION);mailProbe('php_supported',PHP_VERSION_ID>=80300&&PHP_VERSION_ID<90000);
    foreach(['curl','dom','fileinfo','mbstring','sodium','pdo_mysql','openssl','iconv']as$extension)mailProbe('extension_'.$extension,extension_loaded($extension));
    mailProbe('vendor_present',is_file($root.'/vendor/autoload.php'));mailProbe('mail_code_present',is_file($root.'/app/services/mail/MailMessageService.php'));
    $redaction=@file_get_contents('/etc/apache2/conf-enabled/mail-center.conf');$sites='';foreach(glob('/etc/apache2/sites-enabled/*.conf')?:[]as$site)$sites.=(string)@file_get_contents($site);
    mailProbe('callback_redaction_config_present',is_string($redaction)&&str_contains($redaction,'mail_callback_safe'));
    mailProbe('callback_redaction_log_enabled',str_contains($sites,'env=!mail_oauth_callback')&&str_contains($sites,'mail_callback_safe env=mail_oauth_callback'));
    if(!is_file($root.'/env/env.production.php')){mailProbe('environment_present',false);exit(2);}
    $config=require $root.'/env/env.production.php';mailProbe('environment_present',true);
    $url=parse_url((string)($config['app_url']??''));mailProbe('app_https',($url['scheme']??'')==='https');mailProbe('app_host',(string)($url['host']??'not-configured'));
    $mail=$config['mail_center']??[];mailProbe('mail_enabled',($mail['enabled']??false)===true);mailProbe('mail_owner_configured',(int)($mail['owner_user_id']??0)>0);mailProbe('oauth_redaction_verified',($mail['oauth_log_redaction_verified']??false)===true);
    $private=(string)($mail['secrets_file']??'');$real=$private!==''?realpath($private):false;
    mailProbe('private_secrets_readable',$real!==false&&is_readable($real));mailProbe('private_secrets_outside_webroot',$real!==false&&!str_starts_with($real,$root.'/'));
    $db=$config['db'];$pdo=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);mailProbe('database_readable',true);
    mailProbe('database_version',(string)$pdo->query('SELECT VERSION()')->fetchColumn());mailProbe('connection_charset',(string)$pdo->query('SELECT @@character_set_connection')->fetchColumn());
    foreach(['project'=>'moises.cavalcante@meuprojetoweb.com','gmail'=>'moises.cavalcante.br@gmail.com']as$label=>$email){$s=$pdo->prepare("SELECT COUNT(*) FROM core_users WHERE LOWER(email)=LOWER(?) AND status=1 AND role IN ('ADMIN','SUPER_ADMIN')");$s->execute([$email]);mailProbe('active_owner_candidate_'.$label,(int)$s->fetchColumn());}
    $table=$pdo->query("SHOW TABLES LIKE 'core_mail_schema_versions'")->fetchColumn();mailProbe('mail_schema_present',$table!==false);
    if($table!==false){$versions=$pdo->query('SELECT version FROM core_mail_schema_versions ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);foreach(['001_foundation','002_pilot','003_organization','004_reading']as$version)mailProbe('migration_'.$version,in_array($version,$versions,true));}
    mailProbe('read_only_probe_complete',true);
}catch(Throwable $e){mailProbe('read_only_probe_complete',false);fwrite(STDERR,'Inspeção incompleta; detalhes privados omitidos.'.PHP_EOL);exit(1);}
