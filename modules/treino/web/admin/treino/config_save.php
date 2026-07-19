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

$allowed = ['GO', 'ZC1', 'ZC2', 'LE', 'LD', 'VOL1', 'VOL2', 'MAT1', 'MAT2', 'PTE', 'PTD', 'CA1', 'CA2'];
$received = $_POST['custom_slots'] ?? [];
$received = is_array($received) ? $received : [];
$slots = [];

foreach ($allowed as $code) {
    if (!empty($received[$code])) {
        $slots[$code] = 1;
    }
}

if (!$slots) {
    flash('error', 'Selecione pelo menos uma posicao.');
    redirect(PROJECT_URL . '/admin/treino/config.php');
}

if (count($slots) > 11) {
    flash('error', 'O treino permite no maximo 11 posicoes em campo.');
    redirect(PROJECT_URL . '/admin/treino/config.php');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS training_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        custom_slots_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS training_roster (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        team_key ENUM('time_1','time_2') NOT NULL DEFAULT 'time_1',
        status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve',
        slot_group VARCHAR(40) NULL,
        slot_index INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_training_player (player_id),
        INDEX(team_key),
        INDEX(status),
        INDEX(slot_group, slot_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$stmt = $pdo->prepare("
    INSERT INTO training_settings (id, custom_slots_json)
    VALUES (1, ?)
    ON DUPLICATE KEY UPDATE custom_slots_json = VALUES(custom_slots_json)
");
$stmt->execute([json_encode($slots, JSON_UNESCAPED_UNICODE)]);

$validGroups = [
    'GO' => ['goleiro'],
    'ZC1' => ['zagueiro'],
    'ZC2' => ['zagueiro'],
    'LE' => ['lateral'],
    'LD' => ['lateral'],
    'VOL1' => ['meia'],
    'VOL2' => ['meia'],
    'MAT1' => ['meia'],
    'MAT2' => ['meia'],
    'PTE' => ['ponta'],
    'PTD' => ['ponta'],
    'CA1' => ['atacante'],
    'CA2' => ['atacante'],
];

$limits = [];
foreach ($slots as $code => $_) {
    foreach ($validGroups[$code] ?? [] as $group) {
        $limits[$group] = ($limits[$group] ?? 0) + 1;
    }
}

$rows = $pdo->query("SELECT id, slot_group, slot_index FROM training_roster WHERE status = 'field'")->fetchAll(PDO::FETCH_ASSOC);
$toReserve = [];
foreach ($rows as $row) {
    $group = (string)($row['slot_group'] ?? '');
    $index = $row['slot_index'] !== null ? (int)$row['slot_index'] : -1;
    if (!isset($limits[$group]) || $index < 0 || $index >= (int)$limits[$group]) {
        $toReserve[] = (int)$row['id'];
    }
}

if ($toReserve) {
    $placeholders = implode(',', array_fill(0, count($toReserve), '?'));
    $stmt = $pdo->prepare("UPDATE training_roster SET status = 'reserve', slot_group = NULL, slot_index = NULL WHERE id IN ($placeholders)");
    $stmt->execute($toReserve);
}

flash('success', $toReserve ? 'Posicoes salvas. Alguns jogadores voltaram para a reserva.' : 'Posicoes do treino salvas.');
redirect(PROJECT_URL . '/admin/treino/config.php');
