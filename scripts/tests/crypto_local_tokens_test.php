<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__,2);
require $root.'/app/bootstrap/bootstrap.php';
require $root.'/bases/cripto/app/helpers/crypto_local_tokens.php';
if(!coreIsLocal())throw new RuntimeException('Somente local.');
$f=json_decode((string)file_get_contents($root.'/_deploy/crypto-local-test.json'),true,64,JSON_THROW_ON_ERROR);
if(!preg_match('/^cripto-check-[a-f0-9]{10}$/D',$f['slug'])||$f['owner_email']!=='cripto-check@example.invalid')throw new RuntimeException('Fixture invalida.');
$db=require $root.'/projects/'.$f['slug'].'/app/config/database.php';
$p=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('DELETE FROM project_users');
function tokenAssert(bool $ok,string $label): void{if(!$ok)throw new RuntimeException($label);}
$insert=$pdo->prepare('INSERT INTO project_access_tokens (project_id,email,token,expires_at,used) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND),?)');
$valid=bin2hex(random_bytes(32));$expired=bin2hex(random_bytes(32));$used=bin2hex(random_bytes(32));
foreach([[$valid,3600,0],[$expired,-3600,0],[$used,3600,1]]as[$token,$seconds,$alreadyUsed])$insert->execute([$f['project_id'],$f['owner_email'],$token,$seconds,$alreadyUsed]);
$bridge=new CryptoLocalAccessTokens($pdo,(int)$f['project_id'],$f['slug'],$f['db_name']);
foreach([$expired,$used,'invalid-token']as$token){try{$bridge->validate($token);throw new LogicException('Aceitou token invalido.');}catch(InvalidArgumentException $e){}}
$other=new CryptoLocalAccessTokens($pdo,(int)$f['project_id']+100000,$f['slug'],$f['db_name']);
try{$other->validate($valid);throw new LogicException('Aceitou outro projeto.');}catch(InvalidArgumentException $e){}
$bridge->validate($valid);
class CryptoTokenFailingPDO extends PDO {
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        if(str_starts_with($query,'UPDATE project_access_tokens SET used=1')) throw new PDOException('synthetic failure after password write');
        return parent::prepare($query,$options);
    }
}
$coreDb=$config['db'];
$failurePdo=new CryptoTokenFailingPDO('mysql:host='.$coreDb['host'].';dbname='.$coreDb['name'].';charset=utf8mb4',$coreDb['user'],$coreDb['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$failingBridge=new CryptoLocalAccessTokens($failurePdo,(int)$f['project_id'],$f['slug'],$f['db_name']);
try{$failingBridge->setPassword($valid,'synthetic-test-password');throw new LogicException('Esperava falha de banco.');}catch(PDOException $e){}
$stmt=$pdo->prepare('SELECT used FROM project_access_tokens WHERE token=?');$stmt->execute([$valid]);tokenAssert((int)$stmt->fetchColumn()===0,'Token consumido antes de concluir');
tokenAssert((int)$p->query('SELECT COUNT(*) FROM project_users')->fetchColumn()===0,'Gravacao parcial');
$bridge->setPassword($valid,'synthetic-test-password');
$user=$p->query('SELECT role,password FROM project_users')->fetch(PDO::FETCH_ASSOC);
tokenAssert($user['role']==='ADMIN'&&password_verify('synthetic-test-password',$user['password']),'Senha ou papel incorreto');
try{$bridge->setPassword($valid,'different-synthetic-password');throw new LogicException('Token reutilizado');}catch(InvalidArgumentException $e){}
tokenAssert($p->query('SELECT password FROM project_users')->fetchColumn()===$user['password'],'Reuso alterou senha');
$p->exec('DELETE FROM project_users');
$http=bin2hex(random_bytes(32));$insert->execute([$f['project_id'],$f['owner_email'],$http,3600,0]);
$f['access_token']=$http;$f['expired_token']=$expired;$f['used_token']=$used;
file_put_contents($root.'/_deploy/crypto-local-test.json',json_encode($f));
echo "PASS: tokens invalidos, expirados, usados e de outro projeto recusados; criacao/consumo atomicos e rollback validados.\n";
