<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$name = trim((string)($_POST['name'] ?? ''));
$nickname = trim((string)($_POST['nickname'] ?? ''));
$username = strtolower(trim((string)($_POST['username'] ?? '')));
$password = (string)($_POST['password'] ?? '1234');

if ($name === '') {
    exit('Nome obrigatorio');
}

if (($nicknameError = playerValidateNickname($nickname)) !== null) {
    exit($nicknameError);
}

$shirtNumber = $_POST['shirt_number'] !== '' ? (int)$_POST['shirt_number'] : null;
$positionId = $_POST['position_id'] !== '' ? (int)$_POST['position_id'] : null;
$secondaryPositionId = null;
$rosterStatus = in_array($_POST['roster_status'] ?? '', ['titular', 'reserva'], true) ? (string)$_POST['roster_status'] : 'titular';
$birthDate = $_POST['birth_date'] !== '' ? $_POST['birth_date'] : null;
$dominantFoot = $_POST['dominant_foot'] !== '' ? $_POST['dominant_foot'] : null;
$status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'inactive';
$whatsapp = trim((string)($_POST['whatsapp'] ?? '')) ?: null;
$targetRole = playerAccessFeatureEnabled() && !empty($_POST['promote_finance']) ? 'FINANCE' : 'PLAYER';
$userId = null;
$accessAllowed = playerAccessFeatureEnabled();
$accessRequested = $accessAllowed && !empty($_POST['player_access_enabled']);

if ($status !== 'active') {
    $positionId = null;
    $shirtNumber = null;
} elseif ($positionId === null) {
    exit('Posicao obrigatoria.');
} elseif ($shirtNumber === null) {
    exit('Numero da camisa obrigatorio.');
}

$shirtError = playerValidateShirtNumber($pdo, $shirtNumber, null, $status);
if ($shirtError !== null) {
    exit($shirtError);
}

$validationError = playerValidateActiveRoster($pdo, $status, $positionId);
if ($validationError !== null) {
    exit($validationError);
}

$existingUser = null;

if (!$accessRequested) {
    $username = '';
    $password = '';
} elseif ($accessRequested) {
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
        exit('Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
    }

    $stmt = $pdo->prepare("SELECT id, password, role, email, username FROM project_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

$emailForUser = $username !== '' ? $username . '@local.player' : null;

if (!$accessRequested) {
    $userId = null;
} elseif ($existingUser) {
    $userId = (int)$existingUser['id'];

    $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        exit('Este usuario ja esta vinculado a um jogador.');
    }

    $existingRole = (string)($existingUser['role'] ?? '');

    if (!in_array($existingRole, ['ADMIN', 'PLAYER', 'FINANCE'], true)) {
        exit('Este usuario pertence a outro perfil do sistema.');
    }

    if ($password === '' || !password_verify($password, (string)$existingUser['password'])) {
        exit('Senha do usuario existente invalida.');
    }

    if (in_array($existingRole, ['PLAYER', 'FINANCE'], true)) {
        $stmt = $pdo->prepare("UPDATE project_users SET name = ?, email = ?, username = ? WHERE id = ?");
        $stmt->execute([$name, $emailForUser, $username, $userId]);
    }

    if ($existingRole === 'PLAYER') {
        $stmt = $pdo->prepare("UPDATE project_users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$targetRole, $status, $userId]);
    } elseif ($existingRole === 'FINANCE' && $targetRole === 'PLAYER') {
        $stmt = $pdo->prepare("UPDATE project_users SET role = 'PLAYER', status = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
    }
} elseif ($accessRequested) {
    $password = $password !== '' ? $password : '1234';

    $stmt = $pdo->prepare("
        INSERT INTO project_users (name, email, username, password, must_change_password, role, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $mustChangePassword = $password === '1234' ? 1 : 0;
    $stmt->execute([$name, $emailForUser, $username, password_hash($password, PASSWORD_DEFAULT), $mustChangePassword, $targetRole, $status]);
    $userId = (int)$pdo->lastInsertId();
}

$participantId = playerSyncParticipant(
    $pdo,
    null,
    $userId,
    $name,
    $nickname,
    $whatsapp,
    $birthDate,
    $status,
    trim((string)($_POST['notes'] ?? '')) ?: null
);

$stmt = $pdo->prepare("
    INSERT INTO players (participant_id, user_id, name, nickname, avatar, whatsapp, position_id, secondary_position_id, roster_status, shirt_number, birth_date, dominant_foot, status, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $participantId,
    $userId,
    $name,
    playerNicknameValue($nickname, $name),
    null,
    $whatsapp,
    $positionId,
    $secondaryPositionId,
    $rosterStatus,
    $shirtNumber,
    $birthDate,
    $dominantFoot,
    $status,
    trim((string)($_POST['notes'] ?? '')) ?: null,
]);

$playerId = (int)$pdo->lastInsertId();

try {
    $avatarPath = !empty($_FILES['avatar']) ? playerUploadAvatar($_FILES['avatar'], $playerId) : null;
    if ($avatarPath !== null) {
        $stmt = $pdo->prepare("UPDATE players SET avatar = ? WHERE id = ?");
        $stmt->execute([$avatarPath, $playerId]);

        if ($userId !== null) {
            $stmt = $pdo->prepare("UPDATE project_users SET avatar = ? WHERE id = ?");
            $stmt->execute([$avatarPath, $userId]);
        }
    }
} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
    redirect(PROJECT_URL . '/admin/jogadores/edit.php?id=' . $playerId);
}

header('Location: ' . PROJECT_URL . '/admin/jogadores/index.php');
exit;
