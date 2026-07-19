<?php
declare(strict_types=1);

function matchLineupEnsureSchema(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM matches")->fetchAll(PDO::FETCH_COLUMN);

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
        $x = count($zcSlots) === 1 ? 50 : ($index === 0 ? 42 : 58);
        $addSlot('zagueiro', $x, 72, 'ZC');
    }

    if (!empty($customSlots['LE'])) {
        $addSlot('lateral', 18, 62, 'LE');
    }

    if (!empty($customSlots['LD'])) {
        $addSlot('lateral', 82, 62, 'LD');
    }

    if (!empty($customSlots['VOL'])) {
        $addSlot('meia', 50, 55, 'VOL');
    }

    $matSlots = array_values(array_filter(['MAT1', 'MAT2'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($matSlots as $index => $_code) {
        $x = count($matSlots) === 1 ? 50 : ($index === 0 ? 42 : 58);
        $addSlot('meia', $x, 40, 'MAT');
    }

    if (!empty($customSlots['PTE'])) {
        $addSlot('ponta', 24, 24, 'PTE');
    }

    if (!empty($customSlots['PTD'])) {
        $addSlot('ponta', 76, 24, 'PTD');
    }

    $attackSlots = array_values(array_filter(['CA'], static fn (string $code): bool => !empty($customSlots[$code])));
    foreach ($attackSlots as $index => $code) {
        $x = count($attackSlots) === 1 ? 50 : ($index === 0 ? 44 : 56);
        $addSlot('atacante', $x, 20, $code);
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
    $stmt = $pdo->prepare("
        SELECT m.id, m.lineup_mode, c.context
        FROM matches m
        LEFT JOIN competitions c ON c.id = m.competition_id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$match || ($match['context'] ?? '') !== 'internal') {
        return;
    }

    $team = $pdo->query("SELECT team_type, custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $teamType = (string)($team['team_type'] ?? 'field');
    $setup = matchLineupFieldSetup($teamType, $team['custom_slots_json'] ?? null);
    $limits = $setup['limits'];
    $teams = ['team_1', 'team_2'];

    $occupied = [
        'team_1' => matchLineupOccupiedSlots($pdo, $matchId, 'team_1'),
        'team_2' => matchLineupOccupiedSlots($pdo, $matchId, 'team_2'),
    ];

    $teamCounts = ['team_1' => 0, 'team_2' => 0];
    $countStmt = $pdo->prepare("
        SELECT COALESCE(lineup_team, 'team_1') AS lineup_team, COUNT(*) AS total
        FROM match_lineup
        WHERE match_id = ? AND status = 'starter'
        GROUP BY COALESCE(lineup_team, 'team_1')
    ");
    $countStmt->execute([$matchId]);

    foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $team = (string)$row['lineup_team'];
        if (isset($teamCounts[$team])) {
            $teamCounts[$team] = (int)$row['total'];
        }
    }

    $playersStmt = $pdo->prepare("
        SELECT
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
        FROM match_confirmations mc
        INNER JOIN players p ON p.id = mc.player_id
        LEFT JOIN player_positions pp ON pp.id = p.position_id
        LEFT JOIN player_positions spp ON spp.id = p.secondary_position_id
        LEFT JOIN match_lineup ml ON ml.match_id = mc.match_id AND ml.player_id = mc.player_id
        WHERE mc.match_id = ?
          AND mc.status = 'confirmed'
          AND p.status = 'active'
          AND (ml.id IS NULL OR ml.status = 'reserve')
    ");
    $playersStmt->execute([$matchId]);
    $players = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($players)) {
        return;
    }

    shuffle($players);

    foreach ($players as $player) {
        $candidateSlots = matchLineupIsReservePosition($player) ? [] : matchLineupCandidateSlotsForSetup($player, $setup);

        if (!matchLineupIsReservePosition($player) && !empty($player['secondary_position_id']) && !matchLineupPositionIsReserve($pdo, (int)$player['secondary_position_id'])) {
            $candidateSlots = array_merge($candidateSlots, matchLineupPositionSlotsForSetup($pdo, (int)$player['secondary_position_id'], $setup));
        }

        $uniqueCandidates = [];
        foreach ($candidateSlots as $candidate) {
            $uniqueCandidates[$candidate[0] . ':' . $candidate[1]] = $candidate;
        }
        $candidateSlots = array_values($uniqueCandidates);
        shuffle($candidateSlots);

        $teamOrder = $teams;
        shuffle($teamOrder);
        usort($teamOrder, static function (string $a, string $b) use ($teamCounts): int {
            return $teamCounts[$a] <=> $teamCounts[$b];
        });

        $saved = false;

        foreach ($teamOrder as $team) {
            foreach ($candidateSlots as [$candidateGroup, $candidateIndex]) {
                if (!isset($limits[$candidateGroup]) || $candidateIndex >= $limits[$candidateGroup]) {
                    continue;
                }

                $key = $team . ':' . $candidateGroup . ':' . $candidateIndex;
                if (isset($occupied[$team][$key])) {
                    continue;
                }

                matchLineupSavePlayer($pdo, $matchId, (int)$player['player_id'], 'starter', $candidateGroup, (int)$candidateIndex, null, $team);
                $occupied[$team][$key] = true;
                $teamCounts[$team]++;
                $saved = true;
                break 2;
            }
        }

        if (!$saved) {
            $reserveTeam = $teamCounts['team_1'] <= $teamCounts['team_2'] ? 'team_1' : 'team_2';
            matchLineupSavePlayer($pdo, $matchId, (int)$player['player_id'], 'reserve', null, null, null, $reserveTeam);
        }
    }
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
