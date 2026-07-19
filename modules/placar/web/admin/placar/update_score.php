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

$itemId = (int)($_POST['item_id'] ?? 0);
$delta = (int)($_POST['delta'] ?? 0);
$delta = max(-1, min(1, $delta));

if ($itemId <= 0 || $delta === 0) {
    http_response_code(422);
    exit('Dados invalidos.');
}

$stmt = $pdo->prepare("
    SELECT scoreboard_id
    FROM scoreboard_items
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$itemId]);
$scoreboardId = (int)($stmt->fetchColumn() ?: 0);

if ($scoreboardId <= 0) {
    http_response_code(404);
    exit('Item nao encontrado.');
}

$stmt = $pdo->prepare("
    UPDATE scoreboard_items
    SET score = GREATEST(0, score + ?)
    WHERE id = ?
");
$stmt->execute([$delta, $itemId]);

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/live_data.php';
