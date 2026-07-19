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

$scoreboardId = (int)($_POST['scoreboard_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? 'Placar Principal'));
$homeLabel = trim((string)($_POST['home_label'] ?? 'Casa'));
$awayLabel = trim((string)($_POST['away_label'] ?? 'Visitante'));
$status = in_array($_POST['status'] ?? '', ['draft', 'live', 'finished', 'canceled'], true)
    ? $_POST['status']
    : 'live';
$resetScores = (string)($_POST['reset_scores'] ?? '0') === '1';

if ($scoreboardId <= 0) {
    exit('Placar invalido.');
}

$stmt = $pdo->prepare("
    UPDATE scoreboards
    SET title = ?, status = ?,
        started_at = CASE WHEN ? = 'live' AND started_at IS NULL THEN NOW() ELSE started_at END,
        finished_at = CASE WHEN ? = 'finished' THEN NOW() ELSE finished_at END
    WHERE id = ?
");
$stmt->execute([$title !== '' ? $title : 'Placar Principal', $status, $status, $status, $scoreboardId]);

$stmt = $pdo->prepare("
    SELECT id
    FROM scoreboard_items
    WHERE scoreboard_id = ?
    ORDER BY sort_order ASC, id ASC
    LIMIT 2
");
$stmt->execute([$scoreboardId]);
$items = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($items) >= 2) {
    $scoreValue = $resetScores ? ', score = 0' : '';

    $stmt = $pdo->prepare("UPDATE scoreboard_items SET label = ?" . $scoreValue . " WHERE id = ?");
    $stmt->execute([$homeLabel !== '' ? $homeLabel : 'Casa', (int)$items[0]]);
    $stmt->execute([$awayLabel !== '' ? $awayLabel : 'Visitante', (int)$items[1]]);
}

flash('success', 'Placar salvo com sucesso.');
header('Location: ' . PROJECT_URL . '/admin/placar/index.php');
exit;
