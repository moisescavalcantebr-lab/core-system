<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function trainingToggleEnsureSchema(PDO $pdo): void
{
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

    $pdo->exec("ALTER TABLE training_roster MODIFY COLUMN status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve'");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();
trainingToggleEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$source = (string)($_POST['source'] ?? 'roster');
$target = (string)($_POST['target'] ?? 'inactive');
$target = $target === 'active' ? 'active' : 'inactive';
$returnTo = (string)($_POST['return'] ?? '');
$redirectUrl = $returnTo === 'disponiveis'
    ? PROJECT_URL . '/admin/treino/disponiveis.php'
    : PROJECT_URL . '/admin/treino/index.php';

if ($id <= 0) {
    flash('error', 'Jogador nao encontrado.');
    redirect($redirectUrl);
}

if ($source === 'player') {
    $stmt = $pdo->prepare("SELECT id, status FROM players WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player || ($player['status'] ?? '') !== 'active') {
        flash('error', 'Jogador ativo nao encontrado.');
        redirect($redirectUrl);
    }

    if ($target === 'active') {
        $stmt = $pdo->prepare("DELETE FROM training_roster WHERE player_id = ?");
        $stmt->execute([$id]);
        flash('success', 'Jogador disponivel no treino.');
        redirect($redirectUrl);
    }

    $stmt = $pdo->prepare("
        INSERT INTO training_roster (player_id, team_key, status, slot_group, slot_index)
        VALUES (?, 'time_1', 'inactive', NULL, NULL)
        ON DUPLICATE KEY UPDATE status = 'inactive', slot_group = NULL, slot_index = NULL
    ");
    $stmt->execute([$id]);
    flash('success', 'Jogador ocultado do treino.');
    redirect($redirectUrl);
}

$stmt = $pdo->prepare("
    SELECT tr.id
    FROM training_roster tr
    INNER JOIN players p ON p.id = tr.player_id
    WHERE tr.id = ?
      AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    flash('error', 'Jogador ativo nao encontrado no treino.');
    redirect($redirectUrl);
}

if ($target === 'active') {
    $stmt = $pdo->prepare("DELETE FROM training_roster WHERE id = ?");
    $stmt->execute([$id]);
    flash('success', 'Jogador disponivel no treino.');
    redirect($redirectUrl);
}

$stmt = $pdo->prepare("UPDATE training_roster SET status = 'inactive', slot_group = NULL, slot_index = NULL WHERE id = ?");
$stmt->execute([$id]);

flash('success', 'Jogador ocultado do treino.');
redirect($redirectUrl);
