<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function teamEnsureBaseSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_profile (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL DEFAULT 'Meu Time',
            short_name VARCHAR(80) NULL,
            team_type ENUM('futsal','society','beach','field','other','custom') DEFAULT 'field',
            starters_count INT DEFAULT 11,
            reserves_count INT DEFAULT 7,
            custom_slots_json JSON NULL,
            city VARCHAR(100) NULL,
            venue VARCHAR(150) NULL,
            primary_color VARCHAR(20) NULL,
            secondary_color VARCHAR(20) NULL,
            responsible_name VARCHAR(150) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

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

    $pdo->exec("INSERT INTO team_profile (id, name) VALUES (1, 'Meu Time') ON DUPLICATE KEY UPDATE id = id");
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

    $profileColumns = $pdo->query("SHOW COLUMNS FROM team_profile")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('custom_slots_json', $profileColumns, true)) {
        $pdo->exec("ALTER TABLE team_profile ADD COLUMN custom_slots_json JSON NULL AFTER reserves_count");
    }

    $pdo->exec("ALTER TABLE team_profile MODIFY COLUMN team_type ENUM('futsal','society','beach','field','other','custom') DEFAULT 'field'");
}

teamEnsureRosterSlotSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();
require_once __DIR__ . '/formations_helpers.php';
teamEnsureFormationsSchema($pdo);

$playerId = (int)($_POST['player_id'] ?? 0);
$teamKey = (string)($_POST['team_key'] ?? 'time_1');
if (!in_array($teamKey, ['time_1', 'time_2'], true)) {
    http_response_code(422);
    exit('Time invalido.');
}
if ($teamKey === 'time_2' && !teamSecondFormationAllowed()) {
    flash('error', 'Segundo time disponivel a partir do Plano Start.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}
$role = in_array($_POST['roster_role'] ?? '', ['player', 'captain'], true) ? $_POST['roster_role'] : 'player';

if ($playerId <= 0) {
    flash('error', 'Selecione um jogador.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

require_once __DIR__ . '/lineup_helpers.php';

$pdo->beginTransaction();
$team = $pdo->query("SELECT team_type, custom_slots_json, formations_count FROM team_profile WHERE id = 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC) ?: [];
$team = teamUsableFormations($team);
if ($teamKey === 'time_2' && (int)($team['formations_count'] ?? 1) !== 2) {
    $pdo->rollBack();
    flash('error', 'Ative o segundo time antes de adicionar jogadores.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}
$resolved = teamResolveFormationRoster(teamLoadFormationRoster($pdo), $team);
if (isset($resolved['assigned'][$playerId])) {
    $pdo->rollBack();
    flash('error', 'Jogador ja esta em campo. Retire-o antes de mudar de time.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}
$teamType = teamFormationType($team);
$customSlotsJson = (string)($team['custom_slots_json'] ?? '');
$fieldLimits = teamFieldLimits($teamType, $customSlotsJson);

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.status,
        pp.name AS position_name,
        pp.code AS position_code,
        pp.group_key,
        pp2.name AS secondary_position_name,
        pp2.code AS secondary_position_code,
        pp2.group_key AS secondary_group_key
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions pp2 ON pp2.id = p.secondary_position_id
    WHERE p.id = ?
    LIMIT 1
    FOR UPDATE
");
$stmt->execute([$playerId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    $pdo->rollBack();
    flash('error', 'Jogador nao encontrado.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

if (($player['status'] ?? '') !== 'active') {
    $pdo->rollBack();
    flash('error', 'Apenas jogadores ativos podem entrar no elenco.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

$positionKey = teamPositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);
$candidateSlots = teamCandidateSlotsWithSecondary($player, $fieldLimits, $teamType, $customSlotsJson);
$selectedSlot = $_POST['field_slot'] ?? null;
$fieldPositionCode = null;
$primaryPosition = null;
if ($selectedSlot !== null) {
    $slots = teamFormationSlots($team);
    if (!is_string($selectedSlot) || !isset($slots[$selectedSlot])) {
        $pdo->rollBack();
        flash('error', 'Selecione uma posicao do campo atual.');
        redirect(PROJECT_URL . '/admin/meu_time/index.php');
    }
    $selection = $slots[$selectedSlot];
    $candidateSlots = [[$selection['group'], $selection['index']]];
    $fieldPositionCode = $selection['code'];
    if (($_POST['update_primary_position'] ?? '') === '1') {
        $prefix = preg_replace('/\d+$/', '', $fieldPositionCode);
        $query = $pdo->prepare("SELECT id FROM player_positions WHERE status='active' AND code REGEXP ? ORDER BY (code = ?) DESC, sort_order, id LIMIT 1");
        $query->execute(['^'.$prefix.'[0-9]*$', $fieldPositionCode]);
        $primaryPosition = $query->fetchColumn();
        if (!$primaryPosition) {
            $pdo->rollBack();
            flash('error', 'Posicao principal indisponivel no cadastro.');
            redirect(PROJECT_URL . '/admin/meu_time/index.php');
        }
    }
}

if ($selectedSlot === null && !teamIsReservePosition($player) && !isset($fieldLimits[$positionKey]) && empty($candidateSlots)) {
    $pdo->rollBack();
    flash('error', 'A posicao principal e a secundaria nao fazem parte do campo atual. Ajuste a posicao do jogador para adicionar.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

$occupiedSlots = $resolved['teams'][$teamKey]['occupied'];

$status = 'active';
$newSlotGroup = null;
$newSlotIndex = null;

foreach ($candidateSlots as [$candidateGroup, $candidateIndex]) {
    $candidateKey = $candidateGroup . ':' . $candidateIndex;
    if (!isset($fieldLimits[$candidateGroup]) || $candidateIndex >= $fieldLimits[$candidateGroup] || isset($occupiedSlots[$candidateKey])) {
        continue;
    }

    $newSlotGroup = $candidateGroup;
    $newSlotIndex = $candidateIndex;
    break;
}

if ($newSlotGroup === null || $newSlotIndex === null) {
    if ($selectedSlot !== null) {
        $pdo->rollBack();
        flash('error', 'Vaga ocupada. Jogador continua disponivel.');
        redirect(PROJECT_URL . '/admin/meu_time/index.php');
    }
    $status = 'inactive';
}

$stmt = $pdo->prepare("
    INSERT INTO team_roster (player_id, roster_role, status, slot_group, slot_index, team_key, field_position_code)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        roster_role = VALUES(roster_role),
        status = VALUES(status),
        slot_group = VALUES(slot_group),
        slot_index = VALUES(slot_index),
        team_key = VALUES(team_key),
        field_position_code = VALUES(field_position_code)
");
$stmt->execute([$playerId, $role, $status, $newSlotGroup, $newSlotIndex, $teamKey, $fieldPositionCode]);
if ($primaryPosition !== null) {
    $pdo->prepare('UPDATE players SET position_id = ? WHERE id = ?')->execute([(int)$primaryPosition, $playerId]);
}
$pdo->commit();

flash('success', $status === 'active' ? 'Jogador adicionado ao campo.' : 'Vaga ocupada. Jogador continua disponivel.');
redirect(PROJECT_URL . '/admin/meu_time/index.php');
