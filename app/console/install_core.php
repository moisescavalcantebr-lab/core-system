<?php

declare(strict_types=1);

$rootCandidates = [
    '/var/www/html',
    dirname(__DIR__, 2),
];

$root = '';
foreach ($rootCandidates as $candidate) {
    if (is_file($candidate . '/app/database/schema.sql')) {
        $root = $candidate;
        break;
    }
}

if ($root === '') {
    $root = dirname(__DIR__, 2);
}

$schemaPath = $root . '/app/database/schema.sql';
$envPath = $root . '/env/env.production.php';

function installCoreLog(string $message): void
{
    echo '[core-install] ' . $message . PHP_EOL;
}

function installCorePdo(?string $database = null): PDO
{
    $dsn = 'mysql:host=db;charset=utf8mb4';
    if ($database !== null && $database !== '') {
        $dsn = 'mysql:host=db;dbname=' . $database . ';charset=utf8mb4';
    }

    return new PDO($dsn, 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

if (!is_file($schemaPath)) {
    fwrite(STDERR, "Schema nao encontrado: {$schemaPath}" . PHP_EOL);
    exit(1);
}

$serverPdo = installCorePdo();
$serverPdo->exec('CREATE DATABASE IF NOT EXISTS core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
installCoreLog('banco core pronto');

$corePdo = installCorePdo('core');
$schema = file_get_contents($schemaPath);
if ($schema === false || trim($schema) === '') {
    fwrite(STDERR, "Schema vazio ou ilegivel: {$schemaPath}" . PHP_EOL);
    exit(1);
}

$corePdo->exec($schema);
installCoreLog('schema core importado');

$settingsTable = $corePdo->query("SHOW TABLES LIKE 'core_settings'")->fetchColumn();
if ($settingsTable !== 'core_settings') {
    fwrite(STDERR, 'Tabela core_settings nao foi criada.' . PHP_EOL);
    exit(1);
}

if (!is_dir(dirname($envPath))) {
    mkdir(dirname($envPath), 0775, true);
}

$config = [
    'app_name' => 'MEU PROJETO WEB',
    'app_url' => 'https://meuprojetoweb.com',
    'db' => [
        'host' => 'db',
        'name' => 'core',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
    ],
    'project_db' => [
        'host' => 'db',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'provider' => 'resend',
        'api_key' => '',
        'from' => 'Meu Projeto <no-reply@meuprojetoweb.com>',
    ],
    'api_key' => 'core_server_local',
];

$envContents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($envPath, $envContents) === false) {
    fwrite(STDERR, "Nao foi possivel gravar env: {$envPath}" . PHP_EOL);
    exit(1);
}

installCoreLog('env.production.php pronto');

$pdo = installCorePdo('core');
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
installCoreLog('PDO OK ' . (string)$database);
