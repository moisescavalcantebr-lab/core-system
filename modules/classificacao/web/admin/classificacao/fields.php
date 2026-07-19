<?php

function classificationAvailableFields(): array
{
    return [
        'name' => ['label' => 'Jogador', 'type' => 'text'],
        'position' => ['label' => 'Posicao', 'type' => 'number'],
        'group_name' => ['label' => 'Grupo', 'type' => 'text'],
        'category' => ['label' => 'Categoria', 'type' => 'text'],
        'played' => ['label' => 'Jogos', 'type' => 'number'],
        'presences' => ['label' => 'Presencas', 'type' => 'number'],
        'presence_points' => ['label' => 'Pontos Presenca', 'type' => 'number'],
        'wins' => ['label' => 'Vitorias', 'type' => 'number'],
        'draws' => ['label' => 'Empates', 'type' => 'number'],
        'losses' => ['label' => 'Derrotas', 'type' => 'number'],
        'goals_for' => ['label' => 'Gols Pro', 'type' => 'number'],
        'goals_against' => ['label' => 'Gols Contra', 'type' => 'number'],
        'balance' => ['label' => 'Saldo', 'type' => 'number'],
        'points' => ['label' => 'Pontos', 'type' => 'number'],
        'score' => ['label' => 'Score', 'type' => 'number'],
        'percent' => ['label' => 'Aproveitamento', 'type' => 'number'],
        'attendance_percent' => ['label' => '% Presenca', 'type' => 'number'],
        'last_presence' => ['label' => 'Ultima Presenca', 'type' => 'text'],
        'notes' => ['label' => 'Observacoes', 'type' => 'text'],
    ];
}

function classificationDefaultFields(?string $context = null): array
{
    if ($context === 'internal') {
        return ['position', 'name', 'played', 'points', 'wins', 'draws', 'losses', 'goals_for', 'goals_against', 'balance'];
    }

    return ['position', 'played', 'wins', 'draws', 'losses', 'balance', 'points'];
}

function classificationDecodeFields(?string $json): array
{
    $fields = json_decode((string)$json, true);

    if (!is_array($fields)) {
        return classificationDefaultFields();
    }

    return array_values(array_filter($fields, 'is_string'));
}

function classificationFieldValue(array $rowData, string $field): string
{
    $value = $rowData[$field] ?? '';

    if ($value === null || $value === '') {
        return '-';
    }

    return (string)$value;
}

function classificationRebuildInternalCompetition(PDO $pdo, int $competitionId): void
{
    if ($competitionId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("SELECT id, context FROM competitions WHERE id = ? LIMIT 1");
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$competition || ($competition['context'] ?? '') !== 'internal') {
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM classification_tables WHERE competition_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$competitionId]);
    $tableId = (int)($stmt->fetchColumn() ?: 0);

    if ($tableId <= 0) {
        return;
    }

    $matchesStmt = $pdo->prepare("
        SELECT id, score_a, score_b, match_date, created_at
        FROM matches
        WHERE competition_id = ?
          AND status = 'finished'
          AND score_a IS NOT NULL
          AND score_b IS NOT NULL
        ORDER BY COALESCE(match_date, created_at) ASC, id ASC
    ");
    $matchesStmt->execute([$competitionId]);
    $matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalMatches = count($matches);

    $players = [];

    foreach ($matches as $match) {
        $matchId = (int)$match['id'];
        $scoreA = (int)$match['score_a'];
        $scoreB = (int)$match['score_b'];
        $matchDate = (string)($match['match_date'] ?? $match['created_at'] ?? '');

        $participantsStmt = $pdo->prepare("
            SELECT
                p.id AS player_id,
                ml.lineup_team,
                ml.status AS lineup_status,
                ma.status AS attendance_status,
                ma.points AS attendance_points,
                p.name,
                pp.code AS position_code,
                pp.name AS position_name
            FROM players p
            LEFT JOIN match_lineup ml ON ml.match_id = ? AND ml.player_id = p.id
            LEFT JOIN match_attendance ma ON ma.match_id = ? AND ma.player_id = p.id
            LEFT JOIN player_positions pp ON pp.id = p.position_id
            WHERE ml.player_id IS NOT NULL
               OR ma.player_id IS NOT NULL
            ORDER BY p.name ASC
        ");
        $participantsStmt->execute([$matchId, $matchId]);
        $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($participants)) {
            $fallbackStmt = $pdo->prepare("
            SELECT
                mc.player_id,
                NULL AS lineup_team,
                NULL AS lineup_status,
                NULL AS attendance_status,
                NULL AS attendance_points,
                p.name,
                pp.code AS position_code,
                pp.name AS position_name
                FROM match_confirmations mc
                INNER JOIN players p ON p.id = mc.player_id
                LEFT JOIN player_positions pp ON pp.id = p.position_id
                WHERE mc.match_id = ?
                  AND mc.status = 'confirmed'
                ORDER BY p.name ASC
            ");
            $fallbackStmt->execute([$matchId]);
            $participants = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $seen = [];

        foreach ($participants as $participant) {
            $playerId = (int)$participant['player_id'];

            if ($playerId <= 0 || isset($seen[$playerId])) {
                continue;
            }

            $lineupTeam = (string)($participant['lineup_team'] ?? '');
            $attendanceStatus = (string)($participant['attendance_status'] ?? '');
            $attendancePoints = $participant['attendance_points'];
            $hasAttendance = $attendanceStatus !== '';
            $isPresent = $hasAttendance ? $attendanceStatus === 'present' : in_array($lineupTeam, ['team_1', 'team_2'], true);
            $points = $hasAttendance ? (float)$attendancePoints : ($isPresent ? 1.0 : 0.0);

            if ($points === 0.0 && !$isPresent && $attendanceStatus === 'no_response' && !in_array($lineupTeam, ['team_1', 'team_2'], true)) {
                continue;
            }

            $seen[$playerId] = true;

            if (!isset($players[$playerId])) {
                $players[$playerId] = [
                    'player_id' => $playerId,
                    'name' => (string)($participant['name'] ?? '-'),
                    'group_name' => trim((string)($participant['position_code'] ?? '')),
                    'category' => (string)($participant['position_name'] ?? ''),
                    'played' => 0,
                    'presences' => 0,
                    'presence_points' => 0,
                    'wins' => 0,
                    'draws' => 0,
                    'losses' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'balance' => 0,
                    'points' => 0,
                    'score' => 0,
                    'percent' => 0,
                    'attendance_percent' => 0,
                    'last_presence' => '',
                    'notes' => '',
                ];
            }

            if ($isPresent) {
                $players[$playerId]['presences']++;
                $players[$playerId]['last_presence'] = $matchDate;
            }

            $players[$playerId]['presence_points'] += $points;

            if ($isPresent && in_array($lineupTeam, ['team_1', 'team_2'], true)) {
                $players[$playerId]['played']++;

                $goalsFor = $lineupTeam === 'team_1' ? $scoreA : $scoreB;
                $goalsAgainst = $lineupTeam === 'team_1' ? $scoreB : $scoreA;
                $resultPoints = 0;

                $players[$playerId]['goals_for'] += $goalsFor;
                $players[$playerId]['goals_against'] += $goalsAgainst;
                $players[$playerId]['balance'] += ($goalsFor - $goalsAgainst);

                if ($goalsFor > $goalsAgainst) {
                    $players[$playerId]['wins']++;
                    $resultPoints = 3;
                } elseif ($goalsFor < $goalsAgainst) {
                    $players[$playerId]['losses']++;
                } else {
                    $players[$playerId]['draws']++;
                    $resultPoints = 1;
                }

                $players[$playerId]['points'] += $resultPoints;
                $players[$playerId]['score'] += $resultPoints;
            }
        }
    }

    foreach ($players as &$player) {
        $percent = (int)$player['played'] > 0 ? round(((float)$player['points'] / ((int)$player['played'] * 3)) * 100, 1) : 0;
        $player['percent'] = $percent;
        $player['attendance_percent'] = $totalMatches > 0 ? round(((int)$player['presences'] / $totalMatches) * 100, 1) : 0;
        $player['points'] = round((float)$player['points'], 1);
        $player['presence_points'] = round((float)$player['presence_points'], 1);
        $player['score'] = round((float)$player['score'], 1);
    }
    unset($player);

    usort($players, static function (array $a, array $b): int {
        return ((int)$b['points'] <=> (int)$a['points'])
            ?: ((float)$b['attendance_percent'] <=> (float)$a['attendance_percent'])
            ?: strcmp((string)$a['name'], (string)$b['name']);
    });

    $pdo->beginTransaction();

    try {
        $delete = $pdo->prepare("DELETE FROM classification_rows WHERE table_id = ?");
        $delete->execute([$tableId]);

        $insert = $pdo->prepare("
            INSERT INTO classification_rows (table_id, name, data_json)
            VALUES (?, ?, ?)
        ");

        $position = 1;
        foreach ($players as $player) {
            $player['position'] = $position++;
            $insert->execute([
                $tableId,
                (string)$player['name'],
                json_encode($player, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
    }
}
