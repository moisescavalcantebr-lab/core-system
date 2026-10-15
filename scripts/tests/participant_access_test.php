<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
$root=dirname(__DIR__,2);
function verify(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
function participantTestRoute(string $path,array $post=[],array $get=[],int $user=1,array $input=[]): string
{
    $code='$_SERVER["REQUEST_METHOD"]='.var_export($post?'POST':'GET',true).';$_SERVER["PHP_SELF"]='.var_export(basename($path),true).';$_POST='.var_export($post,true).';$_GET='.var_export($get,true).';$testUser='.$user.';$testInput='.var_export($input,true).';require '.var_export($path,true).';';
    $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    verify(proc_close($process)===0,'Falha na rota: '.$error);
    verify(!str_contains($out,'Warning:')&&!str_contains($out,'Fatal error:'),'Aviso na rota: '.$out);
    return $out;
}
$config=require $root.'/env/env.production.php'; $db=$config['db'];
$pdo=new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='test_participant_access_'.bin2hex(random_bytes(5));
$fixture=sys_get_temp_dir().'/'.$database;
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $pdo->exec("CREATE TABLE project_users (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,email VARCHAR(150) UNIQUE,username VARCHAR(80) UNIQUE,password VARCHAR(255),must_change_password INT DEFAULT 0,role VARCHAR(50),status ENUM('active','inactive','blocked'),avatar VARCHAR(255)) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE project_settings (setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT,setting_group VARCHAR(100))");
    $pdo->exec((string)file_get_contents($root.'/modules/participantes/database/schema.sql'));
    $pdo->exec("INSERT INTO project_users (id,name,email,username,password,role,status) VALUES (1,'Admin','admin@example.com','admin','fixture','ADMIN','active'),(2,'Cliente','client@example.com','client','fixture','CLIENT','active'),(3,'Inativo','inactive@example.com','inactive','fixture','ADMIN','inactive'),(4,'Bloqueado','blocked@example.com','blocked','fixture','ADMIN','blocked')");
    $pdo->exec('CREATE TABLE players (id INT PRIMARY KEY,participant_id INT,user_id INT NULL,name VARCHAR(150),status VARCHAR(20))');
    foreach (['app/bootstrap','app/helpers','app/views','web/admin/participantes','modules/participantes'] as $dir) mkdir($fixture.'/'.$dir,0700,true);
    foreach (glob($root.'/modules/participantes/web/admin/participantes/*.php') as $file) copy($file,$fixture.'/web/admin/participantes/'.basename($file));
    foreach (['project_auth.php','csrf.php','settings.php'] as $file) copy($root.'/bases/futebol/app/helpers/'.$file,$fixture.'/app/helpers/'.$file);
    $manifestPath=$fixture.'/modules/participantes/module.json';
    copy($root.'/bases/futebol/modules/participantes/module.json',$manifestPath);
    $connection='$pdo=new PDO('.var_export('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4',true).','.var_export($db['user'],true).','.var_export($db['pass'],true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    $bootstrap='<?php define("PROJECT_PATH",'.var_export($fixture,true).');define("APP_PATH",PROJECT_PATH."/app");define("PROJECT_URL","/projects/football-test/web");$_SESSION=["project_user_id"=>$testUser,"_csrf"=>"test","participant_form_input"=>$testInput];'.$connection.'require APP_PATH."/helpers/project_auth.php";require APP_PATH."/helpers/csrf.php";require APP_PATH."/helpers/settings.php";function flash($type,$msg){echo $msg."\n";}function flash_show(){}function redirect($url){if(isset($_SESSION["participant_form_input"]["password"]))echo "PASSWORD_STORED";echo "REDIRECT:".$url;exit;}';
    file_put_contents($fixture.'/app/bootstrap/project_bootstrap.php',$bootstrap);
    $css=''; foreach (['core_base.css','admin/layout.css','admin/components.css','themes/dark.css'] as $file) $css.=(string)file_get_contents($root.'/bases/futebol/web/assets/css/'.$file);
    file_put_contents($fixture.'/app/views/layout_admin.php','<?php echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content=\"width=device-width,initial-scale=1\"><style>".'.var_export($css,true).'."body{padding:20px}.c-content{margin:0!important;max-width:1200px}</style></head><body class=c-app><main class=c-content>".$content."</main></body></html>";');
    $routes=$fixture.'/web/admin/participantes/';
    $post=['_csrf'=>'test','name'=>'Pessoa sem login','nickname'=>'Pessoa','whatsapp'=>'11999999999','birth_date'=>'1990-01-01','status'=>'active','notes'=>'Teste'];
    $accounts=fn()=>(int)$pdo->query('SELECT COUNT(*) FROM project_users')->fetchColumn();
    $people=fn()=>(int)$pdo->query('SELECT COUNT(*) FROM participants')->fetchColumn();
    $find=function(int $id) use ($pdo): array { $s=$pdo->prepare('SELECT * FROM participants WHERE id=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC); };
    verify(str_contains(participantTestRoute($routes.'store.php',[...$post,'_csrf'=>'invalid']),'CSRF'),'CSRF ignorado');
    foreach ([2,3,4] as $actor) verify(str_contains(participantTestRoute($routes.'store.php',$post,[],$actor),'Acesso negado'),'Administrador inativo ou usuario comum autorizado');
    verify(str_contains(participantTestRoute($routes.'registration_toggle.php',['_csrf'=>'test','enabled'=>'1'],[],3),'Acesso negado'),'Administrador inativo liberou cadastro publico');
    verify($people()===0&&$accounts()===4,'Guard permitiu escrita');
    verify(str_contains(participantTestRoute($routes.'store.php',[...$post,'email'=>'invalid','username'=>[],'password'=>[],'role'=>'ADMIN','participant_access'=>'required']),'cadastrado com sucesso'),'Cadastro sem acesso falhou');
    $id=(int)$pdo->query('SELECT MAX(id) FROM participants')->fetchColumn();
    verify($find($id)['user_id']===null&&$accounts()===4,'Cadastro sem acesso criou conta');
    $pdo->prepare('INSERT INTO players VALUES (10,?,NULL,?,?)')->execute([$id,'Atleta existente','active']);
    $edit=[...$post,'expected_user_id'=>'0','name'=>'Pessoa editada'];
    verify(str_contains(participantTestRoute($routes.'update.php',$edit,['id'=>$id]),'atualizado'),'Edicao sem conta falhou');
    verify($find($id)['user_id']===null&&$people()===1,'Edicao criou outra pessoa ou conta');
    $access=[...$edit,'create_access'=>'1','email'=>'person@example.com','username'=>'person','password'=>'test-password','status'=>'inactive','role'=>'ADMIN','user_id'=>1];
    foreach ([['password'=>''],['email'=>'bad'],['username'=>'x'],['birth_date'=>'2026-02-30'],['name'=>[]]] as $invalid) {
        participantTestRoute($routes.'update.php',[...$access,...$invalid],['id'=>$id]);
        verify($find($id)['name']==='Pessoa editada'&&$find($id)['user_id']===null&&$accounts()===4,'Entrada invalida alterou cadastro ou conta');
    }
    verify(str_contains(participantTestRoute($routes.'update.php',$access,['id'=>$id]),'atualizado'),'Criacao posterior de acesso falhou');
    $userId=(int)$find($id)['user_id'];
    $user=$pdo->query('SELECT * FROM project_users WHERE id='.$userId)->fetch(PDO::FETCH_ASSOC);
    verify($userId>4&&$user['role']==='CLIENT'&&$user['status']==='active','Papel ou estado de conta incorreto');
    verify(password_verify('test-password',$user['password']),'Senha nao foi salva como hash');
    verify($people()===1&&$accounts()===5,'Criacao posterior duplicou pessoa ou conta');
    verify($pdo->query('SELECT user_id FROM players WHERE id=10')->fetchColumn()===null,'Participantes alterou o perfil esportivo');
    verify(str_contains(participantTestRoute($routes.'update.php',$access,['id'=>$id]),'vinculo de acesso mudou'),'Tela antiga sobrescreveu conta nova');
    $existing=[...$access,'expected_user_id'=>(string)$userId,'password'=>'','create_access'=>'0','status'=>'active'];
    $pdo->exec("UPDATE project_users SET role='PLAYER',status='blocked' WHERE id=$userId");
    verify(str_contains(participantTestRoute($routes.'update.php',$existing,['id'=>$id]),'atualizado'),'Edicao da conta existente falhou');
    $preserved=$pdo->query('SELECT * FROM project_users WHERE id='.$userId)->fetch(PDO::FETCH_ASSOC);
    verify($preserved['password']===$user['password']&&$preserved['role']==='PLAYER'&&$preserved['status']==='blocked','Edicao alterou senha, papel ou desbloqueou acesso');
    verify((int)$find($id)['user_id']===$userId,'Conta existente desvinculada');
    participantTestRoute($routes.'toggle.php',['_csrf'=>'test'],['id'=>$id]);
    verify($find($id)['status']==='inactive'&&$pdo->query('SELECT status FROM project_users WHERE id='.$userId)->fetchColumn()==='blocked','Status da pessoa alterou acesso opcional');
    $before=$find($id);
    $duplicate=participantTestRoute($routes.'update.php',[...$existing,'email'=>'admin@example.com','name'=>'Nao deve salvar'],['id'=>$id]);
    verify(str_contains($duplicate,'Ja existe outro usuario')&&!str_contains($duplicate,'PASSWORD_STORED'),'Conflito ignorado ou senha guardada na sessao');
    verify($find($id)===$before&&$accounts()===5,'Conflito deixou alteracao parcial');
    $fresh=participantTestRoute($routes.'store.php',[...$post,'name'=>'Outra pessoa','create_access'=>'1','email'=>'second@example.com','username'=>'second','password'=>'test-password']);
    verify(str_contains($fresh,'cadastrado com sucesso')&&$accounts()===6,'Cadastro novo com acesso falhou');
    $secondId=(int)$pdo->query('SELECT MAX(id) FROM participants')->fetchColumn();
    $secondUserId=(int)$find($secondId)['user_id'];
    $secondPost=[...$post,'expected_user_id'=>(string)$secondUserId,'email'=>'second@example.com','username'=>'second','password'=>'','expected_access_status'=>'active','access_status'=>'inactive'];
    verify(str_contains(participantTestRoute($routes.'update.php',$secondPost,['id'=>$secondId]),'atualizado'),'Alteracao explicita de acesso falhou');
    verify($find($secondId)['status']==='active'&&$pdo->query('SELECT status FROM project_users WHERE id='.$secondUserId)->fetchColumn()==='inactive','Estado de acesso alterou pessoa');
    $pdo->exec("UPDATE project_users SET status='blocked' WHERE id=$secondUserId");
    verify(str_contains(participantTestRoute($routes.'update.php',[...$secondPost,'expected_access_status'=>'inactive','access_status'=>'active'],['id'=>$secondId]),'estado de acesso mudou'),'Estado antigo desbloqueou conta');
    verify(str_contains(participantTestRoute($routes.'update.php',[...$secondPost,'access_status'=>'active'],['id'=>$secondId]),'atualizado'),'Edicao sem mudanca de acesso falhou');
    verify($pdo->query('SELECT status FROM project_users WHERE id='.$secondUserId)->fetchColumn()==='blocked','Campo de acesso inalterado sobrescreveu estado novo');
    $preview=$root.'/_deploy'; if (!is_dir($preview)) mkdir($preview,0700,true);
    file_put_contents($preview.'/participants-optional-create-preview.html',participantTestRoute($routes.'create.php'));
    file_put_contents($preview.'/participants-optional-linked-preview.html',participantTestRoute($routes.'edit.php',[],['id'=>$id]));
    $unlinkedPost=[...$post,'name'=>'Sem acesso para editar']; participantTestRoute($routes.'store.php',$unlinkedPost);
    $unlinked=(int)$pdo->query('SELECT MAX(id) FROM participants')->fetchColumn();
    file_put_contents($preview.'/participants-optional-edit-preview.html',participantTestRoute($routes.'edit.php',[],['id'=>$unlinked]));
    file_put_contents($preview.'/participants-optional-index-preview.html',participantTestRoute($routes.'index.php'));
    $recover=['id'=>0,'name'=>'Pessoa <teste>','create_access'=>'1','email'=>'duplicate@example.com','password'=>'secret-not-rendered'];
    $recovered=participantTestRoute($routes.'create.php',[],[],1,$recover);
    verify(str_contains($recovered,'Pessoa &lt;teste&gt;')&&!str_contains($recovered,'secret-not-rendered'),'Formulario perdeu dados ou expôs senha');
    file_put_contents($preview.'/participants-optional-error-preview.html',$recovered);
    copy($root.'/modules/participantes/module.json',$manifestPath);
    $count=$people(); $accountCount=$accounts();
    verify(str_contains(participantTestRoute($routes.'store.php',[...$post,'create_access'=>'0','participant_access'=>'optional']),'e-mail valido'),'Outra base aceitou cadastro sem acesso');
    verify($people()===$count&&$accounts()===$accountCount,'Politica obrigatoria alterada pelo POST');
    participantTestRoute($routes.'store.php',[...$post,'status'=>'pending','email'=>'required@example.com','username'=>'required','password'=>'test-password']);
    $requiredId=(int)$pdo->query('SELECT MAX(id) FROM participants')->fetchColumn();
    $requiredUserId=(int)$find($requiredId)['user_id'];
    verify($requiredUserId>0&&$pdo->query('SELECT status FROM project_users WHERE id='.$requiredUserId)->fetchColumn()==='inactive','Estado anterior da politica obrigatoria mudou');
    participantTestRoute($routes.'toggle.php',['_csrf'=>'test'],['id'=>$requiredId]);
    verify($pdo->query('SELECT status FROM project_users WHERE id='.$requiredUserId)->fetchColumn()==='active','Politica obrigatoria deixou de vincular status');
    verify(str_contains(participantTestRoute($routes.'update.php',[...$post,'name'=>'Obrigatorio editado','email'=>'required@example.com','username'=>'required','password'=>'','status'=>'inactive'],['id'=>$requiredId]),'atualizado'),'Outra base exigiu campos da politica opcional');
    verify($pdo->query('SELECT status FROM project_users WHERE id='.$requiredUserId)->fetchColumn()==='inactive','Edicao obrigatoria deixou de sincronizar status');
    file_put_contents($preview.'/participants-required-create-preview.html',participantTestRoute($routes.'create.php'));
    file_put_contents($preview.'/participants-required-edit-preview.html',participantTestRoute($routes.'edit.php',[],['id'=>$requiredId]));
    unlink($manifestPath);
    verify(str_contains(participantTestRoute($routes.'store.php',$post),'e-mail valido'),'Manifesto ausente liberou acesso opcional');
    file_put_contents($manifestPath,'{');
    verify(str_contains(participantTestRoute($routes.'store.php',$post),'e-mail valido'),'Manifesto invalido liberou acesso opcional');
    foreach (['helpers.php','store.php','update.php','form.php','toggle.php','edit.php','create.php','index.php','registration_toggle.php'] as $file) {
        $source=$root.'/modules/participantes/web/admin/participantes/'.$file;
        foreach (['bases/futebol/modules/participantes/web/admin/participantes/','bases/futebol/web/admin/participantes/'] as $target) verify(hash_file('sha256',$source)===hash_file('sha256',$root.'/'.$target.$file),'Copia divergente: '.$file);
    }
    verify($pdo->query('SELECT COUNT(*) FROM players')->fetchColumn()==1,'Perfil esportivo apagado');
    echo "PASS: acesso opcional no Futebol, cadastro sem conta, acesso posterior, vinculos, estados independentes, rollback, CSRF, papeis e demais bases preservados.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if (is_dir($fixture)&&str_starts_with($fixture,sys_get_temp_dir().'/test_participant_access_')) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($fixture);
    }
}
