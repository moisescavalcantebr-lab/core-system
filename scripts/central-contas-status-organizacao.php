<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!array_intersect(['--status-only','--sync-limited','--probe-gmail'],$argv))exit(1);
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
        if($provider==='gmail'&&in_array('--probe-gmail',$argv,true)){
            $token=$runtime['oauth']->token($account);$profile=$runtime['api']->profile($token);
            echo 'gmail_profile_matches='.(strtolower((string)($profile['emailAddress']??''))===strtolower($runtime['store']->account($account)['email'])?'yes':'no').PHP_EOL;
            foreach([false,true]as$includeAll){
                $page='';$total=0;$missing=0;
                for($probe=0;$probe<5;$probe++){
                    $query=['maxResults'=>10,'q'=>'after:'.(time()-30*86400),'includeSpamTrash'=>$includeAll?'true':'false'];if($page!=='')$query['pageToken']=$page;
                    $response=(new MailHttpClient())->request('GET','https://gmail.googleapis.com/gmail/v1/users/me/messages?'.http_build_query($query,'','&',PHP_QUERY_RFC3986),['Authorization: Bearer '.$token]);
                    foreach($response['messages']??[]as$row){$total++;$s=$pdo->prepare('SELECT COUNT(*) FROM core_mail_messages WHERE owner_user_id=? AND account_id=? AND remote_id=? AND remote_missing=0');$s->execute([$owner,$account,(string)$row['id']]);if(!(int)$s->fetchColumn())$missing++;}
                    $page=(string)($response['nextPageToken']??'');if($page==='')break;
                }
                $prefix=$includeAll?'gmail_api_all_30d':'gmail_api_regular_30d';echo $prefix.'_count='.$total.PHP_EOL;echo $prefix.'_missing='.$missing.PHP_EOL;echo $prefix.'_more='.($page===''?'no':'yes').PHP_EOL;
            }
            $s=$pdo->prepare("SELECT m.remote_id FROM core_mail_messages m JOIN core_mail_message_folders mf ON mf.message_id=m.id AND mf.account_id=m.account_id AND mf.owner_user_id=m.owner_user_id JOIN core_mail_folders f ON f.id=mf.folder_id AND f.account_id=mf.account_id AND f.owner_user_id=mf.owner_user_id WHERE m.owner_user_id=? AND m.account_id=? AND m.remote_missing=0 AND f.remote_id='INBOX' ORDER BY m.received_at DESC LIMIT 10");$s->execute([$owner,$account]);$sample=$s->fetchAll(PDO::FETCH_COLUMN);$kinds=['inbox'=>0,'trash'=>0,'spam'=>0,'other'=>0,'missing'=>0];
            foreach($sample as$id){try{$metadata=$runtime['api']->message($token,(string)$id);$labels=$metadata['labelIds']??[];$kind=in_array('TRASH',$labels,true)?'trash':(in_array('SPAM',$labels,true)?'spam':(in_array('INBOX',$labels,true)?'inbox':'other'));$kinds[$kind]++;}catch(MailProviderException $e){if($e->kind!=='missing')throw $e;$kinds['missing']++;}}
            foreach($kinds as$kind=>$number)echo 'gmail_cached_inbox_sample_remote_'.$kind.'='.$number.PHP_EOL;
        }
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
        if($provider==='gmail')foreach(['INBOX','SENT','SPAM','TRASH']as$label){$s=$pdo->prepare('SELECT COUNT(DISTINCT m.id) FROM core_mail_messages m JOIN core_mail_message_folders mf ON mf.message_id=m.id AND mf.account_id=m.account_id AND mf.owner_user_id=m.owner_user_id JOIN core_mail_folders f ON f.id=mf.folder_id AND f.account_id=mf.account_id AND f.owner_user_id=mf.owner_user_id WHERE m.owner_user_id=? AND m.account_id=? AND m.remote_missing=0 AND f.remote_id=?');$s->execute([$owner,$account,$label]);echo 'gmail_cached_'.strtolower($label).'='.(int)$s->fetchColumn().PHP_EOL;}
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
