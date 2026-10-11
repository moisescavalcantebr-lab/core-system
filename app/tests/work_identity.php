<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/services/projects/WorkIdentityMigration.php';
require dirname(__DIR__).'/helpers/base_manifest.php';
function workCheck(bool $value, string $message): void
{
    if (!$value) throw new LogicException($message);
}
function workCopy(string $source, string $target): void
{
    if (is_dir($source)) {
        if (!is_dir($target)) mkdir($target,0755,true);
        foreach (scandir($source) ?: [] as $name) if ($name !== '.' && $name !== '..') workCopy($source.'/'.$name,$target.'/'.$name);
    } else {
        if (!is_dir(dirname($target))) mkdir(dirname($target),0755,true);
        if (!copy($source,$target)) throw new RuntimeException('Falha ao preparar fixture.');
    }
}
function workRemoveFixture(string $root): void
{
    $real = realpath($root);
    $tmp = realpath(dirname(__DIR__,2).'/tmp');
    if (!$real || !$tmp || dirname($real) !== $tmp || !preg_match('/^work-identity-test-[a-f0-9]{10}$/D',basename($real))) throw new RuntimeException('Limpeza fora da fixture recusada.');
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        if ($item->isLink()) throw new RuntimeException('Link inesperado na fixture.');
        if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname());
    }
    rmdir($real);
}
if (getenv('WORK_IDENTITY_MYSQL_TEST') !== '1') { echo "Use WORK_IDENTITY_MYSQL_TEST=1 para executar em bancos temporarios.\n"; exit; }
$token = bin2hex(random_bytes(5));
$coreName = 'work_core_test_'.$token;
$projectName = 'work_project_test_'.$token;
$root = dirname(__DIR__,2).'/tmp/work-identity-test-'.$token;
$server = new PDO('mysql:host=app_db;charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
mkdir($root,0755,true);
try {
    $server->exec('CREATE DATABASE `'.$coreName.'`');
    $server->exec('CREATE DATABASE `'.$projectName.'`');
    $core = new PDO('mysql:host=app_db;dbname='.$coreName.';charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db = new PDO('mysql:host=app_db;dbname='.$projectName.';charset=utf8mb4','root','root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $core->exec("CREATE TABLE bases (id INT PRIMARY KEY,slug VARCHAR(80) UNIQUE,name VARCHAR(100)); CREATE TABLE projects (id INT PRIMARY KEY,base_id INT,slug VARCHAR(80) UNIQUE,name VARCHAR(100),path VARCHAR(200),status VARCHAR(20)); CREATE TABLE base_module_prices (id INT PRIMARY KEY,base_id INT,module_slug VARCHAR(80)); INSERT INTO bases VALUES (41,'meu-holerite','Meu Holerite'); INSERT INTO projects VALUES (71,41,'meu-holerite-01','Meu Holerite','/projects/meu-holerite-01','active'); INSERT INTO base_module_prices VALUES (91,41,'equatorial');");
    workCopy(dirname(__DIR__,2).'/bases/meu-trabalho',$root.'/bases/meu-trabalho');
    workCopy(dirname(__DIR__,2).'/modules/leituras',$root.'/modules/leituras');
    $source = $root.'/projects/meu-holerite-01';
    mkdir($source.'/app/config',0755,true);
    mkdir($source.'/app/database',0755,true);
    mkdir($source.'/modules/equatorial',0755,true);
    $config = ['host'=>'app_db','name'=>$projectName,'charset'=>'utf8mb4','user'=>'root','pass'=>'root'];
    file_put_contents($source.'/app/config/database.php','<?php return '.var_export($config,true).';');
    file_put_contents($source.'/project.json',json_encode(['id'=>71,'name'=>'Meu Holerite','slug'=>'meu-holerite-01','modules'=>['meu_holerite','equatorial'],'core_api_key'=>'fixture-only']));
    file_put_contents($source.'/.htaccess',"# preserved fixture\n");
    $oldSchema = str_replace('leituras_','equatorial_',file_get_contents(dirname(__DIR__,2).'/modules/leituras/database/schema.sql'));
    file_put_contents($source.'/app/database/schema.sql',$oldSchema);
    $db->exec($oldSchema);
    $db->exec("INSERT INTO equatorial_reasons (id,owner_user_id,number,location) VALUES (5,10,'22','Jaiara'); INSERT INTO equatorial_readings (id,owner_user_id,reason_id,reason_number,location,work_date,total,executed,sent,lis,leit) VALUES (6,10,5,'22','Jaiara','2026-09-01',500,480,480,400,100); INSERT INTO equatorial_workspaces VALUES (10,'2026-10'); INSERT INTO equatorial_periods VALUES (10,'2026-09','2026-09-30 20:00:00',4); CREATE TABLE project_settings (setting_key VARCHAR(80) PRIMARY KEY,setting_value TEXT); INSERT INTO project_settings VALUES ('site_name','Meu Holerite'),('dashboard_default_group','equatorial'); CREATE TABLE untouched_pdf (id INT PRIMARY KEY,pdf LONGBLOB); INSERT INTO untouched_pdf VALUES (1,'private-fixture');");
    $migration = new WorkIdentityMigration($core,$root);
    $migration->run();
    workCheck(is_dir($source) && !is_dir($root.'/projects/meu-trabalho'),'Simulacao nao deve mover arquivos.');
    $core->exec("INSERT INTO bases VALUES (42,'meu-trabalho','Conflito')");
    try { $migration->run(); throw new LogicException('Conflito aceito.'); } catch (RuntimeException $e) { workCheck(str_contains($e->getMessage(),'coexistem'),'Erro de conflito.'); }
    $core->exec('DELETE FROM bases WHERE id=42');
    $migration->run(true);
    $target = $root.'/projects/meu-trabalho';
    workCheck($core->query('SELECT slug FROM bases WHERE id=41')->fetchColumn()==='meu-trabalho','ID da base preservado.');
    workCheck($core->query('SELECT path FROM projects WHERE id=71')->fetchColumn()==='/projects/meu-trabalho','ID e caminho do projeto.');
    workCheck($core->query('SELECT module_slug FROM base_module_prices WHERE id=91')->fetchColumn()==='leituras','Vinculo comercial preservado.');
    workCheck((require $target.'/app/config/database.php')===$config,'Configuracao do banco intacta.');
    workCheck($db->query('SELECT total FROM leituras_readings WHERE id=6')->fetchColumn()===500,'Leitura preservada.');
    workCheck((int)$db->query('SELECT revision FROM leituras_periods')->fetchColumn()===4,'Arquivamento preservado.');
    workCheck($db->query('SELECT active_month FROM leituras_workspaces')->fetchColumn()==='2026-10','Mes de trabalho preservado.');
    workCheck($db->query('SELECT pdf FROM untouched_pdf')->fetchColumn()==='private-fixture','Dados de outros modulos intactos.');
    $meta = json_decode(file_get_contents($target.'/project.json'),true);
    workCheck($meta['modules']===['meu_holerite','leituras'] && $meta['core_api_key']==='fixture-only','Metadados nao relacionados preservados.');
    workCheck(str_contains(file_get_contents($source.'/web/.htaccess'),'R=308'),'Endereco anterior preservado.');
    workCheck(file_get_contents($target.'/.htaccess')==="# preserved fixture\n",'Manutencao encerrada.');
    workCheck(!str_contains(file_get_contents($target.'/app/database/schema.sql'),'equatorial_'),'Esquema do projeto atualizado.');
    $manifest = base_manifest_payload(['name'=>'Meu Trabalho','slug'=>'meu-trabalho'],$root.'/bases/meu-trabalho');
    workCheck($manifest['previous_slug']==='meu-holerite','Alias preservado ao regravar manifesto.');
    $migration->run(true);
    workCheck((int)$db->query('SELECT COUNT(*) FROM leituras_readings')->fetchColumn()===1,'Reexecucao sem duplicar dados.');
    // Simulate interruption after moving files, before updating the Core identity.
    file_put_contents($target.'/.identity-migration.json',json_encode(['project_id'=>71,'old_slug'=>'meu-holerite-01','htaccess'=>"# preserved fixture\n"]));
    file_put_contents($target.'/.htaccess',"RewriteEngine On\nRewriteRule ^ - [R=503,L]\n");
    unlink($source.'/.identity-alias'); unlink($source.'/web/.htaccess'); rmdir($source.'/web'); rmdir($source);
    $core->exec("UPDATE bases SET slug='meu-holerite' WHERE id=41; UPDATE projects SET slug='meu-holerite-01',path='/projects/meu-holerite-01' WHERE id=71; UPDATE base_module_prices SET module_slug='equatorial' WHERE id=91;");
    $migration->run(true);
    workCheck(file_get_contents($target.'/.htaccess')==="# preserved fixture\n" && !is_file($target.'/.identity-migration.json'),'Retomada restaura acesso original.');
    workCheck(is_file($source.'/web/.htaccess') && (int)$db->query('SELECT COUNT(*) FROM leituras_readings')->fetchColumn()===1,'Retomada preserva alias e dados.');
    echo "Meu Trabalho: simulacao, conflitos, IDs, vinculos, nomes, tabelas, arquivos privados, aliases e reexecucao OK.\n";
} finally {
    $server->exec('DROP DATABASE IF EXISTS `'.$projectName.'`');
    $server->exec('DROP DATABASE IF EXISTS `'.$coreName.'`');
    workRemoveFixture($root);
}
