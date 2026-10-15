<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
$root=dirname(__DIR__,2);
function checkSync(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function syncTestRoute(string $route,array $post,int $role=1): string
{
    $code='ob_start();if(!posix_setgid(33)||!posix_setuid(33))exit(99);$_SERVER["REQUEST_METHOD"]="POST";$_POST='.var_export($post,true).';$testRole='.($role?'"ADMIN"':'"USER"').';require '.var_export($route,true).';';
    $proc=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);
    checkSync(proc_close($proc)===0,'Falha no processo: '.$error);
    checkSync(!str_contains($out,'Fatal error')&&!str_contains($out,'Warning:'),'Falha nao tratada: '.$out);
    return $out;
}
$config=require $root.'/env/env.production.php';$db=$config['db'];
$pdo=new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='test_module_sync_'.bin2hex(random_bytes(5)); $projectDb=$name.'_project'; $fixture=sys_get_temp_dir().'/'.$name;
$pdo->exec('CREATE DATABASE `'.$name.'`'); $pdo->exec('CREATE DATABASE `'.$projectDb.'`');
try {
    $pdo->exec('USE `'.$name.'`');
    $pdo->exec('CREATE TABLE bases (id INT PRIMARY KEY,slug VARCHAR(100)); CREATE TABLE projects (id INT PRIMARY KEY,base_id INT,path VARCHAR(255)); CREATE TABLE project_logs (project_id INT,action VARCHAR(100),message TEXT,level VARCHAR(30) DEFAULT "info"); CREATE TABLE base_module_prices (id INT PRIMARY KEY,settings_json TEXT)');
    $pdo->exec("INSERT INTO bases VALUES (1,'futebol'); INSERT INTO projects VALUES (1,1,'projects/demo'); INSERT INTO base_module_prices VALUES (1,'preservar-precos-e-configuracao')");
    $project=new PDO('mysql:host='.$db['host'].';dbname='.$projectDb.';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $project->exec('CREATE TABLE sync_settings (setting_key VARCHAR(80) PRIMARY KEY,setting_value VARCHAR(100)); CREATE TABLE client_data (id INT PRIMARY KEY,value VARCHAR(100))');
    $project->exec("INSERT INTO sync_settings VALUES ('mode','configuracao-do-cliente'); INSERT INTO client_data VALUES (1,'dados-do-cliente')");
    foreach (['app/bootstrap','app/helpers','app/actions/projects','projects/demo/app/config','projects/demo/modules','projects/demo/web/admin'] as $dir) mkdir($fixture.'/'.$dir,0777,true);
    foreach (['participantes','financeiro'] as $slug) {
        foreach (['modules/'.$slug,'bases/futebol/modules/'.$slug] as $dir) {
            mkdir($fixture.'/'.$dir.'/database',0777,true); mkdir($fixture.'/'.$dir.'/web/admin/'.$slug,0777,true);
        }
        $manifest=['name'=>$slug,'version'=>'2','copy'=>[['from'=>'web/admin/'.$slug,'to'=>'web/admin/'.$slug]],'database'=>['schema'=>'database/schema.sql']];
        if ($slug==='participantes') $manifest['participant_access']='required';
        file_put_contents($fixture.'/modules/'.$slug.'/module.json',json_encode($manifest));
        $manifest['version']='1';$manifest['base_option']='preservar';
        if ($slug==='participantes') $manifest['participant_access']='optional';
        file_put_contents($fixture.'/bases/futebol/modules/'.$slug.'/module.json',json_encode($manifest));
        file_put_contents($fixture.'/modules/'.$slug.'/web/admin/'.$slug.'/index.php','<?php echo "catalogo atual";');
        file_put_contents($fixture.'/bases/futebol/modules/'.$slug.'/web/admin/'.$slug.'/index.php','<?php echo "copia antiga";');
        file_put_contents($fixture.'/modules/'.$slug.'/database/schema.sql',"INSERT INTO sync_settings VALUES ('mode','catalogo') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);");
        file_put_contents($fixture.'/bases/futebol/modules/'.$slug.'/database/schema.sql',"INSERT INTO sync_settings VALUES ('mode','configuracao-da-base') ON DUPLICATE KEY UPDATE setting_key=setting_key;");
    }
    mkdir($fixture.'/modules/nao_instalado',0777,true);
    file_put_contents($fixture.'/modules/nao_instalado/module.json','{"name":"nao_instalado"}');
    foreach (['module_sync.php','auth.php'] as $file) copy($root.'/app/helpers/'.$file,$fixture.'/app/helpers/'.$file);
    copy($root.'/bases/futebol/app/helpers/csrf.php',$fixture.'/app/helpers/csrf.php');
    copy($root.'/app/actions/projects/modules_sync.php',$fixture.'/app/actions/projects/modules_sync.php');
    $connection='$pdo=new PDO('.var_export('mysql:host='.$db['host'].';dbname='.$name.';charset=utf8mb4',true).','.var_export($db['user'],true).','.var_export($db['pass'],true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    file_put_contents($fixture.'/app/bootstrap/bootstrap.php','<?php define("ROOT_PATH",'.var_export($fixture,true).');define("APP_PATH",ROOT_PATH."/app");define("BASES_PATH",ROOT_PATH."/bases");$_SESSION=["core_user"=>["id"=>9,"role"=>$testRole],"_csrf"=>"test"];'.$connection.'require APP_PATH."/helpers/csrf.php";function flash($type,$message){echo $type.":".$message;}');
    file_put_contents($fixture.'/projects/demo/app/config/database.php','<?php return '.var_export(['host'=>$db['host'],'name'=>$projectDb,'user'=>$db['user'],'pass'=>$db['pass'],'charset'=>'utf8mb4'],true).';');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $file) chmod($file->getPathname(),$file->isDir()?0777:0644);
    chmod($fixture,0755);
    $route=$fixture.'/app/actions/projects/modules_sync.php'; $post=['id'=>1,'mode'=>'all','_csrf'=>'test'];
    checkSync(str_contains(syncTestRoute($route,$post,0),'Acesso restrito'),'Core sem privilegio sincronizou');
    checkSync(str_contains(syncTestRoute($route,[...$post,'_csrf'=>'invalid']),'CSRF'),'CSRF invalido aceito');
    checkSync((int)$pdo->query('SELECT COUNT(*) FROM project_logs')->fetchColumn()===0,'Guards executaram sincronizacao');
    checkSync(str_contains(syncTestRoute($route,$post),'Módulos sincronizados'),'Sincronizacao all falhou');
    $baseParticipant=$fixture.'/bases/futebol/modules/participantes/module.json';
    $baseManifest=json_decode((string)file_get_contents($baseParticipant),true);
    $projectManifest=json_decode((string)file_get_contents($fixture.'/projects/demo/modules/participantes/module.json'),true);
    checkSync($baseManifest['participant_access']==='optional'&&$projectManifest['participant_access']==='optional','Acesso opcional perdido');
    checkSync($baseManifest['version']==='2'&&$baseManifest['base_option']==='preservar','Versao antiga ou configuracao perdida');
    checkSync(str_contains((string)file_get_contents($fixture.'/bases/futebol/modules/financeiro/database/schema.sql'),'configuracao-da-base'),'Schema customizado sobrescrito');
    checkSync($project->query('SELECT setting_value FROM sync_settings WHERE setting_key="mode"')->fetchColumn()==='configuracao-do-cliente','Configuracao do banco alterada');
    checkSync($project->query('SELECT value FROM client_data')->fetchColumn()==='dados-do-cliente','Dados apagados');
    checkSync($pdo->query('SELECT settings_json FROM base_module_prices')->fetchColumn()==='preservar-precos-e-configuracao','Configuracao comercial alterada');
    checkSync(!is_dir($fixture.'/bases/futebol/modules/nao_instalado')&&!is_dir($fixture.'/projects/demo/modules/nao_instalado'),'Modulo novo instalado indevidamente');
    $hash=hash_file('sha256',$baseParticipant);
    syncTestRoute($route,$post);
    checkSync(hash_file('sha256',$baseParticipant)===$hash,'Repeticao alterou politica');
    $finance=$fixture.'/bases/futebol/modules/financeiro/module.json';
    $old=json_decode((string)file_get_contents($finance),true);$old['version']='1';file_put_contents($finance,json_encode($old));
    $original=file_get_contents($finance); chmod($finance,0444);chmod(dirname($finance),0555);
    $error=syncTestRoute($route,$post);
    checkSync(str_contains($error,'Nao foi possivel sincronizar'),'Erro de permissao nao tratado');
    checkSync(file_get_contents($finance)===$original,'Falha removeu ou alterou o arquivo original');
    $log=$pdo->query('SELECT message FROM project_logs WHERE level="error" ORDER BY message LIMIT 1')->fetchColumn();
    checkSync(str_contains((string)$log,'module.json'),'Erro de permissao nao foi registrado');
    chmod(dirname($finance),0777);
    $count=$pdo->query('SELECT COUNT(*) FROM project_logs')->fetchColumn();
    checkSync(str_contains(syncTestRoute($route,$post),'Módulos sincronizados'),'Nao substituiu arquivo readonly em pasta gravavel');
    checkSync(json_decode((string)file_get_contents($finance),true)['version']==='2','Arquivo readonly nao foi atualizado');
    checkSync(fileowner($finance)===33,'Copia nao pertence ao usuario PHP');
    checkSync($pdo->query('SELECT COUNT(*) FROM project_logs')->fetchColumn()===$count+1,'Repeticao apos falha nao registrou sucesso');
    checkSync(glob(dirname($finance).'/.module-sync-*')===[],'Arquivos temporarios ficaram acumulados');
    $before=file_get_contents($baseParticipant);file_put_contents($fixture.'/modules/participantes/module.json','{');
    checkSync(str_contains(syncTestRoute($route,$post),'Nao foi possivel sincronizar'),'Manifesto invalido nao tratado');
    checkSync(file_get_contents($baseParticipant)===$before,'Manifesto invalido apagou configuracao da base');
    echo "PASS: sincronizacao real como www-data, politicas e schemas preservados, all sem novos modulos, erro tratado, arquivo readonly, copia segura, dados e configuracoes mantidos.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$projectDb.'`');$pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    if (is_dir($fixture)&&str_starts_with($fixture,sys_get_temp_dir().'/test_module_sync_')) {
        chmod($fixture,0777);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $file) if ($file->isDir()) chmod($file->getPathname(),0777);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($fixture);
    }
}
