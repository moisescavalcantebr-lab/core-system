<?php

declare(strict_types=1);

$rootCandidates = [
    '/var/www/html',
    dirname(__DIR__, 2),
];

$root = '';
foreach ($rootCandidates as $candidate) {
    if (is_file($candidate . '/env/env.production.php')) {
        $root = $candidate;
        break;
    }
}

if ($root === '') {
    $root = dirname(__DIR__, 2);
}

$envPath = $root . '/env/env.production.php';
if (!is_file($envPath)) {
    fwrite(STDERR, "ENV nao encontrado: {$envPath}" . PHP_EOL);
    exit(1);
}

$config = require $envPath;
$db = $config['db'] ?? [];

try {
    $pdo = new PDO(
        'mysql:host=' . ($db['host'] ?? 'db') . ';dbname=' . ($db['name'] ?? 'core') . ';charset=' . ($db['charset'] ?? 'utf8mb4'),
        (string)($db['user'] ?? 'root'),
        (string)($db['pass'] ?? 'root'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $settings = $pdo->query("SHOW TABLES LIKE 'core_settings'")->fetchColumn();

    if ($database !== 'core' || $settings !== 'core_settings') {
        fwrite(STDERR, 'Core incompleto: database=' . (string)$database . ' core_settings=' . (string)$settings . PHP_EOL);
        exit(1);
    }

    echo 'Core OK: ' . $database . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Core ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

