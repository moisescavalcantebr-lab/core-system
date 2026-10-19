<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
ini_set('display_errors','0');
try{
    $statusOnly=in_array('--status-only',$argv,true);
    if(!$statusOnly&&!in_array('--allow-provider-access',$argv,true))exit(1);
    define('ROOT_PATH','/var/www/html');define('APP_PATH',ROOT_PATH.'/app');
    $config=require ROOT_PATH.'/env/env.production.php';$d=$config['db'];
    require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/services/mail/MailAccountService.php';
    mailPilotLoadCode();
    $pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $owner=(int)$config['mail_center']['owner_user_id'];
    if(mailCenterOwnerId($pdo,$config,['core_user'=>['id'=>$owner]])!==$owner)throw new RuntimeException();
    $s=$pdo->prepare("SELECT a.id,p.code,a.email,a.connection_status,c.status,c.token_envelope IS NOT NULL AS has_token FROM core_mail_accounts a JOIN core_mail_providers p ON p.id=a.provider_id LEFT JOIN core_mail_connections c ON c.account_id=a.id AND c.owner_user_id=a.owner_user_id WHERE a.owner_user_id=? AND a.is_active=1 AND p.code IN ('gmail','zoho') ORDER BY a.display_order,a.id");$s->execute([$owner]);$accounts=$s->fetchAll(PDO::FETCH_ASSOC);
    if(count($accounts)!==2)throw new RuntimeException();
    $expected=['gmail'=>'moises.cavalcante.br@gmail.com','zoho'=>'moises.cavalcante@meuprojetoweb.com'];
    foreach($accounts as$a)if(($expected[$a['code']]??null)!==$a['email']||$a['connection_status']!=='connected'||$a['status']!=='connected'||!$a['has_token'])throw new RuntimeException();
    echo 'gmail_zoho_connections_verified=yes'.PHP_EOL;
    if($statusOnly){
        foreach(['mbstring','curl','pdo_mysql']as$extension)echo 'extension_'.$extension.'='.(extension_loaded($extension)?'yes':'no').PHP_EOL;
        mailPilotLoadCode();
        try{MailMessageService::decodeHeader('=?UTF-8?B?VGVzdGU=?=');echo 'synthetic_mime_decode=PASS'.PHP_EOL;}catch(Throwable $e){echo 'synthetic_mime_decode=FAIL'.PHP_EOL;}
        foreach($accounts as$a){$s=$pdo->prepare('SELECT failure_code,(retry_after>UTC_TIMESTAMP()) AS cooldown,(lease_until>UTC_TIMESTAMP()) AS leased FROM core_mail_sync_states WHERE owner_user_id=? AND account_id=?');$s->execute([$owner,$a['id']]);$state=$s->fetch(PDO::FETCH_ASSOC)?:[];$code=$state['failure_code']??'none';echo $a['code'].'_last_failure='.(preg_match('/^[a-z_]{1,30}$/D',$code)?$code:'other').PHP_EOL;echo $a['code'].'_cooldown='.(empty($state['cooldown'])?'no':'yes').PHP_EOL;echo $a['code'].'_lease_active='.(empty($state['leased'])?'no':'yes').PHP_EOL;}
        echo 'provider_calls=no'.PHP_EOL;exit;
    }
    $failed=0;
    foreach($accounts as$a){
        $account=(int)$a['id'];$provider=$a['code'];
        try{
            $runtime=mailPilotRuntime($pdo,$config,$owner,$account);
            $batch=$runtime['sync']->run($account);
            echo $provider.'_sync=PASS'.PHP_EOL;
            echo $provider.'_processed='.(int)$batch['processed'].PHP_EOL;
            echo $provider.'_cycle_complete='.($batch['complete']?'yes':'no').PHP_EOL;
        }catch(Throwable $e){$failed++;echo $provider.'_sync=not_completed'.PHP_EOL;echo $provider.'_error_type='.get_class($e).PHP_EOL;echo $provider.'_error_file='.basename($e->getFile()).':'.$e->getLine().PHP_EOL;if($e instanceof PDOException){$code=(string)$e->getCode();if(preg_match('/^[a-zA-Z0-9]{5}$/D',$code))echo $provider.'_sqlstate='.$code.PHP_EOL;echo $provider.'_driver_error='.(int)($e->errorInfo[1]??0).PHP_EOL;}}
        $s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_messages WHERE owner_user_id=? AND account_id=?');$s->execute([$owner,$account]);echo $provider.'_cached_metadata_count='.(int)$s->fetchColumn().PHP_EOL;
        $s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_folders WHERE owner_user_id=? AND account_id=?');$s->execute([$owner,$account]);echo $provider.'_folders_count='.(int)$s->fetchColumn().PHP_EOL;
    }
    echo 'message_content_printed=no'.PHP_EOL;
    echo 'emails_sent=no'.PHP_EOL;
    echo 'automatic_sync_scheduled=no'.PHP_EOL;
    if($failed)exit(2);
}catch(Throwable $e){fwrite(STDERR,"Validação não concluída: confira as duas conexões e o proprietário no painel; detalhes privados omitidos.\n");exit(1);}
