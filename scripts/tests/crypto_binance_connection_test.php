<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2);
require $root.'/app/bootstrap/bootstrap.php';
require $root.'/modules/crypto_binance/web/admin/crypto_binance/service.php';
require $root.'/modules/crypto_binance/web/admin/crypto_binance/connection_service.php';
if(!coreIsLocal())throw new RuntimeException('Somente local.');
$f=json_decode((string)file_get_contents($root.'/_deploy/crypto-local-test.json'),true,64,JSON_THROW_ON_ERROR);
if(!preg_match('/^cripto-check-[a-f0-9]{10}$/D',$f['slug'])||$f['owner_email']!=='cripto-check@example.invalid')throw new RuntimeException('Fixture invalida.');
$db=require $root.'/projects/'.$f['slug'].'/app/config/database.php';
$p=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function connAssert(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function connReject(callable $fn,string $label):void{try{$fn();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException($label);}
$store=new CryptoBinanceConnectionStore($p);
connAssert($store->protectionAvailable(),'Chave privada nao disponivel');
$store->disconnect();
$key=str_repeat('k',40);$secret=str_repeat('s',40);
$store->save($key,$secret,1);
$cipher=$p->query('SELECT credential_envelope FROM crypto_binance_connections')->fetchColumn();
connAssert(!str_contains($cipher,$key)&&!str_contains($cipher,$secret),'Credencial em texto claro');
connAssert(!str_contains(json_encode($store->metadata()),'credential_envelope'),'Metadados contem envelope');
connAssert($store->credentials()===['api_key'=>$key,'api_secret'=>$secret],'Decifragem divergente');
$store->save($key,$secret,1);
$cipher2=$p->query('SELECT credential_envelope FROM crypto_binance_connections')->fetchColumn();
connAssert($cipher!==$cipher2&&(int)$store->metadata()['generation']===2,'Nonce repetido ou atualizacao falhou');
connReject(fn()=>$store->save('bad',$secret,1),'Aceitou formato invalido');
connAssert($p->query('SELECT credential_envelope FROM crypto_binance_connections')->fetchColumn()===$cipher2,'Falha substituiu conexao anterior');
$wrong=tempnam(sys_get_temp_dir(),'crypto-wrong-');chmod($wrong,0600);file_put_contents($wrong,base64_encode(random_bytes(32)));
try{
    connReject(fn()=>(new CryptoBinanceConnectionStore($p,$wrong))->credentials(),'Chave errada abriu credenciais');
    connReject(fn()=>(new CryptoBinanceConnectionStore($p,$wrong.'-missing'))->credentials(),'Chave ausente aceita');
    $inside=$root.'/_deploy/crypto-test-key';file_put_contents($inside,base64_encode(random_bytes(32)));
    try{connAssert(!(new CryptoBinanceConnectionStore($p,$inside))->protectionAvailable(),'Aceitou chave no webroot');}finally{unlink($inside);}
    $envelope=json_decode($cipher2,true);$raw=base64_decode($envelope['data']);$raw[0]=chr(ord($raw[0])^1);$envelope['data']=base64_encode($raw);
    $p->prepare('UPDATE crypto_binance_connections SET credential_envelope=?')->execute([json_encode($envelope)]);
    connReject(fn()=>$store->credentials(),'Envelope adulterado aceito');
    $p->prepare('UPDATE crypto_binance_connections SET credential_envelope=?')->execute([$cipher2]);
}finally{unlink($wrong);}
$fake=new CryptoBinanceReader(static function(string $url,array $headers)use($key,$secret):array{
    $parts=parse_url($url);parse_str($parts['query']??'',$params);$path=$parts['path'];
    connAssert($parts['host']==='api.binance.com','Host errado');
    if($headers){connAssert($headers===['X-MBX-APIKEY: '.$key],'Nao usou API Key salva');connAssert(hash_equals(hash_hmac('sha256',explode('&signature=',$parts['query'])[0],$secret),$params['signature']),'Nao usou segredo salvo');}
    $symbol=$params['symbol']??'BTCUSDT';
    $body=match($path){
        '/api/v3/time'=>['serverTime'=>1800000000000],
        '/api/v3/exchangeInfo'=>['symbols'=>[['symbol'=>$symbol,'baseAsset'=>'BTC','quoteAsset'=>'USDT','status'=>'TRADING']]],
        '/api/v3/ticker/price'=>['symbol'=>$symbol,'price'=>'60000.00'],
        '/api/v3/account'=>['balances'=>[['asset'=>'USDT','free'=>'10.00','locked'=>'0.00']]],
        '/api/v3/openOrders','/api/v3/myTrades'=>[],
        default=>throw new RuntimeException('Endpoint nao permitido no teste'),
    };return[200,json_encode($body)];
});
for($i=0;$i<2;$i++){
    $snapshot=$store->read(['BTCUSDT'],$fake);
    connAssert($snapshot['checks']['balances']['state']==='confirmed'&&!str_contains(json_encode($snapshot),$secret),'Consulta salva falhou ou vazou');
}
$coreDb=$config['db'];$server=new PDO('mysql:host='.$coreDb['host'].';charset=utf8mb4',$coreDb['user'],$coreDb['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$cross='crypto_conn_cross_'.bin2hex(random_bytes(6));$server->exec('CREATE DATABASE `'.$cross.'`');
try{
    $other=new PDO('mysql:host='.$coreDb['host'].';dbname='.$cross.';charset=utf8mb4',$coreDb['user'],$coreDb['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $other->exec(file_get_contents($root.'/modules/crypto_binance/database/schema.sql'));
    $other->prepare('INSERT INTO crypto_binance_connections(id,credential_envelope,actor_id) VALUES(1,?,1)')->execute([$cipher2]);
    connReject(fn()=>(new CryptoBinanceConnectionStore($other))->credentials(),'Outro projeto abriu envelope');
}finally{$server->exec('DROP DATABASE `'.$cross.'`');}
$lock=cryptoBinanceConnectionLock($p);$q=$p->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);connAssert((int)$q->fetchColumn()===1,'Nao obteve lock');
try{
    $curl=curl_init('https://localhost/projects/'.$f['slug'].'/web/admin/crypto_binance/connection.php');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['_csrf'=>$f['csrf'],'action'=>'disconnect','confirm_disconnect'=>'1']),CURLOPT_COOKIE=>'PHPSESSID='.$f['session'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_TIMEOUT=>10]);
    curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    connAssert($status===303,'Rota de concorrencia nao respondeu');
    connAssert($store->metadata()!==null,'Desconectou durante consulta em andamento');
}finally{$q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
$history=(int)$p->query('SELECT COUNT(*) FROM crypto_binance_reads')->fetchColumn();$store->disconnect();$store->disconnect();
connAssert($store->metadata()===null&&(int)$p->query('SELECT COUNT(*) FROM crypto_binance_reads')->fetchColumn()===$history,'Desconexao apagou historico');
$p->prepare("INSERT INTO project_users(name,email,username,password,role,status) VALUES('Cliente teste','conn-client@example.invalid','conn-client',?,'CLIENT','active')")->execute([password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$client=(int)$p->lastInsertId();
session_write_close();session_id(bin2hex(random_bytes(24)));session_start();$session=session_id();$_SESSION=['project_user_id'=>$client,'_csrf'=>$f['csrf']];session_write_close();
$sessionFile=(session_save_path()?:sys_get_temp_dir()).'/sess_'.$session;chown($sessionFile,'www-data');chgrp($sessionFile,'www-data');$f['client_session']=$session;
$admin=(int)$p->query("SELECT id FROM project_users WHERE role='ADMIN' AND status='active' LIMIT 1")->fetchColumn();
$p->prepare("INSERT INTO project_users(name,email,username,password,role,status) VALUES('Admin inativo','inactive@example.invalid','conn-inactive',?,'ADMIN','inactive')")->execute([password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$inactive=(int)$p->lastInsertId();
foreach (['cross_session'=>[$admin,'other-project'],'inactive_session'=>[$inactive,hash('sha256',$root.'/projects/'.$f['slug'])]] as $label=>[$userId,$scope]) {
    session_id(bin2hex(random_bytes(24)));session_start();$sid=session_id();$_SESSION=['project_user_id'=>$userId,'_csrf'=>$f['csrf'],'crypto_authenticated_project'=>$scope];session_write_close();
    $file=(session_save_path()?:sys_get_temp_dir()).'/sess_'.$sid;chown($file,'www-data');chgrp($file,'www-data');$f[$label]=$sid;
}
file_put_contents($root.'/_deploy/crypto-local-test.json',json_encode($f));
echo "PASS: criptografia, nonce, substituicao atomica, chave ausente/errada, adulteracao, isolamento por projeto, duas consultas com credenciais salvas, concorrencia e desconexao sem perder historico.\n";
