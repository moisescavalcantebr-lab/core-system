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
$overridePositionId = ($_POST['override_position_id'] ?? '') !== '' ? (int)$_POST['override_position_id'] : null;

if ($matchId <= 0 || $playerId <= 0) {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

$stmt = $pdo->prepare("
    INSERT INTO match_confirmations (match_id, player_id, status, payment_status, confirmed_at)
    VALUES (?, ?, 'confirmed', 'not_required', NOW())
    ON DUPLICATE KEY UPDATE
        status = 'confirmed',
        payment_status = 'not_required',
        confirmed_at = NOW(),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->execute([$matchId, $playerId]);

$stmt = $pdo->prepare("
    SELECT p.id, p.name, pp.name AS position_name, pp.code AS position_code, pp.group_key
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    INNER JOIN match_confirmations mc ON mc.player_id = p.id AND mc.match_id = ? AND mc.status = 'confirmed'
    WHERE p.id = ? AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$matchId, $playerId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    flash('error', 'Jogador nao confirmado para esta partida.');
    redirect(PROJECT_URL . '/admin/partidas/lineup.php?id=' . $matchId);
}

$status = matchLineupAssignConfirmedPlayer($pdo, $matchId, $playerId, $overridePositionId, true);

flash('success', $status === 'starter' ? 'Jogador adicionado ao campo.' : 'Jogador adicionado às reservas.');
redirect(PROJECT_URL . '/admin/partidas/lineup.php?id=' . $matchId);
