<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/services/mail/MailSchemaMigration.php';
try {
    $config = require dirname(__DIR__, 2) . '/env/env.production.php';
    $db = $config['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $migration=new MailSchemaMigration($pdo);
    $apply=in_array('--apply',$argv,true);
    if(in_array('--organization',$argv,true))$result=$migration->migrateOrganization($apply);
    elseif(in_array('--pilot',$argv,true))$result=$migration->migratePilot($apply);
    else $result=$migration->migrate($apply);
    echo $result.PHP_EOL;} catch (Throwable $error) {
    fwrite(STDERR, 'Migração interrompida. Confira pré-requisitos e checksum com o responsável técnico; detalhes sensíveis omitidos.' . PHP_EOL);
    exit(1);
}
