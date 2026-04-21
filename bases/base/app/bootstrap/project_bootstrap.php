<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // PRIMEIRO

/*
|--------------------------------------------------------------------------
| PATHS
|--------------------------------------------------------------------------
*/

define('PROJECT_PATH', dirname(__DIR__, 2));
define('APP_PATH', PROJECT_PATH . '/app');
define('PUBLIC_PATH', PROJECT_PATH . '/public');

/* STORAGE agora público */
define('STORAGE_PATH', PUBLIC_PATH . '/storage');

/* URL do projeto */
define('PROJECT_URL', '/projects/' . basename(PROJECT_PATH) . '/public');

/*
|--------------------------------------------------------------------------
| INSTALAÇÃO
|--------------------------------------------------------------------------
*/

$configPath = APP_PATH . '/config/database.php';

if (!file_exists($configPath)) {
    http_response_code(503);
    die('Projeto não instalado.');
}

/*
|--------------------------------------------------------------------------
| PROJECT CONFIG (mantido, mas simples)
|--------------------------------------------------------------------------
*/

$project = [];

$projectJson = PROJECT_PATH . '/project.json';

if (file_exists($projectJson)) {
    $project = json_decode(file_get_contents($projectJson), true) ?? [];
}

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

$dbConfig = require $configPath;

try {

    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";

    $pdo = new PDO(
        $dsn,
        $dbConfig['user'],
        $dbConfig['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

} catch (Throwable $e) {

    http_response_code(500);
    die('Erro de conexão com banco.');

}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

require_once APP_PATH . '/helpers/settings.php';
require_once APP_PATH . '/helpers/project_auth.php';
require_once APP_PATH . '/helpers/flash.php';
require_once APP_PATH . '/helpers/csrf.php';
require_once APP_PATH . '/helpers/http.php';
require_once APP_PATH . '/helpers/media.php';
/*
|--------------------------------------------------------------------------
| ADMIN CHECK (PRIMEIRO ACESSO)
|--------------------------------------------------------------------------
*/

try {

    $count = $pdo->query("
        SELECT COUNT(*) 
        FROM project_users 
        WHERE role = 'ADMIN'
    ")->fetchColumn();

    $currentFile = basename($_SERVER['PHP_SELF']);

    if ($count == 0 && $currentFile !== 'create-password.php') {
        header('Location: create-password.php');
        exit;
    }

} catch (Throwable $e) {

    http_response_code(500);
    die('Erro na estrutura do banco.');

}