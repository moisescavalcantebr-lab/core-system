<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$competitionId = (int)($_POST['competition_id'] ?? 0);
$participantA = trim((string)($_POST['participant_a'] ?? ''));
$participantB = trim((string)($_POST['participant_b'] ?? ''));
$status = in_array($_POST['status'] ?? '', ['scheduled', 'live', 'finished', 'canceled'], true) ? $_POST['status'] : 'scheduled';
$lineupMode = in_array($_POST['lineup_mode'] ?? '', ['team_roster', 'arrival_order', 'automatic'], true) ? $_POST['lineup_mode'] : 'team_roster';
$matchDate = trim((string)($_POST['match_date'] ?? '')) ?: null;
$matchFee = max(0, (float)str_replace(',', '.', (string)($_POST['match_fee'] ?? '0')));
$competitionContext = 'external';
$competitionType = '';

if (!projectPlanAllows('finance_enabled', true)) {
    $matchFee = 0.0;
}

if ($participantA === '' || $participantB === '') {
    flash('error', 'Informe os participantes da partida.');
    redirect($competitionId > 0 ? PROJECT_URL . '/admin/partidas/create.php?competition_id=' . $competitionId : PROJECT_URL . '/admin/partidas/create.php');
}

if ($matchDate === null) {
    flash('error', 'Informe a data e hora da partida.');
    redirect($competitionId > 0 ? PROJECT_URL . '/admin/partidas/create.php?competition_id=' . $competitionId : PROJECT_URL . '/admin/partidas/create.php');
}

if ($competitionId > 0) {
    $stmt = $pdo->prepare("SELECT status, context, type FROM competitions WHERE id = ? LIMIT 1");
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $competitionStatus = (string)($competition['status'] ?? '');
    $competitionContext = (string)($competition['context'] ?? 'external');
    $competitionType = (string)($competition['type'] ?? '');

    if (in_array($competitionStatus, ['finished', 'canceled'], true)) {
        flash('error', 'Esta competicao esta finalizada ou cancelada e nao permite novas partidas.');
        redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId);
    }

    if ($competitionContext === 'internal') {
        $lineupMode = 'automatic';
    } elseif ($lineupMode === 'automatic') {
        $lineupMode = 'team_roster';
    }
} elseif ($lineupMode === 'automatic') {
    $lineupMode = 'team_roster';
}

if ($status === 'live') {
    if (!projectPlanAllows('live_match_enabled', false)) {
        flash('error', projectPlanName() . ' nao permite partida ao vivo.');
        redirect($competitionId > 0 ? PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId : PROJECT_URL . '/admin/partidas/index.php');
    }

    $liveLimit = projectPlanLimit('live_matches_open', 1);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE status = 'live'");
    $stmt->execute();

    if ((int)$stmt->fetchColumn() >= $liveLimit) {
        flash('error', 'Limite de ' . $liveLimit . ' partida ao vivo atingido neste plano.');
        redirect($competitionId > 0 ? PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId : PROJECT_URL . '/admin/partidas/index.php');
    }
}

if ($status === 'scheduled') {
    $scheduledLimit = projectPlanLimit('matches_scheduled', 4);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE status = 'scheduled'");
    $stmt->execute();

    if ((int)$stmt->fetchColumn() >= $scheduledLimit) {
        flash('error', 'Limite de ' . $scheduledLimit . ' partidas agendadas atingido neste plano. Finalize ou cancele uma partida para criar outra.');
        redirect($competitionId > 0 ? PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId : PROJECT_URL . '/admin/partidas/index.php');
    }
}

if ($matchDate !== null) {
    $matchDate = str_replace('T', ' ', $matchDate);
}

$stmt = $pdo->prepare("
    INSERT INTO matches (competition_id, participant_a, participant_b, match_date, venue, round_name, status, lineup_mode, match_fee, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $competitionId > 0 ? $competitionId : null,
    $participantA,
    $participantB,
    $matchDate,
    trim((string)($_POST['venue'] ?? '')) ?: null,
    trim((string)($_POST['round_name'] ?? '')) ?: null,
    $status,
    $lineupMode,
    $matchFee,
    trim((string)($_POST['notes'] ?? '')) ?: null,
]);

$matchId = (int)$pdo->lastInsertId();

if ($competitionContext === 'internal' && $competitionType === 'training') {
    matchLineupSaveTrainingFieldSnapshot($pdo, $matchId, true);
    matchLineupSyncTrainingSnapshot($pdo, $matchId);
} else {
    matchLineupSaveFieldSnapshot($pdo, $matchId, true);
    matchLineupSyncTeamRosterSnapshot($pdo, $matchId);
}

if ($status === 'finished') {
    if ($competitionContext === 'internal' && $competitionType === 'training') {
        matchLineupSaveTrainingFieldSnapshot($pdo, $matchId);
    } else {
        matchLineupSaveFieldSnapshot($pdo, $matchId);
    }
}

flash('success', 'Partida criada.');
redirect($competitionId > 0 ? PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId : PROJECT_URL . '/admin/partidas/index.php');
