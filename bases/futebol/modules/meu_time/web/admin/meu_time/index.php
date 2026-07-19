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

function teamEnsurePlayerSchema(PDO $pdo): void
{
    $playerColumns = $pdo->query("SHOW COLUMNS FROM players")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('user_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN user_id INT NULL AFTER id");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_user_id (user_id)");
    }

    if (!in_array('position_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN position_id INT NULL AFTER whatsapp");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_position_id (position_id)");
    }

    if (!in_array('secondary_position_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN secondary_position_id INT NULL AFTER position_id");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_secondary_position (secondary_position_id)");
    }

    if (!in_array('avatar', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN avatar VARCHAR(255) NULL AFTER nickname");
    }

    $positionColumns = $pdo->query("SHOW COLUMNS FROM player_positions")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('code', $positionColumns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN code VARCHAR(20) NULL AFTER id");
    }

    if (!in_array('group_key', $positionColumns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN group_key VARCHAR(40) NULL AFTER name");
    }

    if (!in_array('group_label', $positionColumns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN group_label VARCHAR(80) NULL AFTER group_key");
    }

    if (!in_array('sort_order', $positionColumns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN sort_order INT DEFAULT 0 AFTER status");
    }
}

teamEnsurePlayerSchema($pdo);
teamEnsureRosterSlotSchema($pdo);

$positionsHelperPath = __DIR__ . '/../jogadores/positions_helper.php';
if (file_exists($positionsHelperPath)) {
    require_once $positionsHelperPath;
    if (function_exists('playerEnsureDefaultPositions')) {
        playerEnsureDefaultPositions($pdo);
    }
}

$pdo->exec("INSERT INTO team_profile (id, name) VALUES (1, 'Meu Time') ON DUPLICATE KEY UPDATE id = id");

$team = $pdo->query("SELECT * FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$allPositions = [];
try {
    $allPositions = $pdo->query("
        SELECT code, name, group_key, group_label
        FROM player_positions
        WHERE status = 'active'
        ORDER BY sort_order ASC, name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $allPositions = [];
}

$players = [];
try {
    $players = $pdo->query("
        SELECT
            p.id,
            COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS name,
            pp.name AS position_name,
            pp.code AS position_code,
            pp.group_key,
            pp.group_label,
            pp2.name AS secondary_position_name,
            pp2.code AS secondary_position_code,
            pp2.group_key AS secondary_group_key,
            pp2.group_label AS secondary_group_label,
            COALESCE(p.avatar, u.avatar) AS avatar
        FROM players p
        LEFT JOIN player_positions pp ON pp.id = p.position_id
        LEFT JOIN player_positions pp2 ON pp2.id = p.secondary_position_id
        LEFT JOIN project_users u ON u.id = p.user_id
        LEFT JOIN team_roster tr ON tr.player_id = p.id
        WHERE tr.id IS NULL
          AND p.status = 'active'
        ORDER BY pp.sort_order ASC, p.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $players = [];
}

$roster = $pdo->query("
    SELECT
        tr.*,
        COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
        pp.name AS position_name,
        pp.code AS position_code,
        pp.group_key,
        pp.group_label,
        pp2.name AS secondary_position_name,
        pp2.code AS secondary_position_code,
        pp2.group_key AS secondary_group_key,
        pp2.group_label AS secondary_group_label,
        COALESCE(p.avatar, u.avatar) AS avatar
    FROM team_roster tr
    LEFT JOIN players p ON p.id = tr.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN player_positions pp2 ON pp2.id = p.secondary_position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.status = 'active'
    ORDER BY
        CASE tr.status WHEN 'active' THEN 0 ELSE 1 END,
        COALESCE(tr.updated_at, tr.created_at) ASC,
        tr.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

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

    return '';
}

function teamPositionLabel(string $key): string
{
    return [
        'goleiro' => 'Goleiros',
        'zagueiro' => 'Zagueiros',
        'lateral' => 'Laterais',
        'meia' => 'Meias',
        'ponta' => 'Pontas',
        'atacante' => 'Atacantes',
        'reserva' => 'Reservas',
    ][$key] ?? '';
}

function teamInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $upper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    $first = $substr($parts[0] ?? '', 0, 1);
    $second = $substr($parts[1] ?? '', 0, 1);
    return $upper($first . $second);
}

function teamAvatarHtml(?string $avatar, string $name, string $class): string
{
    if ($avatar !== null && trim($avatar) !== '') {
        $src = PROJECT_URL . '/' . ltrim($avatar, '/');
        return '<img class="' . htmlspecialchars($class) . '" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($name) . '">';
    }

    return '<span class="' . htmlspecialchars($class) . '">' . htmlspecialchars(teamInitials($name)) . '</span>';
}

function teamPositionDisplay(?string $code): string
{
    return preg_replace('/\d+$/', '', (string)$code) ?: '-';
}

function teamCustomSlotSetup(array $team): ?array
{
    $customSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
    $customSlots = is_array($customSlots) ? $customSlots : [];
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

    if (!$customSlots) {
        return null;
    }

    $slots = [];
    $limits = [];
    $addSlot = static function (string $group, int $x, int $y, string $label) use (&$slots, &$limits): void {
        $slots[$group][] = ['x' => $x, 'y' => $y, 'label' => $label];
        $limits[$group] = ($limits[$group] ?? 0) + 1;
    };

    if (!empty($customSlots['GO'])) {
        $addSlot('goleiro', 50, 88, 'GO');
    }

    $zcSlots = array_values(array_filter(['ZC1', 'ZC2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($zcSlots as $index => $code) {
        $x = count($zcSlots) === 1 ? 50 : ($index === 0 ? 38 : 62);
        $addSlot('zagueiro', $x, 72, 'ZC');
    }

    if (!empty($customSlots['LE'])) {
        $addSlot('lateral', 18, 62, 'LE');
    }

    if (!empty($customSlots['LD'])) {
        $addSlot('lateral', 82, 62, 'LD');
    }

    $volSlots = array_values(array_filter(['VOL1', 'VOL2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($volSlots as $index => $code) {
        $x = count($volSlots) === 1 ? 50 : ($index === 0 ? 42 : 58);
        $addSlot('meia', $x, 55, 'VOL');
    }

    $matSlots = array_values(array_filter(['MAT1', 'MAT2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($matSlots as $index => $code) {
        $x = count($matSlots) === 1 ? 50 : ($index === 0 ? 38 : 62);
        $addSlot('meia', $x, 40, 'MAT');
    }

    if (!empty($customSlots['PTE'])) {
        $addSlot('ponta', 24, 24, 'PTE');
    }

    if (!empty($customSlots['PTD'])) {
        $addSlot('ponta', 76, 24, 'PTD');
    }

    $attackSlots = array_values(array_filter(['CA1', 'CA2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($attackSlots as $index => $code) {
        $x = count($attackSlots) === 1 ? 50 : ($index === 0 ? 38 : 62);
        $addSlot('atacante', $x, 20, 'CA');
    }

    $total = array_sum($limits);

    if ($total <= 0) {
        return null;
    }

    return [
        'starters' => $total,
        'limits' => $limits,
        'slots' => $slots,
    ];
}

function teamFieldSetup(string $teamType, array $team = []): array
{
    if ($teamType === 'custom') {
        $customSetup = teamCustomSlotSetup($team);
        if ($customSetup !== null) {
            return $customSetup;
        }
    }

    return match ($teamType) {
        'futsal' => [
            'starters' => 5,
            'limits' => ['goleiro' => 1, 'zagueiro' => 1, 'lateral' => 1, 'meia' => 1, 'atacante' => 1],
            'slots' => [
                'goleiro' => [['x' => 50, 'y' => 88]],
                'zagueiro' => [['x' => 50, 'y' => 68]],
                'lateral' => [['x' => 24, 'y' => 48]],
                'meia' => [['x' => 76, 'y' => 48]],
                'atacante' => [['x' => 50, 'y' => 24]],
            ],
        ],
        'society' => [
            'starters' => 7,
            'limits' => ['goleiro' => 1, 'zagueiro' => 1, 'lateral' => 2, 'meia' => 1, 'ponta' => 1, 'atacante' => 1],
            'slots' => [
                'goleiro' => [['x' => 50, 'y' => 88]],
                'zagueiro' => [['x' => 50, 'y' => 72]],
                'lateral' => [['x' => 20, 'y' => 58], ['x' => 80, 'y' => 58]],
                'meia' => [['x' => 50, 'y' => 38]],
                'ponta' => [['x' => 28, 'y' => 24]],
                'atacante' => [['x' => 50, 'y' => 22]],
            ],
        ],
        'beach' => [
            'starters' => 5,
            'limits' => ['goleiro' => 1, 'zagueiro' => 1, 'lateral' => 1, 'meia' => 1, 'atacante' => 1],
            'slots' => [
                'goleiro' => [['x' => 50, 'y' => 88]],
                'zagueiro' => [['x' => 50, 'y' => 68]],
                'lateral' => [['x' => 24, 'y' => 48]],
                'meia' => [['x' => 76, 'y' => 48]],
                'atacante' => [['x' => 50, 'y' => 24]],
            ],
        ],
        default => [
            'starters' => 11,
            'limits' => ['goleiro' => 1, 'zagueiro' => 2, 'lateral' => 2, 'meia' => 3, 'ponta' => 2, 'atacante' => 1],
            'slots' => [
                'goleiro' => [['x' => 50, 'y' => 88]],
                'zagueiro' => [['x' => 38, 'y' => 72], ['x' => 62, 'y' => 72]],
                'lateral' => [['x' => 18, 'y' => 62], ['x' => 82, 'y' => 62]],
                'meia' => [['x' => 50, 'y' => 55], ['x' => 38, 'y' => 40], ['x' => 62, 'y' => 40]],
                'ponta' => [['x' => 24, 'y' => 24], ['x' => 76, 'y' => 24]],
                'atacante' => [['x' => 50, 'y' => 20]],
            ],
        ],
    };
}

function teamCustomSelectedCodes(array $team): array
{
    $customSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
    $customSlots = is_array($customSlots) ? $customSlots : [];

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

    return array_keys(array_filter($customSlots));
}

function teamAllowedPositionPrefixes(string $teamType, array $team = []): array
{
    if ($teamType === 'custom') {
        $selectedCodes = teamCustomSelectedCodes($team);
        $prefixes = [];

        foreach ($selectedCodes as $code) {
            $prefix = preg_replace('/\d+$/', '', (string)$code);
            if ($prefix !== '') {
                $prefixes[] = $prefix;
            }
        }

        return array_values(array_unique($prefixes));
    }

    return match ($teamType) {
        'futsal', 'beach' => ['GO', 'ZC', 'LE', 'LD', 'VOL', 'MAT', 'CA'],
        default => ['GO', 'ZC', 'LE', 'LD', 'VOL', 'MAT', 'PTE', 'PTD', 'CA'],
    };
}

function teamPlayerIsCompatible(?string $positionCode, array $allowedPrefixes): bool
{
    $positionCode = (string)$positionCode;

    if (str_starts_with($positionCode, 'RES')) {
        return true;
    }

    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($positionCode, $prefix)) {
            return true;
        }
    }

    return false;
}

function teamPlayerHasCompatiblePosition(array $player, array $allowedPrefixes): bool
{
    return teamPlayerIsCompatible($player['position_code'] ?? null, $allowedPrefixes)
        || teamPlayerIsCompatible($player['secondary_position_code'] ?? null, $allowedPrefixes);
}

$teamType = (string)($team['team_type'] ?? 'field');
$teamType = in_array($teamType, ['field', 'custom', 'futsal', 'society', 'beach'], true) ? $teamType : 'field';
$fieldSetup = teamFieldSetup($teamType, $team);
$fieldLimits = $fieldSetup['limits'];
$slotMap = $fieldSetup['slots'];
$allowedPositionPrefixes = teamAllowedPositionPrefixes($teamType, $team);
$startersCount = (int)($team['starters_count'] ?? $fieldSetup['starters']);
$reservesCount = (int)($team['reserves_count'] ?? 7);
if ($teamType !== 'custom') {
    $startersCount = (int)$fieldSetup['starters'];
    $reservesCount = match ($teamType) {
        'field' => 8,
        'society', 'futsal', 'beach' => 7,
        default => $reservesCount,
    };
}

$groupedPlayers = [
    'goleiro' => [],
    'zagueiro' => [],
    'lateral' => [],
    'meia' => [],
    'ponta' => [],
    'atacante' => [],
    'reserva' => [],
];

foreach ($players as $player) {
    if ($teamType !== 'custom' && !teamPlayerHasCompatiblePosition($player, $allowedPositionPrefixes)) {
        continue;
    }

    $positionKey = teamPositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);
    if ($positionKey !== '' && isset($groupedPlayers[$positionKey])) {
        $groupedPlayers[$positionKey][] = $player;
    }
}

$rosterByPosition = [];
$reserveRoster = [];
$fieldPlayers = [];
$occupiedSlotKeys = [];
$fallbackSlotCounts = [];
$groupedReserveRoster = [
    'atacante' => [],
    'ponta' => [],
    'meia' => [],
    'lateral' => [],
    'zagueiro' => [],
    'goleiro' => [],
    'reserva' => [],
];

foreach ($roster as $member) {
    if (($member['status'] ?? '') !== 'active') {
        $reserveRoster[] = $member;
        continue;
    }

    $key = (string)($member['slot_group'] ?? '');
    $slotIndex = $member['slot_index'] !== null ? (int)$member['slot_index'] : null;

    if ($key === '' || $slotIndex === null) {
        $groupKey = teamPositionKey($member['position_name'] ?? '', $member['group_key'] ?? null);
        $code = (string)($member['position_code'] ?? '');
        $slotKey = match (true) {
            str_starts_with($code, 'PTE') => 'ponta:0',
            str_starts_with($code, 'PTD') => 'ponta:1',
            str_starts_with($code, 'LE') => 'lateral:0',
            str_starts_with($code, 'LD') => 'lateral:1',
            str_starts_with($code, 'GO') => 'goleiro:0',
            str_starts_with($code, 'ZC') => 'zagueiro:' . ($fallbackSlotCounts['zagueiro'] ?? 0),
            str_starts_with($code, 'VOL') || str_starts_with($code, 'MAT') => 'meia:' . ($fallbackSlotCounts['meia'] ?? 0),
            str_starts_with($code, 'CA') => 'atacante:0',
            default => $groupKey . ':' . ($fallbackSlotCounts[$groupKey] ?? 0),
        };

        [$key, $slotIndex] = array_pad(explode(':', $slotKey, 2), 2, '0');
        $slotIndex = (int)$slotIndex;
    }

    $slotKey = $key . ':' . $slotIndex;

    if ($key === '' || !isset($fieldLimits[$key])) {
        $reserveRoster[] = $member;
        continue;
    }

    $rosterByPosition[$key] ??= [];

    if ($slotIndex >= $fieldLimits[$key] || isset($occupiedSlotKeys[$slotKey])) {
        $reserveRoster[] = $member;
        continue;
    }

    $occupiedSlotKeys[$slotKey] = true;
    $member['_slot_index'] = $slotIndex;
    $rosterByPosition[$key][] = $member;
    $fallbackSlotCounts[$key] = ($fallbackSlotCounts[$key] ?? 0) + 1;
}

foreach ($slotMap as $key => $slots) {
    foreach (($rosterByPosition[$key] ?? []) as $member) {
        $index = (int)($member['_slot_index'] ?? 0);
        $slot = $slots[$index] ?? null;

        if ($slot === null) {
            $reserveRoster[] = $member;
            continue;
        }

        $fieldPlayers[] = [
            'roster_id' => (int)$member['id'],
            'name' => $member['player_name'] ?? '-',
            'position' => $member['position_name'] ?? '-',
            'position_code' => $member['position_code'] ?? null,
            'avatar' => $member['avatar'] ?? null,
            'x' => $slot['x'],
            'y' => $slot['y'],
        ];
    }
}

foreach ($reserveRoster as $member) {
    if ($teamType !== 'custom' && !teamPlayerHasCompatiblePosition($member, $allowedPositionPrefixes)) {
        continue;
    }

    $positionKey = teamPositionKey($member['position_name'] ?? '', $member['group_key'] ?? null);
    if ($positionKey !== '' && isset($groupedReserveRoster[$positionKey])) {
        $groupedReserveRoster[$positionKey][] = $member;
    }
}

$cardPlayersByPosition = [
    'goleiro' => [],
    'zagueiro' => [],
    'lateral' => [],
    'meia' => [],
    'ponta' => [],
    'atacante' => [],
    'reserva' => [],
];

foreach ($groupedPlayers as $positionKey => $positionPlayers) {
    foreach ($positionPlayers as $player) {
        $cardPlayersByPosition[$positionKey][] = [
            'id' => (int)$player['id'],
            'name' => $player['name'] ?? '-',
            'avatar' => $player['avatar'] ?? null,
            'position_code' => $player['position_code'] ?? null,
        ];
    }
}

foreach ($groupedReserveRoster as $positionKey => $positionPlayers) {
    foreach ($positionPlayers as $member) {
        $cardPlayersByPosition[$positionKey][] = [
            'id' => (int)$member['player_id'],
            'name' => $member['player_name'] ?? '-',
            'avatar' => $member['avatar'] ?? null,
            'position_code' => $member['position_code'] ?? null,
        ];
    }
}

$filledPositionCodes = [];
foreach ($players as $player) {
    $code = (string)($player['position_code'] ?? '');
    if ($code !== '') {
        $filledPositionCodes[$code] = true;
    }
}
foreach ($roster as $member) {
    $code = (string)($member['position_code'] ?? '');
    if ($code !== '') {
        $filledPositionCodes[$code] = true;
    }
}

$emptyPositionMarksByPosition = [
    'goleiro' => [],
    'zagueiro' => [],
    'lateral' => [],
    'meia' => [],
    'ponta' => [],
    'atacante' => [],
    'reserva' => [],
];

foreach ($allPositions as $position) {
    $code = (string)($position['code'] ?? '');
    if ($code === '' || isset($filledPositionCodes[$code])) {
        continue;
    }
    if ($teamType !== 'custom' && !teamPlayerIsCompatible($code, $allowedPositionPrefixes)) {
        continue;
    }

    $positionKey = teamPositionKey($position['name'] ?? '', $position['group_key'] ?? null);
    if ($positionKey !== '' && isset($emptyPositionMarksByPosition[$positionKey])) {
        $emptyPositionMarksByPosition[$positionKey][] = teamPositionDisplay($code);
    }
}

$hasCardPlayers = false;
foreach ($cardPlayersByPosition as $positionPlayers) {
    if (!empty($positionPlayers)) {
        $hasCardPlayers = true;
        break;
    }
}

$teamTypeLabel = match ($teamType) {
    'futsal' => 'Salão',
    'society' => 'Society',
    'beach' => 'Areia',
    'field' => 'Campo',
    'custom' => 'Personalizado',
    default => 'Outro',
};

$teamName = trim((string)($team['name'] ?? ''));
$teamName = $teamName !== '' ? $teamName : 'Meu Time';
$title = $teamName;

ob_start();
?>

<div class="c-page c-team-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($teamName) ?></h1>
            <p class="c-page-subtitle">Escalação e organização do elenco</p>
        </div>

        <div class="c-team-header-actions">
            <a href="<?= PROJECT_URL ?>/admin/meu_time/config.php" class="c-btn-secondary">
                Configurar Time
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-team-layout">
            <div class="c-card c-team-field-card">
                <h3>Elenco titular</h3>

                <div class="c-team-field c-team-field--<?= htmlspecialchars($teamType) ?>">
                    <label class="c-team-toggle-x">
                        <input type="checkbox" data-team-hide-x>
                        <span>Ocultar x</span>
                    </label>
                    <div class="c-team-field-line c-team-field-center"></div>
                    <div class="c-team-field-circle c-team-field-circle-center"></div>
                    <div class="c-team-field-spot c-team-field-spot-center"></div>
                    <div class="c-team-field-spot c-team-field-spot-top"></div>
                    <div class="c-team-field-spot c-team-field-spot-bottom"></div>
                    <div class="c-team-field-box c-team-field-box-top"></div>
                    <div class="c-team-field-box c-team-field-box-bottom"></div>
                    <div class="c-team-field-goal c-team-field-goal-top"></div>
                    <div class="c-team-field-goal c-team-field-goal-bottom"></div>

                    <?php foreach ($slotMap as $positionKey => $slots): ?>
                        <?php foreach ($slots as $slotIndex => $slot): ?>
                            <?php $isSlotOccupied = isset($occupiedSlotKeys[$positionKey . ':' . $slotIndex]); ?>
                            <?php if ($isSlotOccupied): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <div
                                class="c-team-position-slot c-team-position-slot--empty"
                                style="left:<?= (int)$slot['x'] ?>%;top:<?= (int)$slot['y'] ?>%;"
                            >
                                <?= htmlspecialchars(teamPositionDisplay((string)($slot['label'] ?? match ($positionKey) {
                                    'goleiro' => 'GO',
                                    'zagueiro' => 'ZC',
                                    'lateral' => $slotIndex === 0 ? 'LE' : 'LD',
                                    'meia' => $slotIndex === 0 ? 'VOL' : 'MAT',
                                    'ponta' => $slotIndex === 0 ? 'PTE' : 'PTD',
                                    'atacante' => 'CA',
                                    default => '',
                                }))) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>

                    <?php foreach ($fieldPlayers as $fieldPlayer): ?>
                        <div class="c-team-token" style="left:<?= (int)$fieldPlayer['x'] ?>%;top:<?= (int)$fieldPlayer['y'] ?>%;">
                                <?= teamAvatarHtml($fieldPlayer['avatar'] ?? null, $fieldPlayer['name'], 'c-team-token-avatar') ?>
                                <strong title="<?= htmlspecialchars($fieldPlayer['name']) ?>"><?= htmlspecialchars($fieldPlayer['name']) ?></strong>
                                <span><?= htmlspecialchars(teamPositionDisplay($fieldPlayer['position_code'] ?? null)) ?></span>
                                <form action="<?= PROJECT_URL ?>/admin/meu_time/roster_remove.php?id=<?= (int)$fieldPlayer['roster_id'] ?>" method="POST">
                                    <?= csrf_field(); ?>
                                    <button title="Retirar do campo">x</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

            <div class="c-team-left-column">
                <div class="c-card c-team-reserve-card">
                    <h3>Elenco reserva</h3>

                    <div class="c-team-position-groups">
                        <?php foreach ($cardPlayersByPosition as $positionKey => $positionPlayers): ?>
                            <?php if (empty($positionPlayers)): ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <div class="c-team-position-group">
                                <strong><?= htmlspecialchars(teamPositionLabel($positionKey)) ?></strong>
                                <div class="c-team-avatar-grid">
                                    <?php foreach ($positionPlayers as $player): ?>
                                        <form action="<?= PROJECT_URL ?>/admin/meu_time/roster_store.php" method="POST">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="player_id" value="<?= (int)$player['id'] ?>">
                                            <input type="hidden" name="roster_role" value="player">
                                            <button class="c-team-avatar-card" title="Adicionar <?= htmlspecialchars($player['name']) ?>">
                                                <?= teamAvatarHtml($player['avatar'] ?? null, $player['name'], 'c-team-avatar-initial') ?>
                                                <span><?= htmlspecialchars($player['name']) ?></span>
                                                <small><?= htmlspecialchars(teamPositionDisplay($player['position_code'] ?? null)) ?></small>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$hasCardPlayers): ?>
                        <p class="c-team-empty-note">Nenhum jogador fora do campo.</p>
                    <?php endif; ?>
                </div>
            </div>

                <div class="c-team-info-card">

                    <div class="c-team-info-list">
                        <div>
                            <span>Time</span>
                            <strong><?= htmlspecialchars($team['name'] ?? 'Meu Time') ?></strong>
                        </div>
                        <div>
                            <span>Técnico</span>
                            <strong><?= htmlspecialchars($team['responsible_name'] ?? '-') ?></strong>
                        </div>
                        <div>
                            <span>Tipo</span>
                            <strong><?= htmlspecialchars($teamTypeLabel) ?></strong>
                        </div>
                        <div>
                            <span>Planejado</span>
                            <strong><?= $startersCount ?> titulares + <?= $reservesCount ?> reservas</strong>
                        </div>
                        <div>
                            <span>Em campo</span>
                            <strong><?= count($fieldPlayers) ?></strong>
                        </div>
                        <div>
                            <span>Reservas</span>
                            <strong><?= count($reserveRoster) ?></strong>
                        </div>
                    </div>
                </div>

        </div>
    </div>
</div>

<style>
body:has(.c-team-page) .c-layout {
    grid-template-columns: 220px minmax(0, 1fr);
}

.c-team-layout {
    display: grid;
    grid-template-columns: minmax(270px, .62fr) minmax(500px, 1.38fr);
    gap: 12px;
    align-items: start;
    --team-field-height: clamp(230px, 23vw, 286px);
}

.c-team-header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.c-team-left-column {
    display: grid;
    gap: 12px;
}

@media (min-width: 901px) {
    .c-team-left-column > .c-card,
    .c-team-layout > .c-card:not(.c-team-info-card) {
        min-height: auto;
    }
}

.c-team-position-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(112px, 1fr));
    gap: 6px;
    align-items: start;
}

.c-team-left-column > .c-card,
.c-team-layout > .c-card:not(.c-team-info-card) {
    border-color: transparent;
    background: transparent;
    padding: 0;
}

.c-team-field-card {
    justify-self: stretch;
}

.c-team-empty-note {
    margin: 10px 0 0;
    color: var(--text-secondary);
}

.c-team-position-group,
.c-team-reserve-block {
    display: grid;
    gap: 5px;
    align-items: start;
    align-content: start;
}

.c-team-position-group > strong,
.c-team-reserve-block > strong {
    display: block;
    min-height: 18px;
    padding: 4px 3px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: rgba(255,255,255,.04);
    font-size: 10px;
    line-height: 1.05;
    opacity: .82;
    text-align: center;
}

.c-team-avatar-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px;
    align-content: start;
}

.c-team-reserve-watermark {
    display: grid;
    min-height: 58px;
    place-items: center;
    color: color-mix(in srgb, var(--text-primary) 28%, transparent);
    background: transparent;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.1;
    text-align: center;
    text-transform: uppercase;
}

.c-team-avatar-grid form {
    min-width: 0;
}

.c-team-avatar-card {
    display: grid;
    grid-template-columns: 1fr;
    grid-template-areas:
        "avatar"
        "name"
        "position";
    width: 100%;
    min-height: 56px;
    gap: 2px;
    align-items: center;
    justify-items: center;
    padding: 4px 2px;
    border: 1px solid transparent;
    background: transparent;
    color: inherit;
    cursor: pointer;
    text-align: center;
}

.c-team-avatar-card--muted {
    cursor: pointer;
    opacity: .86;
}

.c-team-avatar-card--removable {
    position: relative;
}

.c-team-avatar-card--removable form {
    position: absolute;
    right: 0;
    top: 0;
    opacity: 0;
    transition: opacity .15s ease;
}

.c-team-reserve-item {
    position: relative;
    min-width: 0;
}

.c-team-reserve-add {
    margin: 0;
}

.c-team-reserve-add .c-team-avatar-card {
    border-color: transparent;
}

.c-team-reserve-remove {
    position: absolute;
    right: 2px;
    top: 2px;
    opacity: 0;
    transition: opacity .15s ease;
}

.c-team-reserve-item:hover .c-team-reserve-remove {
    opacity: 1;
}

.c-team-reserve-remove button {
    width: 16px;
    height: 16px;
    padding: 0;
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    background: rgba(120, 18, 18, .92);
    cursor: pointer;
    line-height: 14px;
}

.c-team-avatar-card--removable:hover form {
    opacity: 1;
}

.c-team-avatar-card--removable button {
    width: 16px;
    height: 16px;
    padding: 0;
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    background: rgba(120, 18, 18, .92);
    cursor: pointer;
    line-height: 14px;
}

.c-team-avatar-card:hover {
    border-color: rgba(76, 139, 245, .65);
    background: rgba(76, 139, 245, .1);
}

.c-team-avatar-initial {
    display: grid;
    grid-area: avatar;
    width: clamp(28px, 2.3vw, 34px);
    height: clamp(28px, 2.3vw, 34px);
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background:
        radial-gradient(circle at 35% 28%, rgba(255,255,255,.95) 0 6px, transparent 7px),
        linear-gradient(145deg, #f7d4bf 0 45%, #2f7c44 46% 100%);
    border: 1px solid rgba(255,255,255,.9);
    box-shadow: 0 2px 6px rgba(0,0,0,.35);
    font-size: 9px;
    font-weight: 700;
    object-fit: cover;
    text-shadow: none;
}

.c-team-reserve-card .c-team-avatar-initial {
    border-color: rgba(245, 158, 11, .95);
    box-shadow:
        0 0 0 1px rgba(245, 158, 11, .34),
        0 2px 6px rgba(0,0,0,.35);
}

img.c-team-avatar-initial {
    display: block;
}

.c-team-avatar-card span:not(.c-team-avatar-initial),
.c-team-avatar-card small {
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-team-avatar-card span:not(.c-team-avatar-initial) {
    grid-area: name;
    margin-top: 1px;
    font-size: 9px;
    line-height: 1.05;
    font-weight: 700;
    text-shadow: 0 1px 3px rgba(0,0,0,.75);
}

.c-team-avatar-card small {
    grid-area: position;
    opacity: .7;
    font-size: 8px;
    line-height: 1.05;
}

.c-team-field {
    position: relative;
    width: 100%;
    max-width: 260px;
    aspect-ratio: 3 / 4;
    min-height: var(--team-field-height);
    margin: 0;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.28);
    background:
        radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.05) 1px, transparent 1px),
        #145c38;
    background-size: 48px 48px;
}

.c-team-toggle-x {
    position: absolute;
    top: 6px;
    right: 6px;
    z-index: 6;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 5px;
    border: 1px solid rgba(148, 163, 184, .28);
    border-radius: 4px;
    background: rgba(15, 23, 42, .62);
    color: rgba(226, 232, 240, .82);
    font-size: 9px;
    line-height: 1;
    cursor: pointer;
}

.c-team-toggle-x input {
    width: 11px;
    height: 11px;
    margin: 0;
}

.c-team-page.is-hiding-field-actions .c-team-token form {
    display: none;
}

.c-team-info-list {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 7px;
}

.c-team-info-card {
    grid-column: 1 / -1;
    align-self: start;
    margin-top: 0;
}

.c-team-reserve-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
}

.c-team-reserve-grid .c-team-avatar-card {
    min-height: 118px;
}

.c-team-reserve-grid .c-team-avatar-initial {
    width: 68px;
    height: 68px;
}

.c-team-info-list div {
    padding: 7px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: transparent;
}

.c-team-info-list span,
.c-team-info-list strong {
    display: block;
}

.c-team-info-list span {
    margin-bottom: 5px;
    font-size: 10px;
    opacity: .72;
}

.c-team-info-list strong {
    font-size: 12px;
    line-height: 1.15;
}

.c-team-field--futsal {
    background-color: #284b63;
}

.c-team-field--society {
    background-color: #196f4a;
}

.c-team-field--beach {
    background-color: #9f8149;
}

.c-team-field-line {
    position: absolute;
    left: 0;
    right: 0;
    height: 1px;
    background: rgba(255,255,255,.55);
}

.c-team-field-center {
    top: 50%;
}

.c-team-field-circle {
    position: absolute;
    width: 82px;
    height: 82px;
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
}

.c-team-field-circle-center {
    left: 50%;
    top: 50%;
}

.c-team-field-spot {
    position: absolute;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,.65);
    transform: translate(-50%, -50%);
    pointer-events: none;
}

.c-team-field-spot-center {
    left: 50%;
    top: 50%;
}

.c-team-field-spot-top {
    left: 50%;
    top: 17%;
}

.c-team-field-spot-bottom {
    left: 50%;
    top: 83%;
}

.c-team-field-box {
    position: absolute;
    left: 28%;
    width: 44%;
    height: 60px;
    border: 1px solid rgba(255,255,255,.55);
}

.c-team-field-box-top {
    top: 0;
    border-top: 0;
}

.c-team-field-box-bottom {
    bottom: 0;
    border-bottom: 0;
}

.c-team-field-goal {
    position: absolute;
    left: 43%;
    width: 14%;
    height: 14px;
    border: 1px solid rgba(255,255,255,.5);
    background: rgba(255,255,255,.04);
}

.c-team-field-goal-top {
    top: 0;
    border-top: 0;
}

.c-team-field-goal-bottom {
    bottom: 0;
    border-bottom: 0;
}

.c-team-position-slot {
    position: absolute;
    z-index: 1;
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border: 1px dashed rgba(255,255,255,.26);
    border-radius: 50%;
    color: rgba(255,255,255,.46);
    background: rgba(2, 10, 24, .10);
    font-size: 10px;
    font-weight: 700;
    transform: translate(-50%, -50%);
    pointer-events: none;
}

.c-team-position-slot--empty {
    border-color: rgba(255,255,255,.34);
    color: rgba(255,255,255,.58);
    background: radial-gradient(circle, rgba(255,255,255,.11), rgba(2,10,24,.08) 62%);
    box-shadow: inset 0 0 0 1px rgba(2,10,24,.14);
}

.c-team-position-slot--occupied {
    opacity: .28;
}

.c-team-token {
    position: absolute;
    z-index: 2;
    transform: translate(-50%, calc(-50% + 8px));
    display: grid;
    justify-items: center;
    width: 66px;
    padding: 0;
    text-align: center;
    color: #fff;
    background: transparent;
    border: 0;
    text-shadow: 0 1px 3px rgba(0,0,0,.9);
}

.c-team-token form {
    position: absolute;
    right: 5px;
    top: -4px;
    opacity: 0;
    transition: opacity .15s ease;
}

.c-team-token:hover form {
    opacity: 1;
}

.c-team-token button {
    width: 16px;
    height: 16px;
    padding: 0;
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    background: rgba(120, 18, 18, .92);
    cursor: pointer;
    line-height: 16px;
}

.c-team-token strong,
.c-team-token span {
    display: block;
    max-width: 66px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-team-token strong {
    margin-top: 3px;
    font-size: 9px;
    line-height: 1.05;
}

.c-team-token span {
    margin-top: 1px;
    font-size: 8px;
    line-height: 1.05;
    opacity: .86;
}

.c-team-token-avatar {
    width: 36px;
    height: 36px;
    margin-bottom: 2px;
    border-radius: 50%;
    display: grid !important;
    place-items: center;
    background:
        radial-gradient(circle at 35% 28%, rgba(255,255,255,.95) 0 7px, transparent 8px),
        linear-gradient(145deg, #f7d4bf 0 45%, #2f7c44 46% 100%);
    border: 2px solid rgba(34, 197, 94, .92);
    box-shadow:
        0 0 0 1px rgba(34, 197, 94, .26),
        0 2px 5px rgba(0,0,0,.45);
    color: #08111f;
    font-size: 10px !important;
    font-weight: 700;
    opacity: 1 !important;
    text-shadow: none;
    object-fit: cover;
}

img.c-team-token-avatar {
    display: block !important;
}

@media (max-width: 1220px) {
    .c-team-layout {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }

    .c-team-info-card {
        grid-column: 1 / -1;
    }
}

@media (max-width: 900px) {
    .c-team-layout {
        grid-template-columns: 1fr;
    }

    .c-team-header-actions {
        justify-content: flex-start;
        width: 100%;
    }

    .c-team-info-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-team-position-groups {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .c-team-avatar-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
    }

    .c-team-position-group > strong {
        min-height: 22px;
    }
}

@media (max-width: 900px) {

    .c-team-token form,
    .c-team-reserve-remove,
    .c-team-avatar-card--removable form {
        opacity: 1;
    }

    .c-team-field {
        max-width: 100%;
        min-height: auto;
    }

    .c-team-token-avatar {
        width: 48px;
        height: 48px;
        font-size: 12px;
    }

    .c-team-reserve-grid .c-team-avatar-initial {
        width: 78px;
        height: 78px;
        font-size: 12px;
    }
}

@media (max-width: 420px) {
    .c-team-position-groups {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-team-avatar-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-team-avatar-card {
        min-height: 62px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('.c-team-page');
    const toggles = Array.from(document.querySelectorAll('[data-team-hide-x]'));
    const storageKey = 'meuTime.hideFieldActions:' + window.location.pathname.split('/web/admin/')[0];
    if (!page || toggles.length === 0) {
        return;
    }

    function setHidden(checked) {
        page.classList.toggle('is-hiding-field-actions', checked);
        toggles.forEach(function (toggle) {
            toggle.checked = checked;
        });
    }

    setHidden(localStorage.getItem(storageKey) === '1');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            setHidden(toggle.checked);
            localStorage.setItem(storageKey, toggle.checked ? '1' : '0');
        });
    });
});
</script>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = false;

require APP_PATH . '/views/layout_admin.php';
