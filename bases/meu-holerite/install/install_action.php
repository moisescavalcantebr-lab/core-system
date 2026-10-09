<?php
declare(strict_types=1);

session_start();

define('PROJECT_PATH', realpath(__DIR__ . '/..'));

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (!$token || $password !== $passwordConfirm) {
    die('Dados inválidos.');
}

// Conectar ao CORE
$coreEnv = require PROJECT_PATH . '/../env/env.production.php';

$corePdo = new PDO(
    "mysql:host={$coreEnv['db']['host']};dbname={$coreEnv['db']['name']}",
    $coreEnv['db']['user'],
    $coreEnv['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Validar token novamente
$stmt = $corePdo->prepare("
    SELECT * FROM project_install_tokens
    WHERE token = :token AND used = 0
    LIMIT 1
");
$stmt->execute(['token' => $token]);
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    die('Token inválido.');
}

// Conectar banco do projeto
$dbConfig = require PROJECT_PATH . '/app/config/database.php';

$projectPdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']}",
    $dbConfig['user'],
    $dbConfig['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Executar schema.sql
$schema = file_get_contents(PROJECT_PATH . '/app/database/schema.sql');
$projectPdo->exec($schema);

// Buscar dados do projeto no CORE
$projectStmt = $corePdo->prepare("
    SELECT * FROM projects WHERE id = :id
");
$projectStmt->execute(['id' => $tokenData['project_id']]);
$project = $projectStmt->fetch(PDO::FETCH_ASSOC);

// Criar ADMIN (único)
$stmt = $projectPdo->prepare("
    INSERT INTO project_users (name, email, password, role)
    VALUES (:name, :email, :password, 'ADMIN')
");
$stmt->execute([
    'name' => $project['owner_name'],
    'email' => $project['owner_email'],
    'password' => password_hash($password, PASSWORD_DEFAULT),
]);

$corePdo->prepare("
    INSERT INTO project_logs (project_id, action, message)
    VALUES (:project_id, 'install_completed', 'Instalação finalizada com sucesso.')
")->execute([
    'project_id' => $tokenData['project_id']
]);

// Marcar token como usado
$corePdo->prepare("
    UPDATE project_install_tokens
    SET used = 1
    WHERE id = :id
")->execute(['id' => $tokenData['id']]);

// Criar install.lock
file_put_contents(PROJECT_PATH . '/install/install.lock', date('c'));

header('Location: /public/admin/login.php');
exit;
