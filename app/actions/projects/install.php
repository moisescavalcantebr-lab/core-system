<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectInstaller.php';

requireAdmin();
csrf_verify();

/* =========================
   VALIDAR DADOS
========================= */

$id     = (int)($_POST['id'] ?? 0);
$dbName = trim($_POST['db_name'] ?? '');

if (!$id || !$dbName) {
    die('Dados inválidos.');
}

/* =========================
   BUSCAR PROJETO + BASE
========================= */

$stmt = $pdo->prepare("
    SELECT p.*, b.slug AS base_slug
    FROM projects p
    LEFT JOIN bases b ON b.id = p.base_id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die('Projeto não encontrado.');
}

if (empty($project['base_slug'])) {
    die('Base do projeto não encontrada.');
}

/* =========================
   CONFIG DO CORE (ENV)
========================= */

$coreConfig      = require ROOT_PATH . '/env/env.production.php';
$projectDbConfig = $coreConfig['project_db'];

$host    = $projectDbConfig['host'];
$user    = $projectDbConfig['user'];
$pass    = $projectDbConfig['pass'];
$charset = $projectDbConfig['charset'];

/* =========================
   TESTAR CONEXÃO
========================= */

try {

    $dsn = "mysql:host={$host};dbname={$dbName};charset={$charset}";
    $projectPdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

} catch (Throwable $e) {
    die('Erro ao conectar no banco. Verifique se o banco foi criado.');
}

/* =========================
   EXECUTAR SCHEMA DA BASE
========================= */

$schemaPath = BASES_PATH . '/' . $project['base_slug'] . '/app/database/schema.sql';

if (!file_exists($schemaPath)) {
    die('Schema da base não encontrado.');
}

$schemaSql = file_get_contents($schemaPath);

try {
    $projectPdo->exec($schemaSql);
} catch (Throwable $e) {
    die('Erro ao executar schema: ' . $e->getMessage());
}

/* =========================
   CRIAR database.php
========================= */

$configContent = "<?php
return [
    'host' => '{$host}',
    'name' => '{$dbName}',
    'user' => '{$user}',
    'pass' => '{$pass}',
    'charset' => '{$charset}'
];";

$configPath = PROJECTS_PATH . '/' . $project['slug'] . '/app/config/database.php';

if (!is_dir(dirname($configPath))) {
    mkdir(dirname($configPath), 0755, true);
}

file_put_contents($configPath, $configContent);

/* =========================
   GARANTIR ESTRUTURA DE PASTAS
========================= */

$projectRoot = PROJECTS_PATH . '/' . $project['slug'];

$directories = [
    $projectRoot . '/storage/uploads',
    $projectRoot . '/storage/uploads/images',
    $projectRoot . '/storage/uploads/avatars',
    $projectRoot . '/storage/uploads/temp'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

/* =========================
   ATIVAR PROJETO (TRANSACIONAL)
========================= */

try {

    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE projects
        SET 
            status = 'active',
            billing_status = 'active',
            path = :path
        WHERE id = :id
    ")->execute([
        'path' => '/projects/' . $project['slug'],
        'id'   => $id
    ]);

    /* =========================
       SINCRONIZAR JSON
    ========================= */

    ProjectInstaller::syncFromDatabase($pdo, $id);

    /* =========================
       LOG
    ========================= */

    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (:project_id, 'installed', :message, 'info')
    ")->execute([
        'project_id' => $id,
        'message'    => "Projeto instalado com banco '{$dbName}'"
    ]);

    $pdo->commit();

} catch (Throwable $e) {

    $pdo->rollBack();
    die('Erro ao ativar projeto: ' . $e->getMessage());
}

header("Location: public/admin/projects/view.php?id={$id}");
exit;