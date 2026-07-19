<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$competitionId = (int)($_GET['id'] ?? 0);
$matchId = (int)($_POST['match_id'] ?? 0);
$scoreA = max(0, (int)($_POST['score_a'] ?? 0));
$scoreB = max(0, (int)($_POST['score_b'] ?? 0));

if ($competitionId <= 0 || $matchId <= 0) {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/competicoes/index.php');
}

$stmt = $pdo->prepare("
    UPDATE matches
    SET score_a = ?, score_b = ?, status = 'finished'
    WHERE id = ? AND competition_id = ?
");
$stmt->execute([$scoreA, $scoreB, $matchId, $competitionId]);

if ($stmt->rowCount() === 0) {
    flash('error', 'Partida nao encontrada.');
    redirect(PROJECT_URL . '/admin/competicoes/result.php?id=' . $competitionId);
}

if ($classificationEnabled && function_exists('classificationRebuildInternalCompetition')) {
    classificationRebuildInternalCompetition($pdo, $competitionId);
}

flash('success', 'Resultado salvo.');
redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . $competitionId);
