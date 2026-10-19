<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__, 2);
require $root . '/app/helpers/mail_center.php';
require $root . '/app/services/mail/MailSchemaMigration.php';
require $root . '/app/services/mail/MailAccountService.php';
require $root . '/app/services/auth/AuthService.php';
require $root . '/app/helpers/session.php';
$checks = 0;
function mcExpect(bool $ok, string $message): void { global $checks; $checks++; if (!$ok) { throw new RuntimeException($message); } }
function mcReject(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException|DomainException|OutOfBoundsException $e) { mcExpect(true, $message); return; }
    throw new RuntimeException($message);
}
function mcRoute(string $file, array $post = [], array $get = [], int $actor = 1, string $method = 'GET', ?int $activity = null): array {
    $code = '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . ';$_SERVER["REQUEST_URI"]="/web/admin/central_contas/accounts.php";$_POST=' . var_export($post, true) . ';$_GET=' . var_export($get, true) . ';$testActor=' . $actor . ';$testActivity=' . ($activity ?? time()) . ';';
    $code .= 'register_shutdown_function(function(){fwrite(STDERR,"MC_RESULT:".json_encode(["status"=>http_response_code()?:200,"flash"=>$_SESSION["_flash"]??null])."\\n");});require ' . var_export($file, true) . ';';
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=32767', '-r', $code], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    $html = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    mcExpect(proc_close($process) === 0, 'Falha ao renderizar rota: ' . preg_replace('/MC_RESULT:.*/s', '', $errors));
    mcExpect(!preg_match('/Warning:|Fatal error:|Deprecated:/', $html . $errors), 'Aviso PHP em rota');
    preg_match('/MC_RESULT:(.*)/', $errors, $matches);
    return ['html' => $html] + json_decode($matches[1] ?? '{}', true);
}
$config = require $root . '/env/env.production.php'; $db = $config['db'];
$pdo = new PDO('mysql:host=' . $db['host'] . ';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'test_mail_center_' . bin2hex(random_bytes(6));
$fixture = sys_get_temp_dir() . '/' . $database;
$pdo->exec('CREATE DATABASE `' . $database . '`');
try {
    $pdo->exec('USE `' . $database . '`');
    $pdo->exec("CREATE TABLE core_users (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), email VARCHAR(150), password VARCHAR(255), avatar VARCHAR(100), role VARCHAR(20), status TINYINT) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO core_users VALUES (1,'Dono','owner@example.test','',NULL,'SUPER_ADMIN',1),(2,'Outro administrador','other@example.test','',NULL,'ADMIN',1),(3,'Inativo','inactive@example.test','',NULL,'SUPER_ADMIN',0),(4,'Comum','user@example.test','',NULL,'USER',1)");
    $pdo->exec("CREATE TABLE projects (id INT UNSIGNED PRIMARY KEY, name VARCHAR(100), status VARCHAR(30)) ENGINE=InnoDB");
    $pdo->exec("INSERT INTO projects VALUES (1,'Projeto de teste','active'),(2,'Projeto removido','deleted')");
    $migration = new MailSchemaMigration($pdo);
    $migration->migrate(false);
    mcExpect(count($pdo->query('SHOW TABLES')->fetchAll()) === 2, 'Simulação alterou banco');
    $migration->migrate(true); $migration->migrate(true);
    mcExpect(count($pdo->query('SHOW TABLES')->fetchAll()) === 7, 'Migração não idempotente');
    mcExpect(mailCenterReady($pdo), 'Schema não detectado');
    $options = ['mail_center' => ['enabled' => true, 'owner_user_id' => 1]];
    mcExpect(mailCenterOwnerId($pdo, $options, ['core_user' => ['id' => 1]]) === 1, 'Dono negado');
    foreach ([2,3,4,0] as $actor) { mcExpect(mailCenterOwnerId($pdo, $options, ['core_user' => ['id' => $actor]]) === null, 'Acesso indevido de outro usuário'); }
    foreach ([[], ['mail_center'=>['enabled'=>true]], ['mail_center'=>['enabled'=>false,'owner_user_id'=>1]], ['mail_center'=>['enabled'=>true,'owner_user_id'=>3]], ['mail_center'=>['enabled'=>true,'owner_user_id'=>4]]] as $bad) {
        mcExpect(mailCenterOwnerId($pdo, $bad, ['core_user'=>['id'=>$bad['mail_center']['owner_user_id']??1]]) === null, 'Configuração inválida liberou acesso');
    }
    mcExpect(!mailCenterValidPost('GET', ['_csrf'=>'test'], ['_csrf'=>'test']), 'GET aceito');
    foreach ([[],['_csrf'=>''],['_csrf'=>[]],['_csrf'=>'wrong']] as $post) { mcExpect(!mailCenterValidPost('POST',$post,['_csrf'=>'test']), 'CSRF inválido aceito'); }
    mcExpect(!mailCenterValidPost('POST',['_csrf'=>''],[]), 'Token vazio aceito');
    mcExpect(mailCenterValidPost('POST', ['_csrf'=>'test'], ['_csrf'=>'test']), 'CSRF correto negado');
    $service = new MailAccountService($pdo, 1); $other = new MailAccountService($pdo, 2);
    mcReject(fn() => new MailAccountService($pdo, 3), 'Usuário inativo aceito');
    $providers = array_column($service->providers(), 'id', 'code');
    mcExpect(array_keys($providers) === ['gmail','outlook','zoho'], 'Provedores fora do escopo');
    $category = $service->saveCategory(['name'=>'Cadastros','color'=>'#2563eb']);
    $foreignCategory = $other->saveCategory(['name'=>'Privada','color'=>'#123456']);
    $data = ['label'=>'Gmail de cadastros','email'=>'owner@gmail.com','provider_id'=>$providers['gmail'],'category_id'=>$category,'project_id'=>1,'purpose'=>'Plataformas e sites'];
    $id = $service->save($data);
    mcExpect($service->find($id)['connection_status'] === 'not_connected', 'Conexão fictícia');
    mcReject(fn() => $other->find($id), 'Consulta cruzada aceita');
    mcReject(fn() => $other->save([...$data,'id'=>$id,'revision'=>1]), 'Edição cruzada aceita');
    mcReject(fn() => $other->setActive($id,1,false), 'Desativação cruzada aceita');
    mcReject(fn() => $service->save([...$data,'email'=>'other@gmail.com','category_id'=>$foreignCategory]), 'Categoria cruzada aceita');
    mcReject(fn() => $service->save([...$data,'email'=>'other@gmail.com','project_id'=>2]), 'Projeto removido aceito');
    foreach ([['email'=>'not-email'], ['label'=>['bad']], ['email'=>"a@gmail.com\r\nBcc:x@example.test"], ['provider_id'=>999], ['password'=>'never-save-this'], ['owner_user_id'=>2], ['purpose'=>str_repeat('a',501)]] as $bad) {
        mcReject(fn() => $service->save([...$data,...$bad]), 'Dados inválidos aceitos');
    }
    mcReject(fn() => $service->save($data), 'Conta duplicada aceita');
    $service->save([...$data,'id'=>$id,'revision'=>1,'label'=>'Cadastros atualizados']);
    mcReject(fn() => $service->save([...$data,'id'=>$id,'revision'=>1]), 'Edição obsoleta aceita');
    $service->setActive($id,2,false); $service->setActive($id,3,true);
    $service->saveCategory(['id'=>$category,'revision'=>1,'name'=>'Sites e plataformas','color'=>'#2545ab']);
    mcReject(fn() => $other->saveCategory(['id'=>$category,'revision'=>2,'name'=>'Invadida','color'=>'#123456']), 'Categoria de outro dono alterada');
    mcReject(fn() => $service->saveCategory(['id'=>$category,'revision'=>1,'name'=>'Antiga','color'=>'#123456']), 'Categoria obsoleta alterada');
    mcReject(fn() => $service->saveCategory(['name'=>'Sites e plataformas','color'=>'red']), 'Cor inválida aceita');
    $audit = json_encode($pdo->query('SELECT * FROM core_mail_audit_events')->fetchAll(PDO::FETCH_ASSOC));
    mcExpect(!str_contains($audit,'@') && !str_contains($audit,'never-save-this') && !str_contains($audit,'Plataformas e sites'), 'Auditoria contém dados sensíveis');
    $columns = implode(',', $pdo->query('SHOW COLUMNS FROM core_mail_accounts')->fetchAll(PDO::FETCH_COLUMN));
    mcExpect(!preg_match('/password|secret|token/i', $columns), 'Fundação tem campo de segredo');
    $service->save(['label'=>'<script>alert(1)</script>','email'=>'safe@outlook.com','provider_id'=>$providers['outlook']]);
    $service->save(['label'=>'Projeto','email'=>'project@example.test','provider_id'=>$providers['zoho']]);
    foreach (['app/bootstrap','web/admin/central_contas','app/actions/central_contas'] as $dir) { mkdir($fixture.'/'.$dir,0700,true); }
    foreach (glob($root.'/web/admin/central_contas/*.php') as $file) { copy($file,$fixture.'/web/admin/central_contas/'.basename($file)); }
    foreach (glob($root.'/app/actions/central_contas/*.php') as $file) { copy($file,$fixture.'/app/actions/central_contas/'.basename($file)); }
    $bootstrap = '<?php $source=' . var_export($root,true) . ';$real=require $source."/env/env.production.php";$d=$real["db"];$pdo=new PDO("mysql:host=".$d["host"].";dbname=' . $database . ';charset=utf8mb4",$d["user"],$d["pass"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$config=["mail_center"=>["enabled"=>true,"owner_user_id"=>1]];$_SESSION=["core_user"=>["id"=>$testActor,"role"=>"SUPER_ADMIN"],"_csrf"=>"test","core_authenticated_at"=>$testActivity];define("ROOT_PATH",$source);define("APP_PATH",$source."/app");define("PUBLIC_PATH",$source."/web");define("STORAGE_PATH",' . var_export($fixture.'/storage',true) . ');foreach(["csrf","flash","http","ui_helper","mail_center"] as $h) require_once APP_PATH."/helpers/".$h.".php";require_once APP_PATH."/services/mail/MailAccountService.php";$coreSettings=["app_name"=>"Core","app_logo"=>"","app_favicon"=>""];$theme="light";';
    file_put_contents($fixture.'/app/bootstrap/bootstrap.php',$bootstrap);
    $page = $fixture.'/web/admin/central_contas/accounts.php'; $action=$fixture.'/app/actions/central_contas/account_save.php';
    foreach ([0,2,3,4] as $actor) { mcExpect(mcRoute($page,[],[],$actor)['status']===404,'Rota autorizou outro usuário'); }
    mcExpect(str_contains(mcRoute($page,[],[],1,'GET',time()-3600)['html'],'') && mcRoute($page,[],[],1,'GET',time()-3600)['html']==='', 'Inatividade não redirecionou');
    mcExpect(mcRoute($action) ['status']===405,'Rota aceitou GET');
    mcExpect(mcRoute($action,['_csrf'=>[]],[],1,'POST')['status']===403,'Rota aceitou CSRF adulterado');
    mcExpect(mcRoute($action,['_csrf'=>'bad'],[],1,'POST')['status']===403,'Rota aceitou CSRF errado');
    $result=mcRoute($action,[...$data,'email'=>'route@gmail.com','_csrf'=>'test'],[],1,'POST');
    mcExpect(($result['flash']['type']??'')==='success','Rota de cadastro falhou');
    $foreign=mcRoute($action,[...$data,'id'=>$id,'revision'=>4,'_csrf'=>'test'],[],2,'POST');
    mcExpect($foreign['status']===404,'Rota de ação autorizou outro dono');
    $statusRoute=$fixture.'/app/actions/central_contas/account_status.php';
    $categoryRoute=$fixture.'/app/actions/central_contas/category_save.php';
    foreach ([$statusRoute,$categoryRoute] as $writeRoute) {
        mcExpect(mcRoute($writeRoute)['status']===405,'Ação aceitou GET');
        mcExpect(mcRoute($writeRoute,['_csrf'=>'bad'],[],1,'POST')['status']===403,'Ação ignorou CSRF');
        mcExpect(mcRoute($writeRoute,['_csrf'=>'test'],[],2,'POST')['status']===404,'Ação autorizou outro dono');
    }
    mcExpect((mcRoute($statusRoute,['_csrf'=>'test','id'=>$id,'revision'=>4,'is_active'=>'0'],[],1,'POST')['flash']['type']??'')==='success','Rota de desativação falhou');
    mcExpect((mcRoute($categoryRoute,['_csrf'=>'test','name'=>'Categoria pela rota','color'=>'#2545ab'],[],1,'POST')['flash']['type']??'')==='success','Rota de categoria falhou');
    $preview=$root.'/_deploy'; if(!is_dir($preview)) mkdir($preview,0700,true);
    foreach (['accounts'=>$page,'overview'=>$fixture.'/web/admin/central_contas/index.php'] as $name=>$file) {
        $result=mcRoute($file); mcExpect($result['status']===200,'Página indisponível');
        mcExpect(!str_contains($result['html'],'<script>alert(1)</script>'),'XSS na renderização');
        mcExpect(str_contains($result['html'],'&lt;script&gt;alert(1)&lt;/script&gt;'),'Texto não escapado');
        file_put_contents($preview.'/mail-center-'.$name.'.html',$result['html']);
    }
    $edit=mcRoute($page,[],['edit'=>$id]); file_put_contents($preview.'/mail-center-edit.html',$edit['html']);
    mcExpect(mcRoute($page,[],['edit'=>999])['status']===404,'ID ausente não retorna 404');
    $pdo->exec('DELETE FROM core_mail_audit_events'); $pdo->exec('DELETE FROM core_mail_accounts');
    file_put_contents($preview.'/mail-center-empty.html',mcRoute($fixture.'/web/admin/central_contas/index.php')['html']);
    $stmt=$pdo->prepare("INSERT INTO core_mail_audit_events (owner_user_id,action,result,correlation_id,created_at) VALUES (1,'category.created','success',?,UTC_TIMESTAMP())");
    for($i=0;$i<30;$i++){$stmt->execute([bin2hex(random_bytes(16))]);}
    mcReject(fn()=>$service->save(['label'=>'Limite','email'=>'limit@gmail.com','provider_id'=>$providers['gmail']]),'Limite de alterações ignorado');
    mcExpect(count($service->accounts())===0,'Operação limitada gravou dados');
    $pdo->exec("UPDATE core_mail_schema_versions SET checksum=REPEAT('0',64)");
    $checksumRejected=false; try{$migration->migrate(false);}catch(RuntimeException $e){$checksumRejected=true;}
    mcExpect($checksumRejected,'Checksum divergente aceito');
    $stmt=$pdo->prepare('UPDATE core_users SET password=?'); $stmt->execute([password_hash('synthetic-test-password', PASSWORD_DEFAULT)]);
    $auth=new AuthService($pdo);
    mcExpect($auth->login('owner@example.test','wrong')===false,'Senha incorreta aceita');
    coreConfigureSession(['app_url'=>'http://localhost:8000']); session_start();
    $cookie=session_get_cookie_params();
    mcExpect($cookie['httponly'] && $cookie['samesite']==='Lax' && !$cookie['secure'] && ini_get('session.use_strict_mode')==='1','Política de sessão local incorreta');
    $oldSession=session_id(); $_SESSION['_csrf']='old-token';
    $auth->createSession($auth->login('owner@example.test','synthetic-test-password'));
    mcExpect(session_id()!==$oldSession && !isset($_SESSION['_csrf']) && isset($_SESSION['core_authenticated_at']),'Login não renovou sessão/CSRF');
    $auth->logout();
    coreConfigureSession(['app_url'=>'https://example.test']);
    mcExpect(session_get_cookie_params()['secure'],'Cookie HTTPS sem Secure');
    echo 'PASS: '.$checks.' verificações — migração, dono, CSRF, CRUD, concorrência, isolamento, XSS e rotas; banco sintético.' . PHP_EOL;
} finally {
    if (preg_match('/^test_mail_center_[a-f0-9]{12}$/D',$database)) { $pdo->exec('DROP DATABASE `'.$database.'`'); }
    if (is_dir($fixture) && dirname($fixture)===sys_get_temp_dir() && basename($fixture)===$database) {
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());} rmdir($fixture);
    }
}
