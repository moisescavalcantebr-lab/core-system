<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__, 2).'/app/helpers/marketing_identity.php';
require dirname(__DIR__, 2).'/modules/landing_page/web/admin/landing_page/helpers.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function rejects(callable $operation): void
{
    try { $operation(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('Conflito nao foi rejeitado.');
}

$config = require dirname(__DIR__, 2).'/env/env.production.php';
$db = $config['db'];
$pdo = new PDO('mysql:host='.$db['host'].';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'test_marketing_identity_'.bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $pdo->exec('CREATE TABLE bases (id INT PRIMARY KEY, slug VARCHAR(120) UNIQUE, name VARCHAR(150)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE projects (id INT PRIMARY KEY, base_id INT, status VARCHAR(30)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE base_module_prices (id INT PRIMARY KEY, base_id INT, module_slug VARCHAR(120), monthly_price DECIMAL(10,2), UNIQUE(base_id,module_slug)) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE plan_bases (plan_id INT, base_id INT) ENGINE=InnoDB');
    $pdo->exec("INSERT INTO bases VALUES (7,'divulgacao','Base Divulgacao')");
    $pdo->exec("INSERT INTO base_module_prices VALUES (9,7,'divulgacao',29.90)");
    $pdo->exec('INSERT INTO plan_bases VALUES (3,7)');
    check(marketingRenameIdentity($pdo, false) === 7, 'ID na simulacao');
    check($pdo->query('SELECT slug FROM bases')->fetchColumn() === 'divulgacao', 'Simulacao alterou dados');
    check(marketingRenameIdentity($pdo) === 7, 'ID alterado');
    check($pdo->query('SELECT slug FROM bases')->fetchColumn() === 'marketing', 'Slug incorreto');
    check($pdo->query('SELECT base_id FROM plan_bases')->fetchColumn() == 7, 'Vinculo do plano perdido');
    $price = $pdo->query('SELECT * FROM base_module_prices')->fetch(PDO::FETCH_ASSOC);
    check($price['id'] == 9 && $price['module_slug'] === 'landing_page' && $price['monthly_price'] === '29.90', 'Preco perdido');
    marketingRenameIdentity($pdo);
    check($pdo->query('SELECT COUNT(*) FROM bases')->fetchColumn() == 1, 'Repeticao duplicou base');
    $pdo->exec("INSERT INTO bases VALUES (8,'divulgacao','Duplicada')");
    rejects(fn() => marketingRenameIdentity($pdo));
    $pdo->exec('DELETE FROM bases WHERE id=8');
    $pdo->exec("UPDATE bases SET slug='divulgacao'");
    $pdo->exec("INSERT INTO projects VALUES (1,7,'active')");
    rejects(fn() => marketingRenameIdentity($pdo));
    check($pdo->query('SELECT slug FROM bases')->fetchColumn() === 'divulgacao', 'Bloqueio alterou base');
    $pdo->exec('DELETE FROM projects');
    $pdo->exec("INSERT INTO base_module_prices VALUES (10,7,'divulgacao',10.00)");
    rejects(fn() => marketingRenameIdentity($pdo));
    check($pdo->query('SELECT slug FROM bases')->fetchColumn() === 'divulgacao', 'Conflito alterou base');
    $pdo->exec('DELETE FROM base_module_prices WHERE id=10');
    marketingRenameIdentity($pdo);

    define('PROJECT_PATH', dirname(__DIR__, 2).'/bases/marketing');
    define('PROJECT_URL', '/projects/test-marketing/web');
    landingPageEnsureSchema($pdo);
    $pdo->exec("INSERT INTO landing_page_pages (title,slug,headline,status) VALUES ('Teste','teste','Pagina teste','published')");
    $pdo->exec("INSERT INTO landing_page_leads (page_id,name,phone) VALUES (1,'Teste','000000000')");
    check(landingPagePublicUrl('teste') === PROJECT_URL.'/landing_page.php?slug=teste', 'URL antiga');
    check($pdo->query('SELECT COUNT(*) FROM landing_page_leads WHERE page_id=1')->fetchColumn() == 1, 'Lead nao associado');
    $moduleRoot = dirname(__DIR__, 2).'/modules/landing_page';
    $manifest = json_decode((string)file_get_contents($moduleRoot.'/module.json'), true, 512, JSON_THROW_ON_ERROR);
    check($manifest['name'] === 'landing_page' && $manifest['recommended_bases'] === ['marketing'], 'Manifesto incorreto');
    foreach ($manifest['copy'] as $copy) {
        check(file_exists($moduleRoot.'/'.$copy['from']), 'Origem ausente');
        check(file_exists(PROJECT_PATH.'/'.$copy['to']), 'Copia na base ausente');
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($moduleRoot));
        check(hash_file('sha256', $file->getPathname()) === hash_file('sha256', PROJECT_PATH.'/modules/landing_page'.$relative), 'Modulo da base divergente');
    }
    echo "OK: simulacao, ID, planos, precos, repeticao, conflitos, projetos, schema, leads, URLs e copias.\n";
} finally {
    $pdo->exec('DROP DATABASE `'.$database.'`');
}
