<?php
declare(strict_types=1);

function playerCardFormationLabels(PDO $pdo): ?array
{
    $helper = __DIR__ . '/../meu_time/lineup_helpers.php';
    if (!is_file($helper)) return null;
    foreach (['team_profile', 'team_roster'] as $table) {
        $query = $pdo->prepare('SHOW TABLES LIKE ?');
        $query->execute([$table]);
        if (!$query->fetchColumn()) return null;
    }
    try {
        require_once $helper;
        $team = $pdo->query('SELECT * FROM team_profile WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if (!$team) return null;
        $resolved = teamResolveFormationRoster(teamLoadFormationRoster($pdo), $team);
        $labels = [];
        foreach ($resolved['teams'] as $key => $formation) {
            $name = trim((string)($team[$key.'_name'] ?? '')) ?: ($key === 'time_1' ? 'Time 1' : 'Time 2');
            foreach ($formation['players'] as $player) $labels[(int)$player['player_id']] = $name;
        }
        return $labels;
    } catch (PDOException $e) {
        error_log('Player formation labels unavailable: '.$e->getMessage());
        return null;
    }
}

function playerCardPosition(array $player): string
{
    $name = trim((string)($player['position_name'] ?? $player['position'] ?? ''));
    if ($name === '') return 'Sem posição';
    return preg_replace('/^(Goleiro|Zagueiro|Lateral Esquerdo|Lateral Direito|Volante|Meia Atacante|Ponta Esquerda|Ponta Direita|Atacante) \d+$/u', '$1', $name) ?? $name;
}
