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

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $pdo->prepare("UPDATE players SET position_id = NULL WHERE position_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM player_positions WHERE id = ?")->execute([$id]);
}

header('Location: ' . PROJECT_URL . '/admin/jogadores/positions.php');
exit;
