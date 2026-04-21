<?php
declare(strict_types=1);

session_start();

define('PROJECT_PATH', realpath(__DIR__ . '/..'));
require PROJECT_PATH . '/app/config/database.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die('Token inválido.');
}

// Conectar ao CORE para validar token
$coreDb = require PROJECT_PATH . '/../env/env.production.php';
$corePdo = new PDO(
    "mysql:host={$coreDb['db']['host']};dbname={$coreDb['db']['name']}",
    $coreDb['db']['user'],
    $coreDb['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $corePdo->prepare("
    SELECT * FROM project_install_tokens
    WHERE token = :token AND used = 0
    LIMIT 1
");
$stmt->execute(['token' => $token]);
$tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenData) {
    die('Token inválido ou já utilizado.');
}

if (strtotime($tokenData['expires_at']) < time()) {
    die('Token expirado.');
}
?>

<h1>Finalizar instalação</h1>

<form method="post" action="file:///C|/Users/Moises Cavalcante/Meus Sites/old/bases/default/install/install_action.php">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

    <input name="password" type="password" placeholder="Crie sua senha" required>
    <input name="password_confirm" type="password" placeholder="Confirme sua senha" required>

    <button>Finalizar instalação</button>
</form>
