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

$context = in_array($_POST['context'] ?? '', ['external', 'internal'], true) ? $_POST['context'] : 'external';
$playerId = (int)($_POST['player_id'] ?? 0);
$typeId = (int)($_POST['statistic_type_id'] ?? 0);
$competitionId = !empty($_POST['competition_id']) ? (int)$_POST['competition_id'] : null;
$value = $_POST['value_number'] !== '' ? (float)$_POST['value_number'] : null;
$recordedAt = !empty($_POST['recorded_at']) ? $_POST['recorded_at'] : null;

if ($playerId <= 0 || $typeId <= 0) {
    flash('error', 'Jogador e estatistica sao obrigatorios.');
    redirect(PROJECT_URL . '/admin/estatisticas/index.php');
}

$stmt = $pdo->prepare("
    INSERT INTO statistic_records (context, competition_id, player_id, statistic_type_id, value_number, recorded_at)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$context, $competitionId, $playerId, $typeId, $value, $recordedAt]);

flash('success', 'Estatistica registrada.');
redirect(PROJECT_URL . '/admin/estatisticas/index.php');
