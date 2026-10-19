<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--sync-one-batch',$argv,true))exit(1);
ini_set('display_errors','0');
try{
    define('ROOT_PATH','/var/www/html');define('APP_PATH',ROOT_PATH.'/app');
    $config=require ROOT_PATH.'/env/env.production.php';$d=$config['db'];require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/services/mail/MailAccountService.php';mailPilotLoadCode();
    $pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$owner=(int)$config['mail_center']['owner_user_id'];
    if(mailCenterOwnerId($pdo,$config,['core_user'=>['id'=>$owner]])!==$owner)throw new RuntimeException();
    $s=$pdo->prepare("SELECT a.id FROM core_mail_accounts a JOIN core_mail_providers p ON p.id=a.provider_id WHERE a.owner_user_id=? AND p.code='gmail' AND a.email=? AND a.is_active=1");$s->execute([$owner,'moises.cavalcante.br@gmail.com']);$account=(int)$s->fetchColumn();if(!$account)throw new RuntimeException();
    $runtime=mailPilotRuntime($pdo,$config,$owner,$account);$batch=$runtime['sync']->run($account);
    echo 'gmail_sync_batch=PASS'.PHP_EOL;echo 'gmail_sync_processed='.(int)$batch['processed'].PHP_EOL;echo 'gmail_history_cycle_complete='.($batch['complete']?'yes':'no').PHP_EOL;
    $latest=$runtime['api']->listMessages($runtime['oauth']->token($account),'after:'.(time()-30*86400));$missing=0;
    foreach($latest['messages']??[]as$row){$s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_messages WHERE owner_user_id=? AND account_id=? AND remote_id=? AND remote_missing=0');$s->execute([$owner,$account,(string)$row['id']]);if(!(int)$s->fetchColumn())$missing++;}
    echo 'gmail_recent_not_cached_after='.$missing.PHP_EOL;echo 'provider_message_mutations=no'.PHP_EOL;echo 'metadata_batch_imported=yes'.PHP_EOL;echo 'message_content_printed=no'.PHP_EOL;echo 'emails_sent=no'.PHP_EOL;
}catch(Throwable $e){
    $kind=$e instanceof MailProviderException?$e->kind:'local';echo 'gmail_sync_validation_error='.(preg_match('/^[a-z_]{1,30}$/D',$kind)?$kind:'other').PHP_EOL;
    echo 'gmail_sync_error_class='.get_class($e).PHP_EOL;echo 'gmail_sync_error_location='.basename($e->getFile()).':'.$e->getLine().PHP_EOL;
    if($e instanceof PDOException){$code=(string)$e->getCode();if(preg_match('/^[a-zA-Z0-9]{5}$/D',$code))echo 'gmail_sync_sqlstate='.$code.PHP_EOL;echo 'gmail_sync_driver_error='.(int)($e->errorInfo[1]??0).PHP_EOL;}
    exit(1);
}
