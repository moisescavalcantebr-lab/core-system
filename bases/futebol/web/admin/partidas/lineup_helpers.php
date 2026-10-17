<?php
declare(strict_types=1);

function matchLineupEnsureSchema(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM matches")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('lineup_snapshot_at', $columns, true)) {
        $pdo->exec('ALTER TABLE matches ADD COLUMN lineup_snapshot_at DATETIME NULL');
    }

    if (!in_array('lineup_mode', $columns, true)) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN lineup_mode ENUM('team_roster','arrival_order','automatic') NOT NULL DEFAULT 'team_roster' AFTER status");
    } else {
        $pdo->exec("ALTER TABLE matches MODIFY lineup_mode ENUM('team_roster','arrival_order','automatic') NOT NULL DEFAULT 'team_roster'");
    }

    if (!in_array('match_fee', $columns, true)) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN match_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER lineup_mode");
    }

    if (!in_array('field_type_snapshot', $columns, true)) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN field_type_snapshot VARCHAR(40) NULL AFTER match_fee");
    }

    if (!in_array('field_slots_snapshot_json', $columns, true)) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN field_slots_snapshot_json TEXT NULL AFTER field_type_snapshot");
    }

    $playerColumns = $pdo->query("SHOW COLUMNS FROM players")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('secondary_position_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN secondary_position_id INT NULL AFTER position_id");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_secondary_position (secondary_position_id)");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS match_confirmations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            player_id INT NOT NULL,
            status ENUM('pending','confirmed','declined') NOT NULL DEFAULT 'pending',
            payment_status ENUM('not_required','pending','paid') NOT NULL DEFAULT 'not_required',
            payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            finance_entry_id INT NULL,
            notes TEXT NULL,
            paid_at DATETIME NULL,
            confirmed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_match_player_confirmation (match_id, player_id),
            INDEX(match_id),
            INDEX(player_id),
            INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $confirmationColumns = $pdo->query("SHOW COLUMNS FROM match_confirmations")->fetchAll(PDO::FETCH_COLUMN);

    $pdo->exec("ALTER TABLE match_confirmations MODIFY status ENUM('pending','confirmed','declined') NOT NULL DEFAULT 'pending'");

    if (!in_array('payment_status', $confirmationColumns, true)) {
        $pdo->exec("ALTER TABLE match_confirmations ADD COLUMN payment_status ENUM('not_required','pending','paid') NOT NULL DEFAULT 'not_required' AFTER status");
    }

    if (!in_array('payment_amount', $confirmationColumns, true)) {
        $pdo->exec("ALTER TABLE match_confirmations ADD COLUMN payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER payment_status");
    }

    if (!in_array('finance_entry_id', $confirmationColumns, true)) {
        $pdo->exec("ALTER TABLE match_confirmations ADD COLUMN finance_entry_id INT NULL AFTER payment_amount");
        $pdo->exec("ALTER TABLE match_confirmations ADD INDEX idx_match_confirmations_finance_entry (finance_entry_id)");
    }

    if (!in_array('paid_at', $confirmationColumns, true)) {
        $pdo->exec("ALTER TABLE match_confirmations ADD COLUMN paid_at DATETIME NULL AFTER notes");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS match_confirmation_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            player_id INT NOT NULL,
            status ENUM('confirmed','declined') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(match_id),
            INDEX(player_id),
            INDEX(status),
            INDEX(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS match_lineup (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            player_id INT NOT NULL,
            status ENUM('starter','reserve') NOT NULL DEFAULT 'reserve',
            slot_group VARCHAR(40) NULL,
            slot_index INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_match_player_lineup (match_id, player_id),
            INDEX(match_id),
            INDEX(player_id),
            INDEX(status),
            INDEX(slot_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS match_attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            player_id INT NOT NULL,
            status ENUM('present','excused_absence','no_response','confirmed_absent','justified_absent') NOT NULL DEFAULT 'no_response',
            points DECIMAL(4,1) NOT NULL DEFAULT 0.0,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_match_player_attendance (match_id, player_id),
            INDEX(match_id),
            INDEX(player_id),
            INDEX(status),
            INDEX(points)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $lineupColumns = $pdo->query("SHOW COLUMNS FROM match_lineup")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('lineup_team', $lineupColumns, true)) {
        $pdo->exec("ALTER TABLE match_lineup ADD COLUMN lineup_team ENUM('team_1','team_2') NULL AFTER status");
        $pdo->exec("ALTER TABLE match_lineup ADD INDEX idx_match_lineup_team (lineup_team)");
    }

    if (!in_array('override_position_id', $lineupColumns, true)) {
        $pdo->exec("ALTER TABLE match_lineup ADD COLUMN override_position_id INT NULL AFTER slot_index");
        $pdo->exec("ALTER TABLE match_lineup ADD INDEX idx_match_lineup_override_position (override_position_id)");
    }
}

function matchLineupModeLabel(?string $mode): string
{
    return match ($mode) {
        'arrival_order' => 'Ordem de confirmação',
        'automatic' => 'Automático',
        default => 'Seguir Meu Time',
    };
}

function matchLineupInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $upper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    return $upper($substr($parts[0] ?? '', 0, 1) . $substr($parts[1] ?? '', 0, 1));
}

function matchLineupAvatar(?string $avatar, string $name, string $class): string
{
    if ($avatar !== null && trim($avatar) !== '') {
        return '<img class="' . htmlspecialchars($class) . '" src="' . htmlspecialchars(PROJECT_URL . '/' . ltrim($avatar, '/')) . '" alt="' . htmlspecialchars($name) . '">';
    }
    return '<span class="' . htmlspecialchars($class) . '">' . htmlspecialchars(matchLineupInitials($name)) . '</span>';
}

function matchLineupPositionDisplay(?string $code): string
{
    return preg_replace('/\d+$/', '', (string)$code) ?: '-';
}

function matchLineupFieldPosition(array $slots, array $player): string
{
    $group = (string)($player['slot_group'] ?? '');
    $index = $player['slot_index'] ?? null;
    $slot = $index !== null ? ($slots[$group][(int)$index] ?? null) : null;
    if ($slot === null) return matchLineupPositionDisplay($player['position_code'] ?? null);
    return matchLineupPositionDisplay($slot['label'] ?? match ($group) {
        'goleiro' => 'GO', 'zagueiro' => 'ZC',
        'lateral' => (int)$index === 0 ? 'LE' : 'LD',
        'meia' => (int)$index === 0 ? 'VOL' : 'MAT',
        'ponta' => (int)$index === 0 ? 'PTE' : 'PTD',
        'atacante' => 'CA', default => $player['position_code'] ?? null,
    });
}

function matchLineupPositionKey(?string $position, ?string $groupKey = null): string
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

    return 'outro';
}

function matchLineupIsReservePosition(array $player): bool
{
    $code = strtoupper((string)($player['position_code'] ?? ''));
    $groupKey = strtolower((string)($player['group_key'] ?? ''));

    return str_starts_with($code, 'RES') || $groupKey === 'reserva';
}

function matchLineupNormalizeCustomSlots(?string $customSlotsJson): array
{
    $customSlots = json_decode((string)$customSlotsJson, true);
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

    return array_filter($customSlots);
}

function matchLineupCustomFieldSetup(?string $customSlotsJson): ?array
{
    $customSlots = matchLineupNormalizeCustomSlots($customSlotsJson);

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
    foreach ($zcSlots as $index => $_code) {
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
    foreach ($volSlots as $index => $_code) {
        $x = count($volSlots) === 1 ? 50 : ($index === 0 ? 42 : 58);
        $addSlot('meia', $x, 55, 'VOL');
    }

    $matSlots = array_values(array_filter(['MAT1', 'MAT2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($matSlots as $index => $_code) {
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
    foreach ($attackSlots as $index => $_code) {
        $x = count($attackSlots) === 1 ? 50 : ($index === 0 ? 38 : 62);
        $addSlot('atacante', $x, 20, 'CA');
    }

    if (array_sum($limits) <= 0) {
        return null;
    }

    return [
        'limits' => $limits,
        'slots' => $slots,
    ];
}

function matchLineupFieldSetup(string $teamType, ?string $customSlotsJson = null): array
{
    if ($teamType === 'custom') {
        $customSetup = matchLineupCustomFieldSetup($customSlotsJson);
        if ($customSetup !== null) {
            return $customSetup;
        }
    }

    return match ($teamType) {
        'futsal', 'beach' => [
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
        default => [
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

function matchLineupCurrentFieldSnapshot(PDO $pdo): array
{
    $team = $pdo->query("SELECT team_type, custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'team_type' => (string)($team['team_type'] ?? 'field'),
        'custom_slots_json' => $team['custom_slots_json'] ?? null,
    ];
}

function matchLineupSaveFieldSnapshot(PDO $pdo, int $matchId, bool $force = false): void
{
    if ($matchId <= 0) {
        return;
    }

    if (!$force) {
        $stmt = $pdo->prepare("SELECT field_type_snapshot, field_slots_snapshot_json FROM matches WHERE id = ? LIMIT 1");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!empty($match['field_type_snapshot'])) {
            return;
        }
    }

    $snapshot = matchLineupCurrentFieldSnapshot($pdo);
    $stmt = $pdo->prepare("UPDATE matches SET field_type_snapshot = ?, field_slots_snapshot_json = ? WHERE id = ?");
    $stmt->execute([
        $snapshot['team_type'],
        $snapshot['custom_slots_json'],
        $matchId,
    ]);
}

function matchLineupOwnTeam(PDO $pdo): array
{
    require_once __DIR__ . '/../meu_time/formations_helpers.php';
    require_once __DIR__ . '/../meu_time/lineup_helpers.php';
    teamEnsureFormationsSchema($pdo);
    return $pdo->query("SELECT * FROM team_profile WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
}

function matchLineupTrainingFieldSnapshot(PDO $pdo): array
{
    return matchLineupCurrentFieldSnapshot($pdo);
}

function matchLineupSaveTrainingFieldSnapshot(PDO $pdo, int $matchId, bool $force = false): void
{
    if ($matchId <= 0) {
        return;
    }

    if (!$force) {
        $stmt = $pdo->prepare("SELECT field_type_snapshot FROM matches WHERE id = ? LIMIT 1");
        $stmt->execute([$matchId]);
        if (!empty($stmt->fetchColumn())) {
            return;
        }
    }

    $snapshot = matchLineupTrainingFieldSnapshot($pdo);
    $stmt = $pdo->prepare("UPDATE matches SET field_type_snapshot = ?, field_slots_snapshot_json = ? WHERE id = ?");
    $stmt->execute([
        $snapshot['team_type'],
        $snapshot['custom_slots_json'],
        $matchId,
    ]);
}

function matchLineupFieldSetupForMatch(PDO $pdo, array $match): array
{
    if (!empty($match['field_type_snapshot'])) {
        return matchLineupFieldSetup((string)$match['field_type_snapshot'], $match['field_slots_snapshot_json'] ?? null);
    }

    $snapshot = matchLineupCurrentFieldSnapshot($pdo);

    return matchLineupFieldSetup($snapshot['team_type'], $snapshot['custom_slots_json']);
}

function matchLineupCandidateSlots(array $player, array $limits): array
{
    if (matchLineupIsReservePosition($player)) {
        return [];
    }

    $code = (string)($player['position_code'] ?? '');
    $groupKey = matchLineupPositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);

    if (str_starts_with($code, 'PTE')) return [['ponta', 0]];
    if (str_starts_with($code, 'PTD')) return [['ponta', 1]];
    if (str_starts_with($code, 'LE')) return [['lateral', 0]];
    if (str_starts_with($code, 'LD')) return [['lateral', 1]];
    if (str_starts_with($code, 'GO')) return [['goleiro', 0]];
    if (str_starts_with($code, 'CA')) return [['atacante', 0]];
    if (str_starts_with($code, 'VOL')) return [['meia', 0], ['meia', 1], ['meia', 2]];
    if (str_starts_with($code, 'MAT')) return [['meia', 1], ['meia', 2], ['meia', 0]];

    $candidates = [];
    for ($index = 0; $index < ($limits[$groupKey] ?? 0); $index++) {
        $candidates[] = [$groupKey, $index];
    }

    return $candidates;
}

function matchLineupCandidateSlotsForSetup(array $player, array $setup): array
{
    if (matchLineupIsReservePosition($player)) {
        return [];
    }

    $slots = $setup['slots'] ?? [];
    $limits = $setup['limits'] ?? [];
    $code = preg_replace('/\d+$/', '', (string)($player['position_code'] ?? ''));
    $groupKey = matchLineupPositionKey($player['position_name'] ?? '', $player['group_key'] ?? null);
    $candidates = [];

    $addByLabel = static function (string $label) use (&$candidates, $slots): void {
        foreach ($slots as $group => $groupSlots) {
            foreach ($groupSlots as $index => $slot) {
                if (($slot['label'] ?? '') === $label) {
                    $candidates[] = [$group, (int)$index];
                }
            }
        }
    };

    $addGroup = static function (string $group) use (&$candidates, $slots): void {
        foreach (($slots[$group] ?? []) as $index => $_slot) {
            $candidates[] = [$group, (int)$index];
        }
    };

    match ($code) {
        'GO' => $addByLabel('GO'),
        'LE' => $addByLabel('LE'),
        'LD' => $addByLabel('LD'),
        'PTE' => $addByLabel('PTE'),
        'PTD' => $addByLabel('PTD'),
        'CA' => $addByLabel('CA'),
        'VOL' => $addByLabel('VOL'),
        'MAT' => $addByLabel('MAT'),
        default => null,
    };

    if ($code === 'VOL') {
        $addByLabel('MAT');
    } elseif ($code === 'MAT') {
        $addByLabel('VOL');
    }

    if (empty($candidates)) {
        if ($groupKey !== 'outro') {
            $addGroup($groupKey);
        }

        if (empty($candidates)) {
            return matchLineupCandidateSlots($player, $limits);
        }
    }

    $uniqueCandidates = [];
    foreach ($candidates as $candidate) {
        $uniqueCandidates[$candidate[0] . ':' . $candidate[1]] = $candidate;
    }

    return array_values($uniqueCandidates);
}

function matchLineupPositionSlots(PDO $pdo, ?int $positionId, array $limits): array
{
    if ($positionId === null || $positionId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT name AS position_name, code AS position_code, group_key FROM player_positions WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$positionId]);
    $position = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$position) {
        return [];
    }

    return matchLineupCandidateSlots($position, $limits);
}

function matchLineupPositionSlotsForSetup(PDO $pdo, ?int $positionId, array $setup): array
{
    if ($positionId === null || $positionId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT name AS position_name, code AS position_code, group_key FROM player_positions WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$positionId]);
    $position = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$position) {
        return [];
    }

    return matchLineupCandidateSlotsForSetup($position, $setup);
}

function matchLineupPositionIsReserve(PDO $pdo, ?int $positionId): bool
{
    if ($positionId === null || $positionId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT code AS position_code, group_key FROM player_positions WHERE id = ? LIMIT 1");
    $stmt->execute([$positionId]);
    $position = $stmt->fetch(PDO::FETCH_ASSOC);

    return $position ? matchLineupIsReservePosition($position) : false;
}

function matchLineupFlatSlots(array $setup): array
{
    $slots = [];

    foreach (($setup['slots'] ?? []) as $group => $items) {
        foreach ($items as $index => $_slot) {
            $slots[] = [$group, (int)$index];
        }
    }

    return $slots;
}

function matchLineupOccupiedSlots(PDO $pdo, int $matchId, ?string $lineupTeam = null): array
{
    $occupied = [];
    $params = [$matchId];
    $whereTeam = '';

    if ($lineupTeam !== null) {
        $whereTeam = " AND lineup_team = ?";
        $params[] = $lineupTeam;
    }

    $stmt = $pdo->prepare("SELECT lineup_team, slot_group, slot_index FROM match_lineup WHERE match_id = ? AND status = 'starter'{$whereTeam}");
    $stmt->execute($params);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $slot) {
        if ($slot['slot_group'] !== null && $slot['slot_index'] !== null) {
            $prefix = $lineupTeam === null ? '' : ((string)($slot['lineup_team'] ?? 'team_1') . ':');
            $occupied[$prefix . $slot['slot_group'] . ':' . (int)$slot['slot_index']] = true;
        }
    }

    return $occupied;
}

function matchLineupSavePlayer(PDO $pdo, int $matchId, int $playerId, string $status, ?string $slotGroup, ?int $slotIndex, ?int $overridePositionId = null, ?string $lineupTeam = 'team_1'): void
{
    $stmt = $pdo->prepare("
        INSERT INTO match_lineup (match_id, player_id, status, lineup_team, slot_group, slot_index, override_position_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            lineup_team = VALUES(lineup_team),
            slot_group = VALUES(slot_group),
            slot_index = VALUES(slot_index),
            override_position_id = VALUES(override_position_id)
    ");
    $stmt->execute([$matchId, $playerId, $status, $lineupTeam, $slotGroup, $slotIndex, $overridePositionId]);
}

function matchLineupSeedTeamRosterMatch(PDO $pdo, int $matchId): void
{
    matchLineupSyncTeamRosterSnapshot($pdo, $matchId);
}

function matchLineupFillMissingTeamRosterReserves(PDO $pdo, int $matchId): void
{
    $stmt = $pdo->prepare("
        SELECT m.id, m.lineup_mode, c.context
        FROM matches m
        LEFT JOIN competitions c ON c.id = m.competition_id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match || (string)($match['context'] ?? 'external') === 'internal') {
        return;
    }

    if ((string)($match['lineup_mode'] ?? 'team_roster') !== 'team_roster') {
        return;
    }

    $pdo->prepare("
        INSERT INTO match_confirmations (match_id, player_id, status, payment_status, confirmed_at)
        SELECT ?, p.id, 'confirmed', 'not_required', NOW()
        FROM players p
        LEFT JOIN match_lineup ml ON ml.match_id = ? AND ml.player_id = p.id
        WHERE p.status = 'active'
          AND ml.id IS NULL
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            payment_status = VALUES(payment_status),
            confirmed_at = COALESCE(match_confirmations.confirmed_at, VALUES(confirmed_at))
    ")->execute([$matchId, $matchId]);

    $pdo->prepare("
        INSERT INTO match_lineup (match_id, player_id, status, lineup_team, slot_group, slot_index, override_position_id)
        SELECT ?, p.id, 'reserve', 'team_1', NULL, NULL, NULL
        FROM players p
        LEFT JOIN match_lineup ml ON ml.match_id = ? AND ml.player_id = p.id
        WHERE p.status = 'active'
          AND ml.id IS NULL
    ")->execute([$matchId, $matchId]);
}

function matchLineupSyncTeamRosterSnapshot(PDO $pdo, int $matchId, bool $initial = false): void
{
    matchLineupCopyOwnFormations($pdo, $matchId, false, $initial);
}

function matchLineupSyncTrainingSnapshot(PDO $pdo, int $matchId, bool $initial = false): void
{
    matchLineupCopyOwnFormations($pdo, $matchId, true, $initial);
}

function matchLineupCopyOwnFormations(PDO $pdo, int $matchId, bool $training, bool $initial = false): void
{
    matchLineupOwnTeam($pdo);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $query = $pdo->prepare("SELECT m.*, c.context, c.type FROM matches m LEFT JOIN competitions c ON c.id=m.competition_id WHERE m.id=? FOR UPDATE");
        $query->execute([$matchId]);
        $match = $query->fetch(PDO::FETCH_ASSOC);
        if (!$match || !empty($match['lineup_snapshot_at'])
            || (!$initial && in_array($match['status'], ['finished', 'canceled'], true))
            || ($training && ($match['context'] !== 'internal' || $match['type'] !== 'training'))
            || (!$training && (($match['context'] ?? '') === 'internal' || $match['lineup_mode'] !== 'team_roster'))) {
            if ($ownsTransaction) $pdo->commit();
            return;
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM match_lineup WHERE match_id=?');
        $count->execute([$matchId]);
        if ((int)$count->fetchColumn() > 0) {
            if ($ownsTransaction) $pdo->commit();
            return;
        }
        $team = $pdo->query('SELECT * FROM team_profile WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($training && (!teamSecondFormationAllowed() || (int)($team['formations_count'] ?? 1) !== 2)) {
            throw new RuntimeException('Ative os dois times em Meu Time para criar um treino.');
        }
        $resolved = teamResolveFormationRoster(teamLoadFormationRoster($pdo), $team);
        $confirm = $pdo->prepare("INSERT INTO match_confirmations (match_id,player_id,status,payment_status,confirmed_at) VALUES (?,?,'confirmed','not_required',NOW()) ON DUPLICATE KEY UPDATE player_id=VALUES(player_id)");
        $selected = [];
        foreach ($resolved['teams'] as $key => $formation) {
            if (!$training && $key !== 'time_1') continue;
            foreach ($formation['players'] as $member) {
                $selected[] = [$member, 'starter', $key === 'time_2' ? 'team_2' : 'team_1'];
            }
        }
        if (!$training) {
            foreach (array_slice($resolved['available'], 0, max(0, min(8, (int)($team['reserves_count'] ?? 8)))) as $member) {
                $selected[] = [$member, 'reserve', 'team_1'];
            }
        }
        foreach ($selected as [$member, $status, $key]) {
            $playerId = (int)$member['player_id'];
            $confirm->execute([$matchId, $playerId]);
            matchLineupSavePlayer($pdo, $matchId, $playerId, $status,
                $status === 'starter' ? $member['slot_group'] : null,
                $status === 'starter' ? (int)$member['slot_index'] : null, null, $key);
        }
        $snapshot = matchLineupCurrentFieldSnapshot($pdo);
        $save = $pdo->prepare("UPDATE matches SET field_type_snapshot=?,field_slots_snapshot_json=?,lineup_snapshot_at=NOW() WHERE id=?");
        $save->execute([$snapshot['team_type'], $snapshot['custom_slots_json'], $matchId]);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function matchLineupAssignConfirmedPlayer(PDO $pdo, int $matchId, int $playerId, ?int $overridePositionId = null, bool $manual = false): string
{
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.lineup_mode,
            p.id AS player_id,
            p.name,
            p.position_id,
            p.secondary_position_id,
            pp.name AS position_name,
            pp.code AS position_code,
            pp.group_key,
            spp.name AS secondary_position_name,
            spp.code AS secondary_position_code,
            spp.group_key AS secondary_group_key
        FROM matches m
        INNER JOIN players p ON p.id = ?
        LEFT JOIN player_positions pp ON pp.id = p.position_id
        LEFT JOIN player_positions spp ON spp.id = p.secondary_position_id
        WHERE m.id = ?
          AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$playerId, $matchId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        return 'reserve';
    }

    $team = $pdo->query("SELECT team_type, custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $teamType = (string)($team['team_type'] ?? 'field');
    $setup = matchLineupFieldSetup($teamType, $team['custom_slots_json'] ?? null);
    $limits = $setup['limits'];
    $occupied = matchLineupOccupiedSlots($pdo, $matchId);

    $status = 'reserve';
    $slotGroup = null;
    $slotIndex = null;

    if (($player['lineup_mode'] ?? 'team_roster') === 'arrival_order' && !$manual) {
        matchLineupSavePlayer($pdo, $matchId, $playerId, 'reserve', null, null, null, 'team_1');
        return 'reserve';
    } else {
        $candidateGroups = [];

        if ($overridePositionId !== null && $overridePositionId > 0) {
            $candidateGroups[] = matchLineupPositionSlotsForSetup($pdo, $overridePositionId, $setup);
        }

        if ($manual || ($player['lineup_mode'] ?? 'team_roster') === 'arrival_order') {
            if (!matchLineupIsReservePosition($player)) {
                $candidateGroups[] = matchLineupCandidateSlotsForSetup($player, $setup);

                if (!empty($player['secondary_position_id']) && !matchLineupPositionIsReserve($pdo, (int)$player['secondary_position_id'])) {
                    $candidateGroups[] = matchLineupPositionSlotsForSetup($pdo, (int)$player['secondary_position_id'], $setup);
                }
            } elseif ($manual) {
                $candidateGroups[] = matchLineupFlatSlots($setup);
            }
        } else {
            $stmt = $pdo->prepare("
                SELECT status, slot_group, slot_index
                FROM team_roster
                WHERE player_id = ?
                LIMIT 1
            ");
            $stmt->execute([$playerId]);
            $roster = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($roster && ($roster['status'] ?? '') === 'active') {
                $candidateGroup = (string)($roster['slot_group'] ?? '');
                $candidateIndex = $roster['slot_index'] !== null ? (int)$roster['slot_index'] : null;

                if ($candidateGroup !== '' && $candidateIndex !== null) {
                    $candidateGroups[] = [[$candidateGroup, $candidateIndex]];
                }
            }
        }

        foreach ($candidateGroups as $candidateSlots) {
            foreach ($candidateSlots as [$candidateGroup, $candidateIndex]) {
                if (!isset($limits[$candidateGroup]) || $candidateIndex >= $limits[$candidateGroup]) {
                    continue;
                }

                $key = $candidateGroup . ':' . $candidateIndex;
                if (isset($occupied[$key])) {
                    continue;
                }

                $status = 'starter';
                $slotGroup = $candidateGroup;
                $slotIndex = $candidateIndex;
                break 2;
            }
        }
    }

    matchLineupSavePlayer($pdo, $matchId, $playerId, $status, $slotGroup, $slotIndex, $overridePositionId, 'team_1');

    return $status;
}

function matchLineupTeamLabel(?string $team): string
{
    return $team === 'team_2' ? 'Time 2' : 'Time 1';
}

function matchLineupAutoAssignInternal(PDO $pdo, int $matchId): void
{
    matchLineupSyncTrainingSnapshot($pdo, $matchId);
}
