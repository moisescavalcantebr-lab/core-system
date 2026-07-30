<?php

declare(strict_types=1);

set_exception_handler(static function (Throwable $exception): void {
    echo '[core-install-error] ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL;
    echo '[core-install-error] ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL;
    exit(1);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array((int)$error['type'], $fatalTypes, true)) {
        return;
    }

    echo '[core-install-fatal] ' . (string)$error['message'] . PHP_EOL;
    echo '[core-install-fatal] ' . (string)$error['file'] . ':' . (string)$error['line'] . PHP_EOL;
});

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
installCoreLog('conexao mysql root OK');
$serverPdo->exec('CREATE DATABASE IF NOT EXISTS core CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
installCoreLog('banco core pronto');

$corePdo = installCorePdo('core');
installCoreLog('conexao core OK');
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
    'app' => [
        'environment' => 'production',
        'laboratory_enabled' => false,
        'allow_laboratory_in_production' => false,
    ],
    'db' => [
        'host' => 'db',
        'name' => 'core',
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8mb4',
    ],
    'project_db' => [
        'host' => 'db',
        'user' => 'core_project',
        'pass' => 'core_project',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'provider' => 'smtp',
        'host' => 'smtppro.zoho.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => '',
        'password' => '',
        'from' => 'Meu Projeto <no-reply@meuprojetoweb.com>',
        'reply_to' => '',
        'timeout' => 15,
    ],
    'api_key' => 'core_server_local',
];

if (is_file($envPath)) {
    $existingConfig = require $envPath;
    if (is_array($existingConfig)) {
        $config = array_replace_recursive($config, $existingConfig);
    }
}

$currentApiKey = trim((string)($config['api_key'] ?? ''));
if ($currentApiKey === '' || $currentApiKey === 'core_server_local' || str_contains($currentApiKey, 'TROQUE_')) {
    $config['api_key'] = 'core_' . bin2hex(random_bytes(24));
}

$projectDbUser = 'core_project';
$projectDbPass = 'core_project';
$config['project_db']['host'] = 'db';
$config['project_db']['user'] = $projectDbUser;
$config['project_db']['pass'] = $projectDbPass;
$config['project_db']['charset'] = 'utf8mb4';
unset(
    $config['project_db']['admin_host'],
    $config['project_db']['admin_user'],
    $config['project_db']['admin_pass'],
    $config['project_db']['admin_charset']
);

if ($projectDbUser !== '' && $projectDbUser !== 'root') {
    $quotedProjectDbUser = $serverPdo->quote($projectDbUser);
    $quotedProjectDbPass = $serverPdo->quote($projectDbPass);

    installCoreLog('preparando usuario de projetos');
    $serverPdo->exec("CREATE USER IF NOT EXISTS {$quotedProjectDbUser}@'%' IDENTIFIED BY {$quotedProjectDbPass}");
    $serverPdo->exec("CREATE USER IF NOT EXISTS {$quotedProjectDbUser}@'localhost' IDENTIFIED BY {$quotedProjectDbPass}");
    installCoreLog('usuario de projetos criado');
    $serverPdo->exec("ALTER USER {$quotedProjectDbUser}@'%' IDENTIFIED BY {$quotedProjectDbPass}");
    $serverPdo->exec("ALTER USER {$quotedProjectDbUser}@'localhost' IDENTIFIED BY {$quotedProjectDbPass}");
    installCoreLog('senha do usuario de projetos atualizada');
    $serverPdo->exec("GRANT ALL PRIVILEGES ON `project\\_%`.* TO {$quotedProjectDbUser}@'%'");
    $serverPdo->exec("GRANT ALL PRIVILEGES ON `project\\_%`.* TO {$quotedProjectDbUser}@'localhost'");
    installCoreLog('permissoes project_* aplicadas');
    $serverPdo->exec('FLUSH PRIVILEGES');
    installCoreLog('usuario de projetos pronto');
}

$envContents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
if (file_put_contents($envPath, $envContents) === false) {
    fwrite(STDERR, "Nao foi possivel gravar env: {$envPath}" . PHP_EOL);
    exit(1);
}

installCoreLog('env.production.php pronto');

$pdo = installCorePdo('core');
$database = $pdo->query('SELECT DATABASE()')->fetchColumn();
installCoreLog('PDO OK ' . (string)$database);
