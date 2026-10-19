<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($pdo,$database,$account,$zohoAccount,$other,$cipher,$checks))exit(1);
$allSyncStart=$checks;
$calls=[];$runAll=new MailSyncAllService($pdo,1,static function(int $id)use(&$calls,$account):array{$calls[]=$id;return ['processed'=>$id===$account?2:1,'complete'=>$id!==$account];});
$result=$runAll->run();sort($calls);$expected=[$account,$zohoAccount];sort($expected);
pilotExpect($calls===$expected&&$result===['total'=>2,'updated'=>2,'pending'=>1,'failed'=>0,'processed'=>3],'Sincronizar todos não atualizou um lote de cada caixa conectada');
$calls=[];$partial=new MailSyncAllService($pdo,1,static function(int $id)use(&$calls,$account):array{$calls[]=$id;if($id===$account)throw new MailProviderException('quota');return ['processed'=>1,'complete'=>true];});$result=$partial->run();
pilotExpect(count($calls)===2&&$result['failed']===1&&$result['updated']===1,'Falha na primeira caixa impediu atualizar a segunda');
$pdo->prepare('UPDATE core_mail_accounts SET is_active=0 WHERE id=?')->execute([$account]);
try{$calls=[];$result=$runAll->run();pilotExpect($calls===[$zohoAccount]&&$result['total']===1,'Caixa desativada incluída na sincronização');}finally{$pdo->prepare('UPDATE core_mail_accounts SET is_active=1 WHERE id=?')->execute([$account]);}
$pdo->prepare("UPDATE core_mail_connections SET status='reauth_required' WHERE account_id=?")->execute([$zohoAccount]);
try{$calls=[];$result=$runAll->run();pilotExpect($calls===[$account]&&$result['total']===1,'Conexão que exige reautorização foi usada');}finally{$pdo->prepare("UPDATE core_mail_connections SET status='connected' WHERE account_id=?")->execute([$zohoAccount]);}
$pdo->prepare("INSERT INTO core_mail_connections(owner_user_id,account_id,remote_identity,scopes,token_envelope,expires_at,status) VALUES(2,?,'other@gmail.com','[]',?,UTC_TIMESTAMP()+INTERVAL 1 HOUR,'connected')")->execute([$other,$cipher->seal(['access_token'=>'synthetic-other','refresh_token'=>'synthetic-other-refresh'],'mail:2:'.$other.':tokens')]);
$pdo->prepare("UPDATE core_mail_accounts SET connection_status='connected' WHERE id=? AND owner_user_id=2")->execute([$other]);
try{$calls=[];$runAll->run();sort($calls);pilotExpect($calls===$expected,'Sincronização de todas as caixas atravessou proprietário');}finally{$pdo->prepare('DELETE FROM core_mail_connections WHERE account_id=? AND owner_user_id=2')->execute([$other]);$pdo->prepare("UPDATE core_mail_accounts SET connection_status='not_connected' WHERE id=? AND owner_user_id=2")->execute([$other]);}
$pdo->prepare('UPDATE core_mail_accounts SET is_active=0 WHERE id IN (?,?)')->execute([$account,$zohoAccount]);
try{$calls=[];$result=$runAll->run();pilotExpect($calls===[]&&$result['total']===0&&$result['failed']===0,'Sem caixas conectadas gerou acesso ao provedor');}finally{$pdo->prepare('UPDATE core_mail_accounts SET is_active=1 WHERE id IN (?,?)')->execute([$account,$zohoAccount]);}
$lockPdo=new PDO('mysql:host='.$d['host'].';dbname='.$database.';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$lockPdo->query("SELECT GET_LOCK('mail_sync_all:1',0)");
try{$calls=[];pilotReject(fn()=>$runAll->run(),'Sincronização duplicada ignorou bloqueio do proprietário');pilotExpect($calls===[],'Sincronização concorrente iniciou acesso às contas');}finally{$lockPdo->query("SELECT RELEASE_LOCK('mail_sync_all:1')");}
pilotReject(fn()=>(new MailSyncAllService($pdo,999,static fn()=>[]))->run(),'Proprietário indisponível pôde sincronizar');
echo 'Sincronizar todos: '.($checks-$allSyncStart).' verificações sintéticas adicionais aprovadas.'.PHP_EOL;
