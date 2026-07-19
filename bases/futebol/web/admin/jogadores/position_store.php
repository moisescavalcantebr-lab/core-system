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

$name = trim((string)($_POST['name'] ?? ''));
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

if ($name === '') {
    exit('Nome obrigatorio.');
}

$stmt = $pdo->prepare("
    INSERT INTO player_positions (name, status)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE status = VALUES(status)
");
$stmt->execute([$name, $status]);

header('Location: ' . PROJECT_URL . '/admin/jogadores/positions.php');
exit;
