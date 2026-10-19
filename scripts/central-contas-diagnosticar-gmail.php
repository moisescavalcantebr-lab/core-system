<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--probe-read',$argv,true))exit(1);
ini_set('display_errors','0');
try{
    define('ROOT_PATH','/var/www/html');define('APP_PATH',ROOT_PATH.'/app');
    $config=require ROOT_PATH.'/env/env.production.php';$d=$config['db'];
    require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/services/mail/MailAccountService.php';mailPilotLoadCode();
    $pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $owner=(int)$config['mail_center']['owner_user_id'];if(mailCenterOwnerId($pdo,$config,['core_user'=>['id'=>$owner]])!==$owner)throw new RuntimeException();
    $s=$pdo->prepare("SELECT a.id FROM core_mail_accounts a JOIN core_mail_providers p ON p.id=a.provider_id WHERE a.owner_user_id=? AND p.code='gmail' AND a.email=? AND a.is_active=1");$s->execute([$owner,'moises.cavalcante.br@gmail.com']);$account=(int)$s->fetchColumn();if(!$account)throw new RuntimeException();
    $runtime=mailPilotRuntime($pdo,$config,$owner,$account);$connection=$runtime['store']->connection($account);
    $scopes=json_decode($connection['scopes'],true);echo 'gmail_connected=yes'.PHP_EOL;
    echo 'gmail_read_permission='.(is_array($scopes)&&in_array('https://www.googleapis.com/auth/gmail.readonly',$scopes,true)?'yes':'no').PHP_EOL;
    echo 'gmail_modify_permission='.(is_array($scopes)&&in_array('https://www.googleapis.com/auth/gmail.modify',$scopes,true)?'yes':'no').PHP_EOL;
    $s=$pdo->prepare('SELECT *,lease_until>UTC_TIMESTAMP() AS leased,retry_after>UTC_TIMESTAMP() AS cooldown FROM core_mail_sync_states WHERE owner_user_id=? AND account_id=?');$s->execute([$owner,$account]);$state=$s->fetch(PDO::FETCH_ASSOC)?:[];
    $failure=(string)($state['failure_code']??'none');echo 'gmail_sync_failure='.(preg_match('/^[a-z_]{1,30}$/D',$failure)?$failure:'other').PHP_EOL;
    echo 'gmail_lease_active='.(empty($state['leased'])?'no':'yes').PHP_EOL;echo 'gmail_cooldown='.(empty($state['cooldown'])?'no':'yes').PHP_EOL;
    $cursor=empty($state['cursor_envelope'])?[]:$runtime['store']->cipher->open($state['cursor_envelope'],'mail:'.$owner.':'.$account.':sync');
    $mode=$cursor['mode']??'none';echo 'gmail_sync_mode='.(in_array($mode,['none','bootstrap','history','poll'],true)?$mode:'other').PHP_EOL;
    echo 'gmail_pending_ids='.count($cursor['queue']??[]).PHP_EOL;echo 'gmail_more_pages='.(empty($cursor['page'])&&empty($cursor['following'])?'no':'yes').PHP_EOL;
    echo 'gmail_cursor_loaded='.(empty($cursor['loaded'])?'no':'yes').PHP_EOL;
    $s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_messages WHERE owner_user_id=? AND account_id=? AND remote_missing=0');$s->execute([$owner,$account]);echo 'gmail_cached_count='.(int)$s->fetchColumn().PHP_EOL;
    // Read-only provider probe: IDs stay in memory, never in the report.
    $token=$runtime['oauth']->token($account);$profile=$runtime['api']->profile($token);echo 'gmail_api_profile=PASS'.PHP_EOL;
    $latest=$runtime['api']->listMessages($token,'after:'.(time()-30*86400));$missing=0;$count=0;
    foreach($latest['messages']??[]as$row){$s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_messages WHERE owner_user_id=? AND account_id=? AND remote_id=? AND remote_missing=0');$s->execute([$owner,$account,(string)$row['id']]);$count++;if(!(int)$s->fetchColumn())$missing++;}
    echo 'gmail_recent_probe_count='.$count.PHP_EOL;echo 'gmail_recent_not_cached='.$missing.PHP_EOL;
    if($mode==='history'&&!empty($cursor['history'])){
        $history=$runtime['api']->history($token,(string)$cursor['history']);echo 'gmail_history_api=PASS'.PHP_EOL;echo 'gmail_history_events='.count($history['history']??[]).PHP_EOL;echo 'gmail_history_more_pages='.(empty($history['nextPageToken'])?'no':'yes').PHP_EOL;
    }
    echo 'mail_messages_changed=no'.PHP_EOL;echo 'metadata_imported=no'.PHP_EOL;echo 'message_content_printed=no'.PHP_EOL;echo 'emails_sent=no'.PHP_EOL;
}catch(Throwable $e){
    $kind=$e instanceof MailProviderException?$e->kind:'local';echo 'gmail_diagnostic_error='.(preg_match('/^[a-z_]{1,30}$/D',$kind)?$kind:'other').PHP_EOL;
    if($e instanceof PDOException){$code=(string)$e->getCode();if(preg_match('/^[a-zA-Z0-9]{5}$/D',$code))echo 'gmail_diagnostic_sqlstate='.$code.PHP_EOL;}
    exit(1);
}
