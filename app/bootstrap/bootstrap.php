<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CORE BOOTSTRAP
|--------------------------------------------------------------------------
*/

session_start();

header('Content-Type: text/html; charset=utf-8');
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

require_once APP_PATH . '/helpers/csrf.php';
require_once APP_PATH . '/helpers/flash.php';
require_once APP_PATH . '/helpers/environment.php';
require_once APP_PATH . '/helpers/http.php';
require_once APP_PATH . '/helpers/ui_helper.php';
require_once APP_PATH . '/helpers/url_helper.php';
require_once APP_PATH . '/helpers/form_renderer.php';
require_once APP_PATH . '/helpers/base_manifest.php';
require_once APP_PATH . '/helpers/core_wallet.php';

/*
|--------------------------------------------------------------------------
| Autoload 
|--------------------------------------------------------------------------
*/

spl_autoload_register(function ($class) {

    $baseDir = APP_PATH;

    try {
        $directory = new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $file): bool {
            return !$file->isDir() || $file->isReadable();
        });
        $iterator = new RecursiveIteratorIterator($filter);

        foreach ($iterator as $file) {
            if ($file->getFilename() === $class . '.php') {
                require $file->getPathname();
                return;
            }
        }
    } catch (UnexpectedValueException $e) {
        return;
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

try {
    $projectColumns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('deletion_requested_at', $projectColumns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN deletion_requested_at DATETIME NULL AFTER status");
    }

    if (!in_array('deletion_scheduled_at', $projectColumns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN deletion_scheduled_at DATETIME NULL AFTER deletion_requested_at");
    }

    if (!in_array('deletion_canceled_at', $projectColumns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN deletion_canceled_at DATETIME NULL AFTER deletion_scheduled_at");
    }

    if (!in_array('deleted_at', $projectColumns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN deleted_at DATETIME NULL AFTER deletion_canceled_at");
    }

    if (!in_array('plan_price_id', $projectColumns, true)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN plan_price_id INT UNSIGNED NULL AFTER plan_id");
        $pdo->exec("ALTER TABLE projects ADD KEY idx_plan_price (plan_price_id)");
    }
} catch (Throwable $e) {
    // Projetos pode não existir durante uma instalação inicial do core.
}

try {
    $leadColumns = $pdo->query("SHOW COLUMNS FROM leads")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('ip_address', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN ip_address VARCHAR(45) NULL AFTER base_id");
    }

    if (!in_array('user_agent', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN user_agent VARCHAR(500) NULL AFTER ip_address");
    }

    if (!in_array('referer', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN referer VARCHAR(500) NULL AFTER user_agent");
    }

    if (!in_array('content_campaign_key', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN content_campaign_key VARCHAR(120) NULL AFTER referer");
        $pdo->exec("ALTER TABLE leads ADD KEY idx_content_campaign_key (content_campaign_key)");
    }

    if (!in_array('content_source', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN content_source VARCHAR(120) NULL AFTER content_campaign_key");
        $pdo->exec("ALTER TABLE leads ADD KEY idx_content_source (content_source)");
    }

    if (!in_array('continue_token', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN continue_token VARCHAR(120) NULL AFTER content_source");
        $pdo->exec("ALTER TABLE leads ADD KEY idx_leads_continue_token (continue_token)");
    }

    if (!in_array('continue_expires_at', $leadColumns, true)) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN continue_expires_at DATETIME NULL AFTER continue_token");
    }
} catch (Throwable $e) {
    // Leads pode não existir durante uma instalação inicial do core.
}

try {
    $baseColumns = $pdo->query("SHOW COLUMNS FROM bases")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('showcase_title', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_title VARCHAR(150) NULL AFTER description");
    }

    if (!in_array('showcase_summary', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_summary TEXT NULL AFTER showcase_title");
    }

    if (!in_array('showcase_features', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_features TEXT NULL AFTER showcase_summary");
    }

    if (!in_array('showcase_cover_image', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_cover_image VARCHAR(255) NULL AFTER showcase_features");
    }

    if (!in_array('showcase_banner_image', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_banner_image VARCHAR(255) NULL AFTER showcase_cover_image");
    }

    if (!in_array('showcase_detail_url', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_detail_url VARCHAR(500) NULL AFTER showcase_banner_image");
    }

    if (!in_array('showcase_cta_text', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_cta_text VARCHAR(80) NULL AFTER showcase_detail_url");
    }

    if (!in_array('showcase_featured', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_featured TINYINT DEFAULT 0 AFTER showcase_cta_text");
    }

    if (!in_array('showcase_order', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_order INT DEFAULT 0 AFTER showcase_featured");
    }

    if (!in_array('showcase_status', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN showcase_status TINYINT DEFAULT 0 AFTER showcase_order");
    }

    if (!in_array('base_stage', $baseColumns, true)) {
        $pdo->exec("ALTER TABLE bases ADD COLUMN base_stage ENUM('laboratory','published','legacy','archived') NOT NULL DEFAULT 'laboratory' AFTER status");
        $pdo->exec("UPDATE bases SET base_stage = 'published' WHERE is_protected = 1");
    }

    $pdo->exec("ALTER TABLE bases ALTER COLUMN showcase_status SET DEFAULT 0");
} catch (Throwable $e) {
    // Bases pode não existir durante uma instalação inicial do core.
}

try {
    $planColumns = $pdo->query("SHOW COLUMNS FROM plans")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('limits_json', $planColumns, true)) {
        $pdo->exec("ALTER TABLE plans ADD COLUMN limits_json LONGTEXT NULL AFTER price");
    }
} catch (Throwable $e) {
    // Planos pode não existir durante uma instalação inicial do core.
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plan_bases (
            plan_id INT UNSIGNED NOT NULL,
            base_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (plan_id, base_id),
            KEY idx_plan_bases_base (base_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plan_prices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_id INT UNSIGNED NOT NULL,
            billing_cycle ENUM('free','monthly','annual') NOT NULL DEFAULT 'monthly',
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_plan_cycle (plan_id, billing_cycle),
            KEY idx_plan_prices_plan (plan_id),
            KEY idx_plan_prices_cycle (billing_cycle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS base_plan_prices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            base_id INT UNSIGNED NOT NULL,
            plan_price_id INT UNSIGNED NOT NULL,
            custom_price DECIMAL(10,2) NULL,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_base_plan_price (base_id, plan_price_id),
            KEY idx_base_plan_prices_base (base_id),
            KEY idx_base_plan_prices_price (plan_price_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS base_module_prices (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            base_id INT UNSIGNED NOT NULL,
            module_slug VARCHAR(120) NOT NULL,
            commercial_category ENUM('included','extra','coming_soon') DEFAULT 'extra',
            monthly_price DECIMAL(10,2) NULL,
            annual_price DECIMAL(10,2) NULL,
            settings_json LONGTEXT NULL,
            status TINYINT DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_base_module_price (base_id, module_slug),
            KEY idx_base_module_prices_base (base_id),
            KEY idx_base_module_prices_category (commercial_category),
            KEY idx_base_module_prices_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $modulePriceColumns = $pdo->query("SHOW COLUMNS FROM base_module_prices")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('commercial_category', $modulePriceColumns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN commercial_category ENUM('included','extra','coming_soon') DEFAULT 'extra' AFTER module_slug");
    }
    if (!in_array('monthly_price', $modulePriceColumns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN monthly_price DECIMAL(10,2) NULL AFTER commercial_category");
    }
    if (!in_array('annual_price', $modulePriceColumns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN annual_price DECIMAL(10,2) NULL AFTER monthly_price");
    }
    if (!in_array('settings_json', $modulePriceColumns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN settings_json LONGTEXT NULL AFTER annual_price");
    }
    if (!in_array('display_order', $modulePriceColumns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN display_order INT DEFAULT 0 AFTER status");
    }
    if (!in_array('updated_at', $modulePriceColumns, true)) {
        $pdo->exec("ALTER TABLE base_module_prices ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    $hasPlanCycleIndex = $pdo->query("SHOW INDEX FROM plan_prices WHERE Key_name = 'uniq_plan_cycle'")->fetchColumn();
    if (!$hasPlanCycleIndex) {
        $pdo->exec("ALTER TABLE plan_prices ADD UNIQUE KEY uniq_plan_cycle (plan_id, billing_cycle)");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plan_upgrade_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            plan_price_id INT UNSIGNED NULL,
            requested_by_name VARCHAR(150) NULL,
            requested_by_email VARCHAR(150) NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            receipt_path VARCHAR(255) NULL,
            requested_modules_json LONGTEXT NULL,
            notes TEXT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            reviewed_by_user_id INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            review_notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_upgrade_project (project_id),
            KEY idx_upgrade_plan (plan_id),
            KEY idx_upgrade_plan_price (plan_price_id),
            KEY idx_upgrade_status (status),
            KEY idx_upgrade_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $upgradeColumns = $pdo->query("SHOW COLUMNS FROM plan_upgrade_requests")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('plan_price_id', $upgradeColumns, true)) {
        $pdo->exec("ALTER TABLE plan_upgrade_requests ADD COLUMN plan_price_id INT UNSIGNED NULL AFTER plan_id");
        $pdo->exec("ALTER TABLE plan_upgrade_requests ADD KEY idx_upgrade_plan_price (plan_price_id)");
    }
    if (!in_array('requested_modules_json', $upgradeColumns, true)) {
        $pdo->exec("ALTER TABLE plan_upgrade_requests ADD COLUMN requested_modules_json LONGTEXT NULL AFTER receipt_path");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plan_refund_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            upgrade_request_id INT UNSIGNED NOT NULL,
            project_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            plan_price_id INT UNSIGNED NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            reason TEXT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            reviewed_by_user_id INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            review_notes TEXT NULL,
            sent_by_user_id INT UNSIGNED NULL,
            sent_at DATETIME NULL,
            sent_receipt_path VARCHAR(255) NULL,
            sent_notes TEXT NULL,
            requested_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_refund_upgrade (upgrade_request_id),
            KEY idx_refund_project (project_id),
            KEY idx_refund_status (status),
            KEY idx_refund_requested (requested_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $refundColumns = $pdo->query("SHOW COLUMNS FROM plan_refund_requests")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('sent_by_user_id', $refundColumns, true)) {
        $pdo->exec("ALTER TABLE plan_refund_requests ADD COLUMN sent_by_user_id INT UNSIGNED NULL AFTER review_notes");
    }
    if (!in_array('sent_at', $refundColumns, true)) {
        $pdo->exec("ALTER TABLE plan_refund_requests ADD COLUMN sent_at DATETIME NULL AFTER sent_by_user_id");
    }
    if (!in_array('sent_receipt_path', $refundColumns, true)) {
        $pdo->exec("ALTER TABLE plan_refund_requests ADD COLUMN sent_receipt_path VARCHAR(255) NULL AFTER sent_at");
    }
    if (!in_array('sent_notes', $refundColumns, true)) {
        $pdo->exec("ALTER TABLE plan_refund_requests ADD COLUMN sent_notes TEXT NULL AFTER sent_receipt_path");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_wallet_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            requested_by_name VARCHAR(150) NULL,
            requested_by_email VARCHAR(150) NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(40) NULL,
            receipt_path VARCHAR(255) NULL,
            notes TEXT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            reviewed_by_user_id INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            review_notes TEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wallet_request_project (project_id),
            KEY idx_wallet_request_status (status),
            KEY idx_wallet_request_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $walletRequestColumns = $pdo->query("SHOW COLUMNS FROM project_wallet_requests")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('payment_method', $walletRequestColumns, true)) {
        $pdo->exec("ALTER TABLE project_wallet_requests ADD COLUMN payment_method VARCHAR(40) NULL AFTER amount");
    }
    if (!in_array('receipt_path', $walletRequestColumns, true)) {
        $pdo->exec("ALTER TABLE project_wallet_requests ADD COLUMN receipt_path VARCHAR(255) NULL AFTER payment_method");
    }
    if (!in_array('review_notes', $walletRequestColumns, true)) {
        $pdo->exec("ALTER TABLE project_wallet_requests ADD COLUMN review_notes TEXT NULL AFTER reviewed_at");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS project_wallet_movements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            movement_type ENUM('credit','debit') NOT NULL,
            source ENUM('balance_request','upgrade','refund','manual_adjustment') NOT NULL DEFAULT 'manual_adjustment',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            description VARCHAR(255) NULL,
            reference_table VARCHAR(80) NULL,
            reference_id INT UNSIGNED NULL,
            status ENUM('applied','canceled') DEFAULT 'applied',
            created_by_user_id INT UNSIGNED NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wallet_movement_project (project_id),
            KEY idx_wallet_movement_source (source),
            KEY idx_wallet_movement_status (status),
            KEY idx_wallet_movement_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $walletMovementColumns = $pdo->query("SHOW COLUMNS FROM project_wallet_movements")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reference_table', $walletMovementColumns, true)) {
        $pdo->exec("ALTER TABLE project_wallet_movements ADD COLUMN reference_table VARCHAR(80) NULL AFTER description");
    }
    if (!in_array('reference_id', $walletMovementColumns, true)) {
        $pdo->exec("ALTER TABLE project_wallet_movements ADD COLUMN reference_id INT UNSIGNED NULL AFTER reference_table");
    }

    $pdo->exec("
        INSERT INTO base_plan_prices (base_id, plan_price_id, custom_price, status)
        SELECT b.id, pp.id, pp.price, 1
        FROM bases b
        INNER JOIN plans pl ON pl.billing_cycle = 'free' AND pl.status = 1
        INNER JOIN plan_prices pp ON pp.plan_id = pl.id AND pp.billing_cycle = 'free' AND pp.status = 1
        WHERE NOT EXISTS (
            SELECT 1
            FROM base_plan_prices bpp
            WHERE bpp.base_id = b.id
              AND bpp.plan_price_id = pp.id
        )
    ");

    base_register_manifests($pdo);
} catch (Throwable $e) {
    // Tabelas de planos podem não existir durante a primeira instalação.
}

/*
|--------------------------------------------------------------------------
| CORE SETTINGS
|--------------------------------------------------------------------------
*/

$settingsService = new SettingsService($pdo);

$coreSettings = [
    'app_name' => $settingsService->get('app_name', 'CORE'),
    'app_logo' => $settingsService->get('app_logo', 'meu_projeto_web_logo.svg'),
    'app_favicon' => $settingsService->get('app_favicon', 'meu_projeto_web_favicon.svg'),
    'theme' => $settingsService->get('theme', 'light'),
];

if (empty($coreSettings['app_logo']) && is_file(PUBLIC_PATH . '/assets/uploads/meu_projeto_web_logo.svg')) {
    $coreSettings['app_logo'] = 'meu_projeto_web_logo.svg';
}

if (empty($coreSettings['app_favicon']) && is_file(PUBLIC_PATH . '/assets/uploads/meu_projeto_web_favicon.svg')) {
    $coreSettings['app_favicon'] = 'meu_projeto_web_favicon.svg';
}

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
