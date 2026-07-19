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

$id = (int)($_GET['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$nickname = trim((string)($_POST['nickname'] ?? ''));
$username = strtolower(trim((string)($_POST['username'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$removeUserLink = !empty($_POST['remove_user_link']);

if ($id <= 0 || $name === '') {
    exit('Dados invalidos');
}

if (($nicknameError = playerValidateNickname($nickname)) !== null) {
    exit($nicknameError);
}

$stmt = $pdo->prepare("SELECT * FROM players WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$currentPlayer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentPlayer) {
    exit('Jogador nao encontrado');
}

$shirtNumber = $_POST['shirt_number'] !== '' ? (int)$_POST['shirt_number'] : null;
$positionId = $_POST['position_id'] !== '' ? (int)$_POST['position_id'] : null;
$secondaryPositionId = null;
$rosterStatus = in_array($_POST['roster_status'] ?? '', ['titular', 'reserva'], true) ? (string)$_POST['roster_status'] : 'titular';
$birthDate = $_POST['birth_date'] !== '' ? $_POST['birth_date'] : null;
$dominantFoot = $_POST['dominant_foot'] !== '' ? $_POST['dominant_foot'] : null;
$status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';
$whatsapp = trim((string)($_POST['whatsapp'] ?? '')) ?: null;
$targetRole = playerAccessFeatureEnabled() && !empty($_POST['promote_finance']) ? 'FINANCE' : 'PLAYER';
$accessAllowed = playerAccessFeatureEnabled();
$accessRequested = $accessAllowed && !$removeUserLink && !empty($_POST['player_access_enabled']);
$currentUserId = !empty($currentPlayer['user_id']) ? (int)$currentPlayer['user_id'] : null;
$userId = ($accessRequested || $removeUserLink) ? $currentUserId : null;
$avatarPath = (string)($currentPlayer['avatar'] ?? '');

if ($status !== 'active') {
    $positionId = null;
    $shirtNumber = null;
} elseif ($positionId === null) {
    exit('Posicao obrigatoria.');
} elseif ($shirtNumber === null) {
    exit('Numero da camisa obrigatorio.');
}

$shirtError = playerValidateShirtNumber($pdo, $shirtNumber, $id, $status);
if ($shirtError !== null) {
    exit($shirtError);
}

$validationError = playerValidateActiveRoster($pdo, $status, $positionId, $id);
if ($validationError !== null) {
    exit($validationError);
}

if (!$accessRequested && !empty($currentPlayer['user_id'])) {
    $stmt = $pdo->prepare("UPDATE project_users SET status = 'inactive' WHERE id = ? AND role IN ('PLAYER', 'FINANCE')");
    $stmt->execute([(int)$currentPlayer['user_id']]);
}

if ($removeUserLink) {
    $userId = $currentUserId;
} elseif (!$accessRequested) {
    $userId = null;
} elseif ($userId) {
    $stmt = $pdo->prepare("SELECT role, email, username, password, status FROM project_users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['role' => 'PLAYER'];

    $username = $username !== '' ? $username : (string)($currentUser['username'] ?? '');
    $emailForUser = (string)($currentUser['email'] ?? '') ?: $username . '@local.player';

    if ($username !== '' && !preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
        exit('Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
    }

    $currentRole = (string)($currentUser['role'] ?? 'PLAYER');
    $currentUserStatus = (string)($currentUser['status'] ?? '');
    $isActivatingUser = $currentUserStatus !== 'active' && $status === 'active';
    $isChangingUsername = $username !== '' && $username !== (string)($currentUser['username'] ?? '');

    if (($isActivatingUser || $isChangingUsername) && ($password === '' || !password_verify($password, (string)($currentUser['password'] ?? '')))) {
        exit('Senha do usuario existente invalida.');
    }

    if (in_array($currentRole, ['PLAYER', 'FINANCE'], true) && $username !== '' && $password !== '') {
        $stmt = $pdo->prepare("UPDATE project_users SET name = ?, email = ?, username = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $emailForUser, $username, password_hash($password, PASSWORD_DEFAULT), $userId]);
    } elseif (in_array($currentRole, ['PLAYER', 'FINANCE'], true) && $username !== '') {
        $stmt = $pdo->prepare("UPDATE project_users SET name = ?, email = ?, username = ? WHERE id = ?");
        $stmt->execute([$name, $emailForUser, $username, $userId]);
    } elseif (in_array($currentRole, ['PLAYER', 'FINANCE'], true) && $password !== '') {
        $stmt = $pdo->prepare("UPDATE project_users SET name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $emailForUser, password_hash($password, PASSWORD_DEFAULT), $userId]);
    } elseif (in_array($currentRole, ['PLAYER', 'FINANCE'], true)) {
        $stmt = $pdo->prepare("UPDATE project_users SET name = ?, email = ? WHERE id = ?");
        $stmt->execute([$name, $emailForUser, $userId]);
    }

    if (in_array($currentRole, ['PLAYER', 'FINANCE'], true)) {
        $stmt = $pdo->prepare("UPDATE project_users SET role = ?, status = ? WHERE id = ?");
        $stmt->execute([$targetRole, $status, $userId]);
    }
} else {
    if ($username === '') {
        $username = strtolower(preg_replace('/[^a-z0-9._-]+/', '-', trim($name)) ?? '');
        $username = trim($username, '.-_');
        if ($username === '') {
            exit('Usuario obrigatorio');
        }
    }

    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
        exit('Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
    }

    $emailForUser = $username . '@local.player';

    $stmt = $pdo->prepare("SELECT id, password, role FROM project_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        $userId = (int)$existingUser['id'];

        $stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? AND id <> ? LIMIT 1");
        $stmt->execute([$userId, $id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            exit('Este usuario ja esta vinculado a outro jogador.');
        }

        $existingRole = (string)($existingUser['role'] ?? '');

        if (!in_array($existingRole, ['ADMIN', 'PLAYER', 'FINANCE'], true)) {
            exit('Este usuario pertence a outro perfil do sistema.');
        }

        if ($password === '' || !password_verify($password, (string)$existingUser['password'])) {
            exit('Senha do usuario existente invalida.');
        }

        if (in_array($existingRole, ['PLAYER', 'FINANCE'], true)) {
            $stmt = $pdo->prepare("UPDATE project_users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $emailForUser, $userId]);
        }

        if (in_array($existingRole, ['PLAYER', 'FINANCE'], true)) {
            $stmt = $pdo->prepare("UPDATE project_users SET role = ?, status = ? WHERE id = ?");
            $stmt->execute([$targetRole, $status, $userId]);
        }
    } else {
        $password = $password !== '' ? $password : '1234';

        $stmt = $pdo->prepare("
            INSERT INTO project_users (name, email, username, password, must_change_password, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $mustChangePassword = $password === '1234' ? 1 : 0;
        $stmt->execute([$name, $emailForUser, $username, password_hash($password, PASSWORD_DEFAULT), $mustChangePassword, $targetRole, $status]);
        $userId = (int)$pdo->lastInsertId();
    }
}

$participantId = playerSyncParticipant(
    $pdo,
    isset($currentPlayer['participant_id']) ? (int)$currentPlayer['participant_id'] : null,
    $userId,
    $name,
    $nickname,
    $whatsapp,
    $birthDate,
    $status,
    trim((string)($_POST['notes'] ?? '')) ?: null
);

try {
    if (!empty($_POST['remove_avatar'])) {
        playerDeleteAvatarFile($avatarPath);
        $avatarPath = '';
    }

    $uploadedAvatar = !empty($_FILES['avatar']) ? playerUploadAvatar($_FILES['avatar'], $id) : null;
    if ($uploadedAvatar !== null) {
        playerDeleteAvatarFile($avatarPath);
        $avatarPath = $uploadedAvatar;
    }
} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
    redirect(PROJECT_URL . '/admin/jogadores/edit.php?id=' . $id);
}

$stmt = $pdo->prepare("
    UPDATE players
    SET participant_id = ?,
        user_id = ?,
        name = ?,
        nickname = ?,
        avatar = ?,
        whatsapp = ?,
        position_id = ?,
        secondary_position_id = ?,
        roster_status = ?,
        shirt_number = ?,
        birth_date = ?,
        dominant_foot = ?,
        status = ?,
        notes = ?
    WHERE id = ?
");

$stmt->execute([
    $participantId,
    $userId,
    $name,
    playerNicknameValue($nickname, $name),
    $avatarPath !== '' ? $avatarPath : null,
    $whatsapp,
    $positionId,
    $secondaryPositionId,
    $rosterStatus,
    $shirtNumber,
    $birthDate,
    $dominantFoot,
    $status,
    trim((string)($_POST['notes'] ?? '')) ?: null,
    $id,
]);

if ($userId !== null) {
    $stmt = $pdo->prepare("UPDATE project_users SET avatar = ? WHERE id = ?");
    $stmt->execute([$avatarPath !== '' ? $avatarPath : null, $userId]);
}

header('Location: ' . PROJECT_URL . '/admin/jogadores/index.php');
exit;
