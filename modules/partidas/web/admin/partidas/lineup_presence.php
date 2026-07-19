<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$matchId = (int)($_GET['id'] ?? 0);
$playerId = (int)($_POST['player_id'] ?? 0);
$presence = (string)($_POST['presence'] ?? '');

if ($matchId <= 0 || $playerId <= 0 || !in_array($presence, ['confirmed', 'declined'], true)) {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

$stmt = $pdo->prepare("
    SELECT p.id
    FROM players p
    WHERE p.id = ?
      AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$playerId]);

if (!$stmt->fetchColumn()) {
    flash('error', 'Jogador invalido para esta partida.');
    redirect(PROJECT_URL . '/admin/partidas/lineup.php?id=' . $matchId);
}

$stmt = $pdo->prepare("
    INSERT INTO match_confirmations (match_id, player_id, status, payment_status, confirmed_at)
    VALUES (?, ?, ?, 'not_required', NOW())
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        payment_status = VALUES(payment_status),
        confirmed_at = IF(VALUES(status) = 'confirmed', NOW(), confirmed_at),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$matchId, $playerId, $presence]);

if ($presence === 'declined') {
    $stmt = $pdo->prepare("DELETE FROM match_lineup WHERE match_id = ? AND player_id = ?");
    $stmt->execute([$matchId, $playerId]);
    flash('success', 'Jogador marcado fora desta partida.');
} else {
    matchLineupAssignConfirmedPlayer($pdo, $matchId, $playerId, null, false);
    flash('success', 'Jogador confirmado nesta partida.');
}

redirect(PROJECT_URL . '/admin/partidas/lineup.php?id=' . $matchId);
