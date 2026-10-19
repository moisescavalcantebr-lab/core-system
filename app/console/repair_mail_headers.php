<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('ROOT_PATH',dirname(__DIR__,2));define('APP_PATH',ROOT_PATH.'/app');
require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';
require APP_PATH.'/services/mail/MailAccountService.php';
try{
    $config=require ROOT_PATH.'/env/env.production.php';$db=$config['db'];
    if(($config['mail_center']['enabled']??false)!==true)throw new DomainException('disabled');
    $pdo=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $owner=(int)($config['mail_center']['owner_user_id']??0);mailPilotLoadCode();new MailAccountService($pdo,$owner);
    $options=getopt('',['apply','limit:','after:']);$limit=filter_var($options['limit']??25,FILTER_VALIDATE_INT);$after=filter_var($options['after']??0,FILTER_VALIDATE_INT);
    if($limit===false||$limit<1||$limit>100||$after===false||$after<0)throw new InvalidArgumentException('limits');
    $s=$pdo->prepare("SELECT m.id,m.account_id FROM core_mail_messages m JOIN core_mail_accounts a ON a.id=m.account_id AND a.owner_user_id=m.owner_user_id JOIN core_mail_connections c ON c.account_id=a.id AND c.owner_user_id=a.owner_user_id WHERE m.owner_user_id=? AND m.id>? AND m.remote_missing=0 AND a.is_active=1 AND a.connection_status='connected' AND c.status='connected' AND (LOCATE('?',m.subject)>0 OR LOCATE('?',m.sender)>0 OR LOCATE('?',m.recipients)>0) ORDER BY m.id LIMIT ".$limit);$s->execute([$owner,$after]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
    if(!isset($options['apply'])){echo 'Prévia: '.count($rows).' metadados candidatos neste lote. Nenhum provedor acessado ou dado alterado.'.PHP_EOL;exit;}
    $runtimes=[];$blocked=[];$processed=0;$failed=0;$deadline=microtime(true)+45;$next=$after;
    foreach($rows as$row){if(microtime(true)>$deadline)break;$account=(int)$row['account_id'];$next=(int)$row['id'];
        if(isset($blocked[$account])){$failed++;continue;}
        try{
            $runtime=$runtimes[$account]??=mailPilotRuntime($pdo,$config,$owner,$account);$message=$runtime['store']->message((int)$row['id']);
            $remote=$runtime['api']->message($runtime['oauth']->token($account),$message['remote_id'],false);
            $runtime['store']->refreshHeaders((int)$row['id'],$remote);$processed++;
        }catch(Throwable $e){$failed++;if($e instanceof MailProviderException&&in_array($e->kind,['auth','quota'],true))$blocked[$account]=true;}
    }
    echo 'Metadados atualizados: '.$processed.'; indisponíveis: '.$failed.'; próximo cursor local: '.$next.'. Apenas cache local; sem envio ou alterações remotas.'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Revisão de cabeçalhos interrompida; detalhes privados omitidos.'.PHP_EOL);exit(1);}
