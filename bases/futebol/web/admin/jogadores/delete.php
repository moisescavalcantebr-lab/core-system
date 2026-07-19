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
$return = (string)($_GET['return'] ?? '');
$redirect = $return === 'inactive'
    ? PROJECT_URL . '/admin/jogadores/inativos.php'
    : PROJECT_URL . '/admin/jogadores/index.php';

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT p.user_id, u.role
        FROM players p
        LEFT JOIN project_users u ON u.id = p.user_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$id]);

    if (!empty($player['user_id']) && ($player['role'] ?? '') === 'PLAYER') {
        $stmt = $pdo->prepare("DELETE FROM project_users WHERE id = ?");
        $stmt->execute([(int)$player['user_id']]);
    }
}

header('Location: ' . $redirect);
exit;
