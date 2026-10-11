<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/helpers/marketing_identity.php';
try {
    $config = require dirname(__DIR__, 2).'/env/env.production.php';
    $db = $config['db'];
    $pdo = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset='.($db['charset'] ?? 'utf8mb4'), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $apply = in_array('--apply', $argv, true);
    $id = marketingRenameIdentity($pdo, $apply);
    echo ($apply ? 'Aplicado' : 'Simulacao').': Marketing / Landing Pages; base ID '.($id ?? 'ainda nao cadastrada').".\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migracao interrompida: '.$e->getMessage()."\n");
    exit(1);
}
