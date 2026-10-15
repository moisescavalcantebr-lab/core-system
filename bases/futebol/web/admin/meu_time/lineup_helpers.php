<?php
declare(strict_types=1);

function teamLoadFormationRoster(PDO $pdo): array
{
    return $pdo->query("
        SELECT tr.*, p.status AS player_status,
            COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
            pp.name AS position_name, pp.code AS position_code, pp.group_key,
            pp2.name AS secondary_position_name, pp2.code AS secondary_position_code,
            pp2.group_key AS secondary_group_key, COALESCE(p.avatar, u.avatar) AS avatar
        FROM team_roster tr
        INNER JOIN players p ON p.id = tr.player_id
        LEFT JOIN player_positions pp ON pp.id = p.position_id
        LEFT JOIN player_positions pp2 ON pp2.id = p.secondary_position_id
        LEFT JOIN project_users u ON u.id = p.user_id
        WHERE p.status = 'active'
        ORDER BY COALESCE(tr.updated_at, tr.created_at) ASC, tr.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function teamFormationType(array $team): string
{
    $type = (string)($team['team_type'] ?? 'field');
    if ($type === 'custom' && teamCustomFieldLimits($team['custom_slots_json'] ?? null) === null) {
        return 'field';
    }
    return in_array($type, ['field', 'custom', 'futsal', 'society', 'beach'], true) ? $type : 'field';
}

function teamResolveFormationRoster(array $roster, array $team): array
{
    $keys = (int)($team['formations_count'] ?? 1) === 2 ? ['time_1', 'time_2'] : ['time_1'];
    $type = teamFormationType($team);
    $custom = (string)($team['custom_slots_json'] ?? '');
    $limits = teamFieldLimits($type, $custom);
    $result = ['teams' => [], 'available' => [], 'assigned' => []];
    foreach ($keys as $key) $result['teams'][$key] = ['players' => [], 'occupied' => []];
    foreach ($roster as $member) {
        $key = (string)($member['team_key'] ?? 'time_1');
        $group = (string)($member['slot_group'] ?? '');
        $index = $member['slot_index'] !== null ? (int)$member['slot_index'] : null;
        $candidates = teamCandidateSlotsWithSecondary($member, $limits, $type, $custom);
        $valid = ($member['status'] ?? '') === 'active' && isset($result['teams'][$key]);
        // Older rosters had no saved slot; resolve them with the same rules as a new assignment.
        if ($valid && ($group === '' || $index === null)) {
            foreach ($candidates as [$candidateGroup, $candidateIndex]) {
                if ($candidateIndex >= 0 && $candidateIndex < ($limits[$candidateGroup] ?? 0)
                    && !isset($result['teams'][$key]['occupied'][$candidateGroup.':'.$candidateIndex])) {
                    $group = $candidateGroup;
                    $index = $candidateIndex;
                    break;
                }
            }
        }
        $slot = $group.':'.$index;
        $valid = $valid && $index !== null && $index >= 0 && $index < ($limits[$group] ?? 0)
            && in_array([$group, $index], $candidates, true)
            && !isset($result['teams'][$key]['occupied'][$slot]);
        if (!$valid) {
            $result['available'][] = $member;
            continue;
        }
        $member['slot_group'] = $group;
        $member['slot_index'] = $index;
        $result['teams'][$key]['occupied'][$slot] = true;
        $result['teams'][$key]['players'][] = $member;
        $result['assigned'][(int)$member['player_id']] = true;
    }
    return $result;
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

