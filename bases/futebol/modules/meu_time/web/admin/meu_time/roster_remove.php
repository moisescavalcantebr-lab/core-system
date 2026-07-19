<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function teamEnsureBaseSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_roster (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            slot_group VARCHAR(40) NULL,
            slot_index INT NULL,
            roster_role ENUM('player','captain','coach') DEFAULT 'player',
            joined_at DATE NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_team_roster_player (player_id),
            INDEX(status),
            INDEX(slot_group, slot_index),
            INDEX(roster_role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function teamEnsureRosterSlotSchema(PDO $pdo): void
{
    teamEnsureBaseSchema($pdo);

    $columns = $pdo->query("SHOW COLUMNS FROM team_roster")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('slot_group', $columns, true)) {
        $pdo->exec("ALTER TABLE team_roster ADD COLUMN slot_group VARCHAR(40) NULL AFTER status");
    }

    if (!in_array('slot_index', $columns, true)) {
        $pdo->exec("ALTER TABLE team_roster ADD COLUMN slot_index INT NULL AFTER slot_group");
    }
}

teamEnsureRosterSlotSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);
$mode = $_POST['mode'] ?? 'reserve';

if ($id > 0) {
    if ($mode === 'elenco') {
        $stmt = $pdo->prepare("DELETE FROM team_roster WHERE id = ?");
        $stmt->execute([$id]);
        flash('success', 'Jogador retirado das reservas e voltou para o elenco.');
    } else {
        $stmt = $pdo->prepare("UPDATE team_roster SET status = 'inactive', slot_group = NULL, slot_index = NULL WHERE id = ?");
        $stmt->execute([$id]);
        flash('success', 'Jogador retirado do campo e voltou para reservas.');
    }
} else {
    flash('error', 'Jogador nao encontrado no elenco.');
}
redirect(PROJECT_URL . '/admin/meu_time/index.php');
