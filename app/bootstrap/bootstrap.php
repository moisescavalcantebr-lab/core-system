<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CORE BOOTSTRAP
|--------------------------------------------------------------------------
*/

session_start();

/*
|--------------------------------------------------------------------------
| Carregar ENV
|--------------------------------------------------------------------------
*/

$envPath = __DIR__ . '/../../env/env.production.php';

if (!file_exists($envPath)) {
    die('ENV não configurado.');
}

$config = require $envPath;

/*
|--------------------------------------------------------------------------
| Constantes globais
|--------------------------------------------------------------------------
*/

define('ROOT_PATH', realpath(__DIR__ . '/../..'));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/web');

define('BASES_PATH', ROOT_PATH . '/bases');
define('PROJECTS_PATH', ROOT_PATH . '/projects');
define('STORAGE_PATH', ROOT_PATH . '/storage');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

require APP_PATH . '/helpers/csrf.php';
require APP_PATH . '/helpers/flash.php';
require APP_PATH . '/helpers/http.php';
require APP_PATH . '/helpers/ui_helper.php';
require APP_PATH . '/helpers/ui_registry/registry_helper.php';
require APP_PATH . '/helpers/url_helper.php';


/*
|--------------------------------------------------------------------------
| Autoload 
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    $baseDir = APP_PATH;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir)
    );

    foreach ($iterator as $file) {
        if ($file->getFilename() === $class . '.php') {
            require $file->getPathname();
            return;
        }
    }
});

/*
|--------------------------------------------------------------------------
| Conexão PDO
|--------------------------------------------------------------------------
*/

try {

    $db = $config['db'];

    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
]);

} catch (Throwable $e) {

    die('Erro de conexão com banco.');

}

/*
|--------------------------------------------------------------------------
| CORE SETTINGS
|--------------------------------------------------------------------------
*/

$settingsService = new SettingsService($pdo);

$coreSettings = [
    'app_name' => $settingsService->get('app_name', 'CORE'),
    'app_logo' => $settingsService->get('app_logo'),
    'theme' => $settingsService->get('theme', 'light'),
];

/* =========================
THEME GLOBAL
========================= */

$theme = $coreSettings['theme'] ?? 'light';

/*
|--------------------------------------------------------------------------
| Detectar Projeto Ativo
|--------------------------------------------------------------------------
*/

$projectId = $_SESSION['project_id'] ?? null;

if ($projectId) {

    /*
    |--------------------------------------------------------------------------
    | Buscar projeto
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id, slug
        FROM projects
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$projectId]);

    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {

        /*
        |--------------------------------------------------------------------------
        | Definir constantes do projeto
        |--------------------------------------------------------------------------
        */

        define('PROJECT_ID', (int)$project['id']);

        define(
            'PROJECT_PATH',
            PROJECTS_PATH . '/' . $project['slug']
        );

        /*
        |--------------------------------------------------------------------------
        | Module Loader
        |--------------------------------------------------------------------------
        */

      //  $moduleLoader = new ModuleLoader($pdo, PROJECT_ID);

      //  $moduleLoader->load();

    }

}