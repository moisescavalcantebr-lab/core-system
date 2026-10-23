<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root=dirname(__DIR__);
require $root.'/app/bootstrap/bootstrap.php';
require $root.'/app/services/projects/ProjectProvisioner.php';
if (!coreIsLocal() || !in_array($config['db']['host'],['db','localhost','127.0.0.1'],true)) throw new RuntimeException('Atualizacao somente local.');
$slug=$argv[1]??'';
if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new RuntimeException('Slug invalido.');
$stmt=$pdo->prepare("SELECT p.id FROM projects p JOIN bases b ON b.id=p.base_id WHERE p.slug=? AND p.status='active' AND b.slug='cripto'");$stmt->execute([$slug]);
if(!$stmt->fetchColumn())throw new RuntimeException('Projeto Cripto ativo nao encontrado.');
$project=$root.'/projects/'.$slug;
$db=require $project.'/app/config/database.php';
if($db['host']!==$config['db']['host'] || $db['name']!==ProjectProvisioner::projectDatabaseName($slug))throw new RuntimeException('Banco inesperado.');
$target=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$source=$root.'/modules/crypto_binance';
foreach(['database/schema.sql','module.json'] as $file){
    $to=$project.'/modules/crypto_binance/'.$file;
    if(!is_dir(dirname($to)))mkdir(dirname($to),0755,true);
    if(!copy($source.'/'.$file,$to))throw new RuntimeException('Falha ao copiar manifesto/schema.');
}
foreach(glob($source.'/web/admin/crypto_binance/*.php') as $file){
    foreach(['/web/admin/crypto_binance/','/modules/crypto_binance/web/admin/crypto_binance/'] as $dir){
        if(!is_dir($project.$dir))mkdir($project.$dir,0755,true);
        if(!copy($file,$project.$dir.basename($file)))throw new RuntimeException('Falha ao copiar rota do addon.');
    }
}
foreach (['web/admin/login_action.php','app/helpers/crypto_local_setup.php'] as $authFile) {
    if (!copy($root.'/bases/cripto/'.$authFile, $project.'/'.$authFile)) throw new RuntimeException('Falha ao atualizar vinculo de autenticacao.');
}
$target->exec((string)file_get_contents($source.'/database/schema.sql'));
echo "PASS: addon Binance local atualizado; tabelas existentes e historico preservados.\n";
