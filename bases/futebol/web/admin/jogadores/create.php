<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

$title = 'Novo Jogador';

$player = [
    'user_id' => '',
    'name' => '',
    'nickname' => '',
    'username' => '',
    'email' => '',
    'user_role' => 'PLAYER',
    'user_status' => '',
    'whatsapp' => '',
    'position_id' => '',
    'position' => '',
    'roster_status' => 'titular',
    'shirt_number' => '',
    'birth_date' => '',
    'dominant_foot' => '',
    'status' => 'inactive',
    'notes' => '',
];

$positions = playerAvailablePositions($pdo);
$shirtNumbers = playerAvailableShirtNumbers($pdo);
$formAction = PROJECT_URL . '/admin/jogadores/store.php';
$submitLabel = 'Salvar Jogador';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';
