<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/admin/jogadores/positions_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

if (!playerAccessFeatureEnabled()) {
    flash('error', 'Cadastro de jogador fica disponível a partir do Plano Start.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

if (getSetting('player_public_registration_enabled', '0') !== '1') {
    flash('error', 'Cadastro não liberado no momento.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

playerEnsureDefaultPositions($pdo);

$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'active'")->fetchColumn();
if ($activeCount >= playerActiveLimit()) {
    flash('error', 'Cadastro indisponível no momento. Não há posições disponíveis.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

$name = trim((string)($_POST['name'] ?? ''));
$nickname = trim((string)($_POST['nickname'] ?? ''));
$username = strtolower(trim((string)($_POST['username'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$whatsapp = trim((string)($_POST['whatsapp'] ?? '')) ?: null;
$positionId = $_POST['position_id'] !== '' ? (int)$_POST['position_id'] : null;
$secondaryPositionId = null;
$shirtNumber = $_POST['shirt_number'] !== '' ? (int)$_POST['shirt_number'] : null;
$birthDate = $_POST['birth_date'] !== '' ? $_POST['birth_date'] : null;
$dominantFoot = $_POST['dominant_foot'] !== '' ? $_POST['dominant_foot'] : null;

if ($name === '') {
    flash('error', 'Nome obrigatorio.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

if (($nicknameError = playerValidateNickname($nickname)) !== null) {
    flash('error', $nicknameError);
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
    flash('error', 'Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

if (strlen($password) < 4) {
    flash('error', 'A senha precisa ter pelo menos 4 caracteres.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

if ($positionId === null) {
    flash('error', 'Escolha uma subposicao disponivel.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

$availablePositionIds = array_map('intval', array_column(playerAvailablePositions($pdo), 'id'));
if (!in_array($positionId, $availablePositionIds, true)) {
    flash('error', 'Esta subposicao nao esta mais disponivel. Escolha outra.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

$shirtError = playerValidateShirtNumber($pdo, $shirtNumber, null, 'active');
if ($shirtNumber === null) {
    flash('error', 'Escolha um numero de camisa disponivel.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

if ($shirtError !== null) {
    flash('error', $shirtError);
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

$validationError = playerValidateActiveRoster($pdo, 'active', $positionId);
if ($validationError !== null) {
    flash('error', $validationError);
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

$emailForUser = $username . '@local.player';

$stmt = $pdo->prepare("SELECT id FROM project_users WHERE username = ? OR email = ? LIMIT 1");
$stmt->execute([$username, $emailForUser]);
if ($stmt->fetchColumn()) {
    flash('error', 'Este usuario ja existe. Escolha outro.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO project_users (name, email, username, password, must_change_password, role, status)
        VALUES (?, ?, ?, ?, ?, 'PLAYER', 'active')
    ");
    $stmt->execute([
        $name,
        $emailForUser,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $password === '1234' ? 1 : 0,
    ]);

    $userId = (int)$pdo->lastInsertId();

    $participantId = playerSyncParticipant(
        $pdo,
        null,
        $userId,
        $name,
        $nickname,
        $whatsapp,
        $birthDate,
        'active',
        'Acesso criado pelo formulario publico.'
    );

    $stmt = $pdo->prepare("
        INSERT INTO players (participant_id, user_id, name, nickname, whatsapp, position_id, secondary_position_id, shirt_number, birth_date, dominant_foot, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
    ");
    $stmt->execute([
        $participantId,
        $userId,
        $name,
        playerNicknameValue($nickname, $name),
        $whatsapp,
        $positionId,
        $secondaryPositionId,
        $shirtNumber,
        $birthDate,
        $dominantFoot,
        'Acesso criado pelo formulario publico.',
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Nao foi possivel enviar o cadastro agora.');
    redirect(PROJECT_URL . '/cadastro-jogador.php');
}

flash('success', 'Cadastro concluido. Entre com seu usuario e senha.');
redirect(PROJECT_URL . '/admin/login.php');
