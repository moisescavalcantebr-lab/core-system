<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$name = trim((string)($_POST['name'] ?? ''));
$nickname = trim((string)($_POST['nickname'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$username = strtolower(trim((string)($_POST['username'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'pending'], true) ? (string)$_POST['status'] : 'active';
$whatsapp = trim((string)($_POST['whatsapp'] ?? '')) ?: null;
$notes = trim((string)($_POST['notes'] ?? '')) ?: null;

try {
    $birthDate = participantNormalizeBirthDate($_POST['birth_date'] ?? null);
} catch (InvalidArgumentException $e) {
    exit($e->getMessage());
}

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Nome e e-mail sao obrigatorios.');
}

if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
    exit('Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
}

if ($password === '' || strlen($password) < 4) {
    exit('Senha obrigatoria com no minimo 4 caracteres.');
}

if (($nicknameError = participantValidateNickname($nickname)) !== null) {
    exit($nicknameError);
}

$stmt = $pdo->prepare("SELECT id FROM project_users WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute([$email, $username]);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    exit('Ja existe um usuario com este e-mail ou usuario.');
}

$pdo->beginTransaction();

try {
    $userStatus = $status === 'active' ? 'active' : 'inactive';
    $stmt = $pdo->prepare("
        INSERT INTO project_users (name, email, username, password, must_change_password, role, status)
        VALUES (?, ?, ?, ?, 0, 'CLIENT', ?)
    ");
    $stmt->execute([$name, $email, $username, password_hash($password, PASSWORD_DEFAULT), $userStatus]);
    $userId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO participants (user_id, name, nickname, whatsapp, birth_date, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $name, $nickname ?: null, $whatsapp, $birthDate, $status, $notes]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('Nao foi possivel cadastrar o registro agora.');
}

flash('success', participantLabel() . ' cadastrado com sucesso.');
redirect(participantAdminUrl());
