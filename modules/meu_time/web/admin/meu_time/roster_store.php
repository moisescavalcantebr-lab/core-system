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

$playerId = (int)($_POST['player_id'] ?? 0);
$role = in_array($_POST['roster_role'] ?? '', ['player', 'captain'], true) ? $_POST['roster_role'] : 'player';

if ($playerId <= 0) {
    flash('error', 'Selecione um jogador.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

function teamPositionKey(?string $position, ?string $groupKey = null): string
{
    if ($groupKey !== null && $groupKey !== '') {
        return $groupKey;
    }

    $position = strtolower((string)$position);

    if (str_contains($position, 'goleiro')) {
        return 'goleiro';
    }

    if (str_contains($position, 'zagueiro')) {
        return 'zagueiro';
    }

    if (str_contains($position, 'lateral')) {
        return 'lateral';
    }

    if (str_contains($position, 'ponta')) {
        return 'ponta';
    }

    if (str_contains($position, 'volante') || str_contains($position, 'meia')) {
        return 'meia';
    }

    if (str_contains($position, 'atacante')) {
        return 'atacante';
    }

    return 'outro';
}

function teamIsReservePosition(array $player): bool
{
    $code = strtoupper((string)($player['position_code'] ?? ''));
    $groupKey = strtolower((string)($player['group_key'] ?? ''));

    return str_starts_with($code, 'RES') || $groupKey === 'reserva';
}

function teamCustomFieldLimits(?string $customSlotsJson): ?array
{
    $customSlots = json_decode((string)$customSlotsJson, true);
    $customSlots = is_array($customSlots) ? $customSlots : [];
    $customSlots = teamNormalizeCustomSlots($customSlots);

    if (!$customSlots) {
        return null;
    }

    $groups = [
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

    foreach ($groups as $code => $group) {
        if (!empty($customSlots[$code])) {
            $limits[$group] = ($limits[$group] ?? 0) + 1;
        }
    }

    return $limits ?: null;
}

function teamNormalizeCustomSlots(array $customSlots): array
{
    if (!empty($customSlots['ZC']) && empty($customSlots['ZC1']) && empty($customSlots['ZC2'])) {
        $customSlots['ZC1'] = 1;
        if ((int)$customSlots['ZC'] > 1) {
            $customSlots['ZC2'] = 1;
        }
    }

    if (!empty($customSlots['MAT']) && empty($customSlots['MAT1']) && empty($customSlots['MAT2'])) {
        $customSlots['MAT1'] = 1;
        if ((int)$customSlots['MAT'] > 1) {
            $customSlots['MAT2'] = 1;
        }
    }
    if (!empty($customSlots['VOL']) && empty($customSlots['VOL1']) && empty($customSlots['VOL2'])) {
        $customSlots['VOL1'] = 1;
        if ((int)$customSlots['VOL'] > 1) {
            $customSlots['VOL2'] = 1;
        }
    }
    if (!empty($customSlots['CA']) && empty($customSlots['CA1']) && empty($customSlots['CA2'])) {
        $customSlots['CA1'] = 1;
        if ((int)$customSlots['CA'] > 1) {
            $customSlots['CA2'] = 1;
        }
    }

    return $customSlots;
}

function teamCustomCandidateSlots(array $player, array $fieldLimits, ?string $customSlotsJson): array
{
    $customSlots = json_decode((string)$customSlotsJson, true);
    $customSlots = is_array($customSlots) ? $customSlots : [];
    $customSlots = teamNormalizeCustomSlots($customSlots);

    if (!$customSlots) {
        return [];
    }

    $positionCode = (string)($player['position_code'] ?? '');
    $baseCode = preg_replace('/\d+$/', '', $positionCode);
    $groupOrders = [
        'goleiro' => ['GO'],
        'zagueiro' => ['ZC1', 'ZC2'],
        'lateral' => ['LE', 'LD'],
        'meia' => ['VOL1', 'VOL2', 'MAT1', 'MAT2'],
        'ponta' => ['PTE', 'PTD'],
        'atacante' => ['CA1', 'CA2'],
    ];
    $preferredCodes = match ($baseCode) {
        'GO' => ['GO'],
        'ZC' => str_starts_with($positionCode, 'ZC2') ? ['ZC2', 'ZC1'] : ['ZC1', 'ZC2'],
        'LE' => ['LE'],
        'LD' => ['LD'],
        'VOL' => str_starts_with($positionCode, 'VOL2') ? ['VOL2', 'VOL1'] : ['VOL1', 'VOL2'],
        'MAT' => str_starts_with($positionCode, 'MAT2') ? ['MAT2', 'MAT1'] : ['MAT1', 'MAT2'],
        'PTE' => ['PTE'],
        'PTD' => ['PTD'],
        'CA' => str_starts_with($positionCode, 'CA2') ? ['CA2', 'CA1'] : ['CA1', 'CA2'],
        default => [],
    };

    if (!$preferredCodes) {
        return [];
    }

    $slots = [];
    foreach ($groupOrders as $group => $order) {
        $selectedOrder = array_values(array_filter($order, static fn (string $code): bool => !empty($customSlots[$code])));

        foreach ($preferredCodes as $preferredCode) {
            $index = array_search($preferredCode, $selectedOrder, true);
            if ($index !== false && $index < ($fieldLimits[$group] ?? 0)) {
                $slots[] = [$group, (int)$index];
            }
        }
    }

    return $slots;
}

function teamFieldLimits(string $teamType, ?string $customSlotsJson = null): array
{
    if ($teamType === 'custom') {
        $customLimits = teamCustomFieldLimits($customSlotsJson);
        if ($customLimits !== null) {
            return $customLimits;
        }
    }

    return match ($teamType) {
        'futsal', 'beach' => ['goleiro' => 1, 'zagueiro' => 1, 'lateral' => 1, 'meia' => 1, 'atacante' => 1],
        'society' => ['goleiro' => 1, 'zagueiro' => 1, 'lateral' => 2, 'meia' => 1, 'ponta' => 1, 'atacante' => 1],
        default => ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1],
    };
}

function teamCandidateSlots(array $player, array $fieldLimits, string $teamType = 'field', ?string $customSlotsJson = null): array
{
    if (teamIsReservePosition($player)) {
        $slots = [];
        foreach ($fieldLimits as $group => $limit) {
            for ($index = 0; $index < $limit; $index++) {
                $slots[] = [$group, $index];
            }
        }
        return $slots;
    }

    if ($teamType === 'custom') {
        return teamCustomCandidateSlots($player, $fieldLimits, $customSlotsJson);
    }

    $code = (string)($player['position_code'] ?? '');
    $groupKey = teamPositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);

    if (str_starts_with($code, 'PTE')) {
        return [['ponta', 0]];
    }

    if (str_starts_with($code, 'PTD')) {
        return [['ponta', 1]];
    }

    if (str_starts_with($code, 'LE')) {
        return [['lateral', 0]];
    }

    if (str_starts_with($code, 'LD')) {
        return [['lateral', 1]];
    }

    if (str_starts_with($code, 'GO')) {
        return [['goleiro', 0]];
    }

    if (str_starts_with($code, 'CA')) {
        return [['atacante', 0]];
    }

    if (str_starts_with($code, 'VOL')) {
        return [['meia', 0], ['meia', 1], ['meia', 2]];
    }

    if (str_starts_with($code, 'MAT')) {
        return [['meia', 1], ['meia', 2], ['meia', 0]];
    }

    $candidates = [];
    $limit = $fieldLimits[$groupKey] ?? 0;
    for ($index = 0; $index < $limit; $index++) {
        $candidates[] = [$groupKey, $index];
    }

    return $candidates;
}

function teamSecondaryPositionCandidate(array $player): ?array
{
    $secondaryCode = (string)($player['secondary_position_code'] ?? '');
    if ($secondaryCode === '') {
        return null;
    }

    $secondary = $player;
    $secondary['position_name'] = $player['secondary_position_name'] ?? '';
    $secondary['position_code'] = $secondaryCode;
    $secondary['group_key'] = $player['secondary_group_key'] ?? '';

    return $secondary;
}

function teamCandidateSlotsWithSecondary(array $player, array $fieldLimits, string $teamType = 'field', ?string $customSlotsJson = null): array
{
    $candidates = teamCandidateSlots($player, $fieldLimits, $teamType, $customSlotsJson);
    $secondary = teamSecondaryPositionCandidate($player);

    if ($secondary !== null) {
        $candidates = array_merge($candidates, teamCandidateSlots($secondary, $fieldLimits, $teamType, $customSlotsJson));
    }

    $unique = [];
    foreach ($candidates as $candidate) {
        $key = $candidate[0] . ':' . $candidate[1];
        $unique[$key] = $candidate;
    }

    return array_values($unique);
}

$team = $pdo->query("SELECT team_type, custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$teamType = (string)($team['team_type'] ?? 'field');
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
");
$stmt->execute([$playerId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    flash('error', 'Jogador nao encontrado.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

if (($player['status'] ?? '') !== 'active') {
    flash('error', 'Apenas jogadores ativos podem entrar no elenco.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

$positionKey = teamPositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);
$candidateSlots = teamCandidateSlotsWithSecondary($player, $fieldLimits, $teamType, $customSlotsJson);

if (!teamIsReservePosition($player) && !isset($fieldLimits[$positionKey]) && empty($candidateSlots)) {
    flash('error', 'A posicao principal e a secundaria nao fazem parte do campo atual. Ajuste a posicao do jogador para adicionar.');
    redirect(PROJECT_URL . '/admin/meu_time/index.php');
}

$activeRoster = $pdo->query("
    SELECT tr.player_id, tr.slot_group, tr.slot_index, pp.name AS position_name, pp.code AS position_code, pp.group_key
    FROM team_roster tr
    INNER JOIN players p ON p.id = tr.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    WHERE tr.status = 'active'
      AND p.status = 'active'
    ORDER BY COALESCE(tr.updated_at, tr.created_at) ASC, tr.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$occupiedSlots = [];

foreach ($activeRoster as $activePlayer) {
    if ((int)$activePlayer['player_id'] === $playerId) {
        continue;
    }

    $slotGroup = (string)($activePlayer['slot_group'] ?? '');
    $slotIndex = $activePlayer['slot_index'] !== null ? (int)$activePlayer['slot_index'] : null;

    if ($slotGroup === '' || $slotIndex === null || !isset($fieldLimits[$slotGroup]) || $slotIndex >= $fieldLimits[$slotGroup]) {
        continue;
    }

    $occupiedSlots[$slotGroup . ':' . $slotIndex] = true;
}

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
    $status = 'inactive';
}

$stmt = $pdo->prepare("
    INSERT INTO team_roster (player_id, roster_role, status, slot_group, slot_index)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        roster_role = VALUES(roster_role),
        status = VALUES(status),
        slot_group = VALUES(slot_group),
        slot_index = VALUES(slot_index)
");
$stmt->execute([$playerId, $role, $status, $newSlotGroup, $newSlotIndex]);

flash('success', $status === 'active' ? 'Jogador adicionado ao campo.' : 'Vaga ocupada. Jogador mantido na reserva.');
redirect(PROJECT_URL . '/admin/meu_time/index.php');
