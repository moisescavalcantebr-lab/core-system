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

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM participants WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$participant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participant) {
    exit('Participante nao encontrado.');
}

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

if (($nicknameError = participantValidateNickname($nickname)) !== null) {
    exit($nicknameError);
}

$userId = (int)($participant['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id FROM project_users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1");
$stmt->execute([$email, $username, $userId]);
if ($stmt->fetch(PDO::FETCH_ASSOC)) {
    exit('Ja existe outro usuario com este e-mail ou usuario.');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        UPDATE participants
        SET name = ?, nickname = ?, whatsapp = ?, birth_date = ?, status = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $nickname ?: null, $whatsapp, $birthDate, $status, $notes, $id]);

    if ($userId > 0) {
        $userStatus = $status === 'active' ? 'active' : 'inactive';
        $sql = "UPDATE project_users SET name = ?, email = ?, username = ?, status = ?";
        $params = [$name, $email, $username, $userStatus];

        if ($password !== '') {
            if (strlen($password) < 4) {
                exit('Senha deve ter no minimo 4 caracteres.');
            }

            $sql .= ", password = ?, must_change_password = 0";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $userId;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('Nao foi possivel atualizar o registro agora.');
}

flash('success', participantLabel() . ' atualizado.');
redirect(participantAdminUrl());
