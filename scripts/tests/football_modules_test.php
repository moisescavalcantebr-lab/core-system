<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') exit(1);
$root=dirname(__DIR__,2);$base=$root.'/bases/futebol';
function footballCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$config=require $root.'/env/env.production.php';$db=$config['db'];
$server=new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='test_football_modules_'.bin2hex(random_bytes(5));$server->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo=new PDO('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec((string)file_get_contents($base.'/app/database/schema.sql'));
    $modules=[];
    foreach (glob($base.'/modules/*/module.json') as $path) {
        $manifest=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        $slug=basename(dirname($path));$modules[$slug]=$manifest;
        $schema=$manifest['database']['schema'] ?? '';
        if ($schema!=='') $pdo->exec((string)file_get_contents(dirname($path).'/'.$schema));
    }
    footballCheck(count($modules)===8&&isset($modules['classificacao'])&&!isset($modules['placar_ao_vivo']),'Lista de modulos mudou');
    footballCheck(($modules['treino']['menu'] ?? null)===null&&$modules['treino']['status']==='deprecated','Treino antigo permanece ativo');
    footballCheck($modules['participantes']['participant_access']==='optional','Politica de Futebol perdida');
    foreach ($modules as $slug=>$manifest) {
        foreach ($manifest['attach_to'] ?? [] as $parent) footballCheck(isset($modules[$parent]),'Addon sem modulo pai: '.$slug);
        foreach ($manifest['database']['tables'] ?? [] as $table) {
            $stmt=$pdo->prepare('SHOW TABLES LIKE ?');$stmt->execute([$table]);footballCheck((bool)$stmt->fetchColumn(),'Tabela ausente: '.$table);
        }
        $catalog=$root.'/modules/'.$slug;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($catalog,FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative=substr($file->getPathname(),strlen($catalog)+1);
            if ($relative==='module.json'||$relative==='database/schema.sql') continue;
            footballCheck(hash_file('sha256',$file->getPathname())===hash_file('sha256',$base.'/modules/'.$slug.'/'.$relative),'Codigo desatualizado: '.$slug.'/'.$relative);
        }
        foreach ($manifest['copy'] ?? [] as $copy) {
            $source=$base.'/modules/'.$slug.'/'.$copy['from'];$target=$base.'/'.$copy['to'];
            if (is_file($source)) { footballCheck(hash_file('sha256',$source)===hash_file('sha256',$target),'Rota desatualizada: '.$copy['to']); continue; }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS)) as $file) {
                $relative=substr($file->getPathname(),strlen($source)+1);
                footballCheck(hash_file('sha256',$file->getPathname())===hash_file('sha256',$target.'/'.$relative),'Rota desatualizada: '.$slug.'/'.$relative);
            }
        }
    }
    $pdo->exec((string)file_get_contents($base.'/modules/treino/database/schema.sql'));
    $pdo->exec("INSERT INTO participants (id,name,status) VALUES (101,'Pessoa de teste','active'); INSERT INTO players (id,participant_id,name,status) VALUES (101,101,'Atleta de teste','active'); INSERT INTO team_roster (id,player_id) VALUES (101,101); INSERT INTO training_roster (id,player_id) VALUES (101,101); INSERT INTO competitions (id,name) VALUES (101,'Competicao de teste'); INSERT INTO matches (id,competition_id,participant_a,participant_b) VALUES (101,101,'Meu Time','Visitante'); INSERT INTO finance_entries (id,type,title,amount) VALUES (101,'income','Contribuicao teste',10.00)");
    $tables=['participants','players','team_profile','team_roster','training_roster','competitions','matches','finance_entries'];
    $before=[];foreach ($tables as $table) $before[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec("UPDATE finance_settings SET setting_value='esportivo' WHERE setting_key='finance_mode'");
    foreach ($modules as $slug=>$manifest) {
        $schema=$manifest['database']['schema'] ?? '';
        if ($schema!=='') $pdo->exec((string)file_get_contents($base.'/modules/'.$slug.'/'.$schema));
    }
    foreach ($tables as $table) footballCheck($before[$table]===$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Schema alterou dados: '.$table);
    footballCheck($pdo->query("SELECT setting_value FROM finance_settings WHERE setting_key='finance_mode'")->fetchColumn()==='esportivo','Configuracao financeira alterada');
    define('PROJECT_PATH',$base);
    require $base.'/app/helpers/settings.php';
    require $base.'/web/admin/financeiro/helpers.php';
    footballCheck(count(financeCategoryOptions($pdo))>0,'Categorias do Financeiro indisponiveis');
    echo "PASS: modulos de Futebol e rotas alinhados, Classificacao instalada, Treino antigo desativado, schemas idempotentes e dados esportivos/financeiros/legados preservados.\n";
} finally { $server->exec('DROP DATABASE IF EXISTS `'.$database.'`'); }
