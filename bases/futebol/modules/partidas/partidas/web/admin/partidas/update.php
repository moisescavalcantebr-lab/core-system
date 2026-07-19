<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';
require __DIR__ . '/plan_fallback.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);
$participantA = trim((string)($_POST['participant_a'] ?? ''));
$participantB = trim((string)($_POST['participant_b'] ?? ''));
$status = in_array($_POST['status'] ?? '', ['scheduled', 'live', 'finished', 'canceled'], true) ? $_POST['status'] : 'scheduled';
$lineupMode = in_array($_POST['lineup_mode'] ?? '', ['team_roster', 'arrival_order', 'automatic'], true) ? $_POST['lineup_mode'] : 'team_roster';
$matchDate = trim((string)($_POST['match_date'] ?? '')) ?: null;
$scoreA = ($_POST['score_a'] ?? '') !== '' ? max(0, (int)$_POST['score_a']) : null;
$scoreB = ($_POST['score_b'] ?? '') !== '' ? max(0, (int)$_POST['score_b']) : null;
$matchFee = max(0, (float)str_replace(',', '.', (string)($_POST['match_fee'] ?? '0')));

if ($id <= 0 || $participantA === '' || $participantB === '') {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/partidas/index.php');
}

if ($status === 'live' && !projectPlanAllows('live_match_enabled', false)) {
    flash('error', projectPlanName() . ' nao permite partida em andamento.');
    redirect(PROJECT_URL . '/admin/partidas/edit.php?id=' . $id);
}

if ($matchDate !== null) {
    $matchDate = str_replace('T', ' ', $matchDate);
}

if ($scoreA !== null && $scoreB !== null) {
    $status = 'finished';
}

$stmt = $pdo->prepare("
    SELECT m.competition_id, c.context
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$matchContext = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$competitionContext = (string)($matchContext['context'] ?? 'external');

if ($competitionContext === 'internal') {
    $lineupMode = 'automatic';
} elseif ($lineupMode === 'automatic') {
    $lineupMode = 'team_roster';
}

$stmt = $pdo->prepare("
    UPDATE matches
    SET participant_a = ?, participant_b = ?, score_a = ?, score_b = ?, match_date = ?, venue = ?, round_name = ?, status = ?, lineup_mode = ?, match_fee = ?, notes = ?
    WHERE id = ?
");
$stmt->execute([
    $participantA,
    $participantB,
    $scoreA,
    $scoreB,
    $matchDate,
    trim((string)($_POST['venue'] ?? '')) ?: null,
    trim((string)($_POST['round_name'] ?? '')) ?: null,
    $status,
    $lineupMode,
    $matchFee,
    trim((string)($_POST['notes'] ?? '')) ?: null,
    $id,
]);

$competitionId = (int)($matchContext['competition_id'] ?? 0);

if ($status === 'finished') {
    matchLineupSaveFieldSnapshot($pdo, $id);
}

if ($status === 'finished' && $competitionId > 0 && $classificationEnabled && function_exists('classificationRebuildInternalCompetition')) {
    classificationRebuildInternalCompetition($pdo, $competitionId);
}

flash('success', 'Partida atualizada.');
redirect($competitionId > 0 ? PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId : PROJECT_URL . '/admin/partidas/index.php');
