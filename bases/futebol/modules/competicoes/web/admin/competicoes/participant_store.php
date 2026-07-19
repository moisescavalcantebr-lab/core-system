<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$competitionId = (int)($_GET['id'] ?? 0);
$participantType = in_array($_POST['participant_type'] ?? '', ['team', 'player', 'internal_team'], true) ? $_POST['participant_type'] : 'team';
$playerId = !empty($_POST['player_id']) ? (int)$_POST['player_id'] : null;
$name = trim((string)($_POST['name'] ?? ''));

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$competitionId]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    flash('error', 'Competicao nao encontrada.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$context = (string)($competition['context'] ?? 'external');

if ($context === 'external') {
    $participantType = 'team';
    $playerId = null;
}

if ($context === 'internal' && !in_array($participantType, ['player', 'internal_team'], true)) {
    $participantType = 'player';
}

if ($participantType === 'player' && $playerId) {
    $stmt = $pdo->prepare("SELECT name FROM players WHERE id = ? LIMIT 1");
    $stmt->execute([$playerId]);
    $name = (string)($stmt->fetchColumn() ?: $name);
}

if ($competitionId <= 0 || $name === '') {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$stmt = $pdo->prepare("
    INSERT INTO competition_participants (competition_id, participant_type, player_id, name)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$competitionId, $participantType, $playerId, $name]);

flash('success', 'Participante adicionado.');
redirect(PROJECT_URL . '/admin/competicoes/participants.php?id=' . $competitionId);
