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

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT match_id FROM match_lineup WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$matchId = (int)($stmt->fetchColumn() ?: 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM match_lineup WHERE id = ?");
    $stmt->execute([$id]);
    flash('success', 'Jogador removido da escalação.');
}

redirect(PROJECT_URL . '/admin/partidas/lineup.php?id=' . $matchId);
