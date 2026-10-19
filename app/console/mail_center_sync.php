<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);define('ROOT_PATH',$root);define('APP_PATH',$root.'/app');
require APP_PATH.'/helpers/mail_center.php';require APP_PATH.'/helpers/mail_pilot.php';require APP_PATH.'/services/mail/MailAccountService.php';
try{
    if(!in_array('--allow-provider-access',$argv,true))throw new DomainException('Use --allow-provider-access somente após aprovar o piloto real.');
    $config=require $root.'/env/env.production.php';$d=$config['db'];$pdo=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $owner=(int)($config['mail_center']['owner_user_id']??0);if(mailCenterOwnerId($pdo,$config,['core_user'=>['id'=>$owner]])===null)throw new DomainException('Proprietário indisponível.');
    $options=getopt('',['account:','allow-provider-access']);$account=mailCenterId($options['account']??null);$runtime=mailPilotRuntime($pdo,$config,$owner,$account);$result=$runtime['sync']->run($account);echo 'Lote concluído: '.$result['processed'].' registros; ciclo completo='.($result['complete']?'sim':'não').PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Sincronização não concluída. Confira configuração e estado da conta no painel; detalhes omitidos.'.PHP_EOL);exit(1);}
