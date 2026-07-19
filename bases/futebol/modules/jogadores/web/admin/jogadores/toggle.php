<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

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

$stmt = $pdo->prepare("
    SELECT p.id, p.user_id, p.status, p.position_id, p.shirt_number, u.role
    FROM players p
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if ($player) {
    $nextStatus = $player['status'] === 'active' ? 'inactive' : 'active';

    if ($nextStatus === 'inactive') {
        $stmt = $pdo->prepare("
            UPDATE players
            SET status = 'inactive',
                position_id = NULL,
                secondary_position_id = NULL,
                shirt_number = NULL
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        if (!empty($player['user_id']) && in_array(($player['role'] ?? ''), ['PLAYER', 'FINANCE'], true)) {
            $stmt = $pdo->prepare("UPDATE project_users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([(int)$player['user_id']]);
        }

        header('Location: ' . $redirect);
        exit;
    }

    if ($nextStatus === 'active') {
        if ($player['position_id'] === null || $player['shirt_number'] === null) {
            flash('error', 'Escolha posição e camisa para ativar este jogador.');
            header('Location: ' . PROJECT_URL . '/admin/jogadores/edit.php?id=' . $id . '&activate=1');
            exit;
        }

        $shirtError = playerValidateShirtNumber($pdo, $player['shirt_number'] !== null ? (int)$player['shirt_number'] : null, $id, $nextStatus);
        if ($shirtError !== null) {
            flash('error', $shirtError);
            header('Location: ' . PROJECT_URL . '/admin/jogadores/edit.php?id=' . $id . '&activate=1');
            exit;
        }
    }

    $validationError = playerValidateActiveRoster($pdo, $nextStatus, $player['position_id'] !== null ? (int)$player['position_id'] : null, $id);
    if ($validationError !== null) {
        if ($nextStatus === 'active' && str_contains($validationError, 'posicao')) {
            flash('error', 'Posição ocupada. Escolha outra para ativar este jogador.');
            header('Location: ' . PROJECT_URL . '/admin/jogadores/edit.php?id=' . $id . '&activate=1');
            exit;
        }

        flash('error', $validationError);
        header('Location: ' . $redirect);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE players SET status = ? WHERE id = ?");
    $stmt->execute([$nextStatus, $id]);

    if (!empty($player['user_id']) && in_array(($player['role'] ?? ''), ['PLAYER', 'FINANCE'], true)) {
        $stmt = $pdo->prepare("UPDATE project_users SET status = ? WHERE id = ?");
        $stmt->execute([$nextStatus, (int)$player['user_id']]);
    }
}

header('Location: ' . $redirect);
exit;
