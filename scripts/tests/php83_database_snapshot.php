<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$config = require dirname(__DIR__, 2) . '/env/env.production.php';
$db = $config['db'];
if (!in_array($db['host'], ['db', 'localhost', '127.0.0.1'], true)) { throw new RuntimeException('Local database only.'); }
$pdo = new PDO('mysql:host=' . $db['host'] . ';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $pdo->query("SELECT TABLE_SCHEMA,TABLE_NAME FROM information_schema.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA NOT IN ('mysql','sys','performance_schema','information_schema') ORDER BY TABLE_SCHEMA,TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
$snapshot = [];
foreach ($tables as $table) {
    $identifier = '`' . str_replace('`', '``', $table['TABLE_SCHEMA']) . '`.`' . str_replace('`', '``', $table['TABLE_NAME']) . '`';
    $row = $pdo->query('CHECKSUM TABLE ' . $identifier . ' EXTENDED')->fetch(PDO::FETCH_ASSOC);
    if (!isset($row['Checksum'])) { throw new RuntimeException('Table checksum unavailable.'); }
    $snapshot[$table['TABLE_SCHEMA'] . '.' . $table['TABLE_NAME']] = (string)$row['Checksum'];
}
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
