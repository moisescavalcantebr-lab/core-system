<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../partidas/lineup_helpers.php';

requireProjectAuth();
matchLineupEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$matchId = (int)($_GET['id'] ?? 0);
$status = ($_POST['status'] ?? '') === 'declined' ? 'declined' : 'confirmed';
$user = projectUser();

$stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([(int)$user['id']]);
$playerId = (int)($stmt->fetchColumn() ?: 0);

if ($matchId <= 0 || $playerId <= 0) {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

$stmt = $pdo->prepare("
    SELECT m.id, m.match_fee, c.context AS competition_context
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
      AND m.status IN ('scheduled','live')
    LIMIT 1
");
$stmt->execute([$matchId]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    flash('error', 'Partida indisponivel para confirmacao.');
    redirect(PROJECT_URL . '/admin/player/partidas.php');
}

if ($status === 'confirmed' && (float)($match['match_fee'] ?? 0) > 0) {
    $stmt = $pdo->prepare("
        SELECT payment_status
        FROM match_confirmations
        WHERE match_id = ? AND player_id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId, $playerId]);

    if ((string)($stmt->fetchColumn() ?: '') !== 'paid') {
        flash('error', 'Pague o valor da partida antes de confirmar presenca.');
        redirect(PROJECT_URL . '/admin/player/partidas.php');
    }
}

$stmt = $pdo->prepare("
    INSERT INTO match_confirmations (match_id, player_id, status, confirmed_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE status = VALUES(status), confirmed_at = NOW()
");
$stmt->execute([$matchId, $playerId, $status]);

$stmt = $pdo->prepare("
    INSERT INTO match_confirmation_logs (match_id, player_id, status)
    VALUES (?, ?, ?)
");
$stmt->execute([$matchId, $playerId, $status]);

if ($status === 'declined') {
    $stmt = $pdo->prepare("DELETE FROM match_lineup WHERE match_id = ? AND player_id = ?");
    $stmt->execute([$matchId, $playerId]);
} else {
    if (($match['competition_context'] ?? '') === 'internal') {
        matchLineupAutoAssignInternal($pdo, $matchId);
    } else {
        matchLineupAssignConfirmedPlayer($pdo, $matchId, $playerId);
    }
}

flash('success', $status === 'confirmed' ? 'Presenca confirmada.' : 'Resposta registrada.');
redirect(PROJECT_URL . '/admin/player/partidas.php');
