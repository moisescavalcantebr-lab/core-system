<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function trainingStoreEnsureSchema(PDO $pdo): void
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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            custom_slots_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function trainingStoreDefaultSlots(): array
{
    return [
        'GO' => 1,
        'ZC1' => 1,
        'ZC2' => 1,
        'LE' => 1,
        'LD' => 1,
        'VOL1' => 1,
        'MAT1' => 1,
        'PTE' => 1,
        'PTD' => 1,
        'CA1' => 1,
        'CA2' => 1,
    ];
}

function trainingStoreSettings(PDO $pdo, array $team): array
{
    $settings = $pdo->query("SELECT * FROM training_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        $teamSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
        $teamSlots = is_array($teamSlots) && $teamSlots ? $teamSlots : trainingStoreDefaultSlots();
        $stmt = $pdo->prepare("INSERT INTO training_settings (id, custom_slots_json) VALUES (1, ?)");
        $stmt->execute([json_encode($teamSlots, JSON_UNESCAPED_UNICODE)]);
        return ['custom_slots_json' => json_encode($teamSlots, JSON_UNESCAPED_UNICODE)];
    }

    return $settings;
}

function trainingStoreNormalizeCustomSlots(array $customSlots): array
{
    foreach (['ZC', 'VOL', 'MAT', 'CA'] as $prefix) {
        if (!empty($customSlots[$prefix]) && empty($customSlots[$prefix . '1']) && empty($customSlots[$prefix . '2'])) {
            $customSlots[$prefix . '1'] = 1;
            if ((int)$customSlots[$prefix] > 1) {
                $customSlots[$prefix . '2'] = 1;
            }
        }
    }

    return $customSlots;
}

function trainingStoreFieldLimits(array $team): array
{
    $customSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
    $customSlots = is_array($customSlots) ? trainingStoreNormalizeCustomSlots($customSlots) : [];

    if (!$customSlots) {
        return ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1];
    }

    $map = [
        'GO' => 'goleiro',
        'ZC1' => 'zagueiro',
        'ZC2' => 'zagueiro',
        'LE' => 'lateral',
        'LD' => 'lateral',
        'VOL1' => 'meia',
        'VOL2' => 'meia',
        'MAT1' => 'meia',
        'MAT2' => 'meia',
        'PTE' => 'ponta',
        'PTD' => 'ponta',
        'CA1' => 'atacante',
        'CA2' => 'atacante',
    ];
    $limits = [];

    foreach ($map as $code => $group) {
        if (!empty($customSlots[$code])) {
            $limits[$group] = ($limits[$group] ?? 0) + 1;
        }
    }

    return $limits ?: ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1];
}

function trainingStorePositionKey(?string $position, ?string $groupKey = null): string
{
    if ($groupKey !== null && $groupKey !== '') {
        return $groupKey;
    }

    $position = strtolower((string)$position);

    if (str_contains($position, 'goleiro')) return 'goleiro';
    if (str_contains($position, 'zagueiro')) return 'zagueiro';
    if (str_contains($position, 'lateral')) return 'lateral';
    if (str_contains($position, 'ponta')) return 'ponta';
    if (str_contains($position, 'volante') || str_contains($position, 'meia')) return 'meia';
    if (str_contains($position, 'atacante')) return 'atacante';

    return '';
}

function trainingStoreCandidateSlots(array $player, array $limits): array
{
    $code = (string)($player['position_code'] ?? '');
    $groupKey = trainingStorePositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);

    $slots = match (true) {
        str_starts_with($code, 'GO') => [['goleiro', 0]],
        str_starts_with($code, 'ZC') => [['zagueiro', str_starts_with($code, 'ZC2') ? 1 : 0], ['zagueiro', 0], ['zagueiro', 1]],
        str_starts_with($code, 'LE') => [['lateral', 0], ['lateral', 1]],
        str_starts_with($code, 'LD') => [['lateral', 1], ['lateral', 0]],
        str_starts_with($code, 'VOL') => [['meia', 0], ['meia', 1], ['meia', 2]],
        str_starts_with($code, 'MAT') => [['meia', 1], ['meia', 2], ['meia', 0]],
        str_starts_with($code, 'PTE') => [['ponta', 0], ['ponta', 1]],
        str_starts_with($code, 'PTD') => [['ponta', 1], ['ponta', 0]],
        str_starts_with($code, 'CA') => [['atacante', 0]],
        default => [],
    };

    if (!$slots && isset($limits[$groupKey])) {
        for ($i = 0; $i < $limits[$groupKey]; $i++) {
            $slots[] = [$groupKey, $i];
        }
    }

    $unique = [];
    foreach ($slots as $slot) {
        if (isset($limits[$slot[0]]) && $slot[1] < $limits[$slot[0]]) {
            $unique[$slot[0] . ':' . $slot[1]] = $slot;
        }
    }

    return array_values($unique);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();
trainingStoreEnsureSchema($pdo);

$playerId = (int)($_POST['player_id'] ?? 0);
$teamKey = (string)($_POST['team_key'] ?? 'time_1');
$teamKey = $teamKey === 'time_2' ? 'time_2' : 'time_1';
$returnTo = (string)($_POST['return'] ?? '');
$redirectUrl = $returnTo === 'disponiveis'
    ? PROJECT_URL . '/admin/treino/disponiveis.php'
    : PROJECT_URL . '/admin/treino/index.php';

if ($playerId <= 0) {
    flash('error', 'Selecione um jogador.');
    redirect($redirectUrl);
}

$stmt = $pdo->prepare("
    SELECT p.id, p.status, pp.name AS position_name, pp.code AS position_code, pp.group_key
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$playerId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player || ($player['status'] ?? '') !== 'active') {
    flash('error', 'Jogador ativo nao encontrado.');
    redirect($redirectUrl);
}

$team = $pdo->query("SELECT * FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$trainingSettings = trainingStoreSettings($pdo, $team);
$limits = trainingStoreFieldLimits($trainingSettings);
$candidateSlots = trainingStoreCandidateSlots($player, $limits);

$stmt = $pdo->prepare("
    SELECT slot_group, slot_index
    FROM training_roster
    WHERE team_key = ?
      AND status = 'field'
      AND player_id <> ?
");
$stmt->execute([$teamKey, $playerId]);
$occupied = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (($row['slot_group'] ?? '') !== '' && $row['slot_index'] !== null) {
        $occupied[(string)$row['slot_group'] . ':' . (int)$row['slot_index']] = true;
    }
}

$status = 'reserve';
$slotGroup = null;
$slotIndex = null;

foreach ($candidateSlots as [$candidateGroup, $candidateIndex]) {
    $key = $candidateGroup . ':' . $candidateIndex;
    if (!isset($occupied[$key])) {
        $status = 'field';
        $slotGroup = $candidateGroup;
        $slotIndex = (int)$candidateIndex;
        break;
    }
}

$stmt = $pdo->prepare("
    INSERT INTO training_roster (player_id, team_key, status, slot_group, slot_index)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        team_key = VALUES(team_key),
        status = VALUES(status),
        slot_group = VALUES(slot_group),
        slot_index = VALUES(slot_index)
");
$stmt->execute([$playerId, $teamKey, $status, $slotGroup, $slotIndex]);

flash('success', $status === 'field' ? 'Jogador escalado no treino.' : 'Jogador adicionado como reserva do treino.');
redirect($redirectUrl);
