<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(u.name, p.name) AS name, COALESCE(p.avatar, u.avatar) AS avatar, u.username, u.email, u.role AS user_role, u.status AS user_status
    FROM players p
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    exit('Jogador nao encontrado');
}

if (($_GET['activate'] ?? '') === '1') {
    $player['status'] = 'active';
}

$title = 'Editar Jogador';
$positions = playerAvailablePositions($pdo, $id);
$shirtNumbers = playerAvailableShirtNumbers($pdo, $id);
$formAction = PROJECT_URL . '/admin/jogadores/update.php?id=' . $id;
$submitLabel = 'Atualizar Jogador';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';
