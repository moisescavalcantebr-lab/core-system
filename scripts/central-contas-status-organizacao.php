<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!array_intersect(['--status-only','--sync-limited'],$argv))exit(1);
ini_set('display_errors','0');
try{
    define('ROOT_PATH','/var/www/html');define('APP_PATH',ROOT_PATH.'/app');
    $config=require ROOT_PATH.'/env/env.production.php';$d=$config['db'];
    require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/services/mail/MailAccountService.php';mailPilotLoadCode();
    $pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $owner=(int)$config['mail_center']['owner_user_id'];
    if(mailCenterOwnerId($pdo,$config,['core_user'=>['id'=>$owner]])!==$owner)throw new RuntimeException();
    echo 'organization_enabled='.(($config['mail_center']['remote_organization_enabled']??false)?'yes':'no').PHP_EOL;
    $s=$pdo->prepare("SELECT a.id,p.code FROM core_mail_accounts a JOIN core_mail_providers p ON p.id=a.provider_id JOIN core_mail_connections c ON c.account_id=a.id AND c.owner_user_id=a.owner_user_id WHERE a.owner_user_id=? AND a.is_active=1 AND c.status='connected' AND c.token_envelope IS NOT NULL AND p.code IN ('gmail','zoho') ORDER BY p.code");$s->execute([$owner]);$boxes=$s->fetchAll(PDO::FETCH_ASSOC);
    $failed=0;
    foreach($boxes as$box){
        $account=(int)$box['id'];$provider=$box['code'];$runtime=mailPilotRuntime($pdo,$config,$owner,$account);
        echo $provider.'_organization_ready='.(isset($runtime['organization'])&&$runtime['organization']->available($account)?'yes':'no').PHP_EOL;
        if(in_array('--sync-limited',$argv,true)){
            for($round=1;$round<=5;$round++){
                try{$batch=$runtime['sync']->run($account);echo $provider.'_batch_'.$round.'_processed='.(int)$batch['processed'].PHP_EOL;echo $provider.'_batch_'.$round.'_complete='.($batch['complete']?'yes':'no').PHP_EOL;}
                catch(Throwable $e){$failed++;$kind=$e instanceof MailProviderException?$e->kind:'local';echo $provider.'_sync_error='.(preg_match('/^[a-z_]{1,30}$/D',$kind)?$kind:'other').PHP_EOL;break;}
                if($batch['complete'])break;
                if($round<5)sleep(6);
            }
        }
        $s=$pdo->prepare('SELECT COUNT(*) AS total,SUM(remote_missing=0) AS available,SUM(remote_missing=0 AND received_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY) AS recent FROM core_mail_messages WHERE owner_user_id=? AND account_id=?');$s->execute([$owner,$account]);$count=$s->fetch(PDO::FETCH_ASSOC);
        echo $provider.'_cached_total='.(int)$count['total'].PHP_EOL;echo $provider.'_cached_available='.(int)$count['available'].PHP_EOL;echo $provider.'_cached_recent='.(int)$count['recent'].PHP_EOL;
        $s=$pdo->prepare('SELECT *,lease_until>UTC_TIMESTAMP() AS leased,retry_after>UTC_TIMESTAMP() AS cooldown FROM core_mail_sync_states WHERE owner_user_id=? AND account_id=?');$s->execute([$owner,$account]);$state=$s->fetch(PDO::FETCH_ASSOC)?:[];
        $cursor=empty($state['cursor_envelope'])?[]:$runtime['store']->cipher->open($state['cursor_envelope'],'mail:'.$owner.':'.$account.':sync');
        $mode=$cursor['mode']??'none';echo $provider.'_mode='.(in_array($mode,['none','poll','bootstrap','history'],true)?$mode:'other').PHP_EOL;
        echo $provider.'_queued='.count($cursor['queue']??[]).PHP_EOL;echo $provider.'_more_pages='.(empty($cursor['page'])&&empty($cursor['following'])?'no':'yes').PHP_EOL;
        echo $provider.'_cooldown='.(empty($state['cooldown'])?'no':'yes').PHP_EOL;echo $provider.'_lease_active='.(empty($state['leased'])?'no':'yes').PHP_EOL;
        $code=$state['failure_code']??'none';echo $provider.'_failure='.(preg_match('/^[a-z_]{1,30}$/D',$code)?$code:'other').PHP_EOL;
    }
    echo 'provider_message_writes=no'.PHP_EOL;echo 'emails_sent=no'.PHP_EOL;echo 'message_content_printed=no'.PHP_EOL;
    exit($failed?2:0);
}catch(Throwable $e){fwrite(STDERR,"Conferência não concluída; detalhes privados omitidos.\n");exit(1);}
