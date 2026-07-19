<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

requireProjectAdmin();

if (!function_exists('projectModuleProvides') || !projectModuleProvides('advanced_live')) {
    flash('error', 'Modulo ao vivo ainda nao esta liberado para este projeto.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

csrf_verify();
matchLineupEnsureSchema($pdo);

$matchId = (int)($_POST['match_id'] ?? 0);
$playerId = (int)($_POST['player_id'] ?? 0);

$matchStmt = $pdo->prepare("
    SELECT m.*, c.context AS competition_context
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
    LIMIT 1
");
$matchStmt->execute([$matchId]);
$match = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$match || $playerId <= 0) {
    flash('error', 'Partida ou jogador invalido.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

if (($match['competition_context'] ?? '') === 'internal') {
    flash('error', 'Ao vivo sera liberado primeiro para partidas externas.');
    redirect(PROJECT_URL . '/admin/partidas/show.php?id=' . $matchId);
}

$lineupStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM match_lineup
    WHERE match_id = ?
      AND player_id = ?
      AND status = 'starter'
      AND COALESCE(lineup_team, 'team_1') = 'team_1'
");
$lineupStmt->execute([$matchId, $playerId]);

if ((int)$lineupStmt->fetchColumn() === 0) {
    flash('error', 'Selecione um jogador titular desta partida.');
    redirect(PROJECT_URL . '/admin/partidas/ao_vivo.php?id=' . $matchId);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS live_match_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        match_id INT NOT NULL,
        player_id INT NOT NULL,
        event_type ENUM('goal') NOT NULL DEFAULT 'goal',
        team_key ENUM('team_1','team_2') NOT NULL DEFAULT 'team_1',
        event_minute INT NULL,
        notes TEXT NULL,
        created_by_user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_live_match_events_match (match_id),
        INDEX idx_live_match_events_player (player_id),
        INDEX idx_live_match_events_type (event_type),
        INDEX idx_live_match_events_team (team_key),
        INDEX idx_live_match_events_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$userId = (int)($_SESSION['project_user']['id'] ?? $_SESSION['user']['id'] ?? 0);

$pdo->beginTransaction();
try {
    $eventStmt = $pdo->prepare("
        INSERT INTO live_match_events (match_id, player_id, event_type, team_key, created_by_user_id)
        VALUES (?, ?, 'goal', 'team_1', ?)
    ");
    $eventStmt->execute([$matchId, $playerId, $userId > 0 ? $userId : null]);

    $scoreStmt = $pdo->prepare("
        UPDATE matches
        SET score_a = COALESCE(score_a, 0) + 1,
            score_b = COALESCE(score_b, 0)
        WHERE id = ?
    ");
    $scoreStmt->execute([$matchId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Nao foi possivel registrar o gol.');
    redirect(PROJECT_URL . '/admin/partidas/ao_vivo.php?id=' . $matchId . '&player_id=' . $playerId);
}

redirect(PROJECT_URL . '/admin/partidas/ao_vivo.php?id=' . $matchId . '&player_id=' . $playerId . '&saved=goal');
