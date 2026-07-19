<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/admin/participantes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

if (!participantPublicRegistrationEnabled()) {
    flash('error', 'Cadastro nao liberado no momento.');
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

$name = trim((string)($_POST['name'] ?? ''));
$nickname = trim((string)($_POST['nickname'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$username = strtolower(trim((string)($_POST['username'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$whatsapp = trim((string)($_POST['whatsapp'] ?? '')) ?: null;

try {
    $birthDate = participantNormalizeBirthDate($_POST['birth_date'] ?? null);
} catch (InvalidArgumentException $e) {
    flash('error', $e->getMessage());
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Informe nome e e-mail validos.');
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
    flash('error', 'Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

if ($password === '' || strlen($password) < 4) {
    flash('error', 'Senha obrigatoria com no minimo 4 caracteres.');
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

if (($nicknameError = participantValidateNickname($nickname)) !== null) {
    flash('error', $nicknameError);
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

$stmt = $pdo->prepare("SELECT id FROM project_users WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute([$email, $username]);

if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    flash('error', 'Ja existe um cadastro iniciado com este e-mail ou usuario. Confira seu acesso ou fale com o administrador.');
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO project_users (name, email, username, password, must_change_password, role, status)
        VALUES (?, ?, ?, ?, 0, 'CLIENT', 'active')
    ");
    $stmt->execute([$name, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO participants (user_id, name, nickname, whatsapp, birth_date, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$userId, $name, $nickname ?: null, $whatsapp, $birthDate]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Nao foi possivel enviar o cadastro agora.');
    redirect(PROJECT_URL . '/cadastro-participante.php');
}

flash('success', 'Cadastro enviado. Agora voce ja pode acessar com seu usuario e senha.');
redirect(PROJECT_URL . '/admin/login.php');
