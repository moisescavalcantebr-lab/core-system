<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/services/projects/WorkIdentityMigration.php';
$root = dirname(__DIR__,2);
try {
    $config = require $root.'/env/env.production.php';
    $db = $config['db'];
    $pdo = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset='.($db['charset'] ?? 'utf8mb4'),$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $apply = in_array('--apply',$argv,true);
    if (!$apply) echo "Simulacao: use --apply para aplicar.\n";
    foreach ((new WorkIdentityMigration($pdo,$root))->run($apply) as $message) echo $message."\n";
} catch (Throwable $e) {
    fwrite(STDERR,'Migracao interrompida: '.$e->getMessage()."\n");
    exit(1);
}
