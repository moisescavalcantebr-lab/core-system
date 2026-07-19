<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAuth();

$user = projectUser();
$stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$currentPlayerId = (int)($stmt->fetchColumn() ?: 0);

if ($currentPlayerId <= 0) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    http_response_code(404);
    exit('Competicao nao encontrada.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM matches
    WHERE competition_id = ?
    ORDER BY COALESCE(match_date, created_at) ASC
");
$stmt->execute([$id]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isInternal = ($competition['context'] ?? '') === 'internal';
$classification = null;
$classificationRows = [];
$activeFields = [];
$playerRankingData = null;
$availableFields = $classificationEnabled ? classificationAvailableFields() : [];

if ($isInternal && $classificationEnabled) {
    classificationRebuildInternalCompetition($pdo, $id);

    $stmt = $pdo->prepare("SELECT * FROM classification_tables WHERE competition_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id]);
    $classification = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($classification) {
        $activeFields = classificationDecodeFields($classification['active_fields'] ?? '[]');

        $stmt = $pdo->prepare("SELECT * FROM classification_rows WHERE table_id = ? ORDER BY id ASC");
        $stmt->execute([(int)$classification['id']]);
        $classificationRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classificationRows as $row) {
            $rowData = json_decode((string)($row['data_json'] ?? '[]'), true);

            if (is_array($rowData) && (int)($rowData['player_id'] ?? 0) === $currentPlayerId) {
                $playerRankingData = $rowData;
                break;
            }
        }
    }
}

$wins = 0;
$losses = 0;
$draws = 0;
$points = 0;
$goalsFor = 0;
$goalsAgainst = 0;

foreach ($matches as $match) {
    if (($match['status'] ?? '') !== 'finished' || $match['score_a'] === null || $match['score_b'] === null) {
        continue;
    }

    $scoreA = (int)$match['score_a'];
    $scoreB = (int)$match['score_b'];

    $goalsFor += $scoreA;
    $goalsAgainst += $scoreB;

    if ($scoreA > $scoreB) {
        $wins++;
        $points += 3;
    } elseif ($scoreA < $scoreB) {
        $losses++;
    } else {
        $draws++;
        $points++;
    }
}

$goalBalance = $goalsFor - $goalsAgainst;
$yellowCards = 0;
$redCards = 0;
foreach ($matches as $match) {
    if (($match['status'] ?? '') !== 'finished') {
        continue;
    }

    $yellowCards += (int)($match['yellow_cards_a'] ?? 0) + (int)($match['yellow_cards_b'] ?? 0);
    $redCards += (int)($match['red_cards_a'] ?? 0) + (int)($match['red_cards_b'] ?? 0);
}
$isFriendlyExternal = ($competition['context'] ?? '') === 'external' && ($competition['type'] ?? '') === 'friendly';
$scheduledMatches = [];
$liveMatches = [];
$finishedMatches = [];

foreach ($matches as $match) {
    if (($match['status'] ?? '') === 'finished') {
        $finishedMatches[] = $match;
        continue;
    }

    if (($match['status'] ?? '') === 'scheduled') {
        $scheduledMatches[] = $match;
        continue;
    }

    if (($match['status'] ?? '') === 'live') {
        $liveMatches[] = $match;
    }
}

$contextLabels = ['external' => 'Externa', 'internal' => 'Interna'];
$typeLabels = [
    'championship' => 'Campeonato',
    'cup' => 'Copa',
    'league' => 'Liga',
    'tournament' => 'Torneio',
    'friendly' => 'Amistoso',
    'training' => 'Treino',
    'challenge' => 'Desafio',
    'ranking' => 'Ranking',
    'other' => 'Outro',
];
$matchStatusLabels = [
    'scheduled' => 'Agendada',
    'live' => 'Em andamento',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function playerCompetitionMatchStatusBadge(?string $status): string
{
    return match ($status) {
        'live' => 'c-badge--success',
        'scheduled' => 'c-badge--info',
        'finished' => 'c-badge--neutral',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function playerCompetitionResultClass(array $match): string
{
    if (($match['score_a'] ?? null) === null || ($match['score_b'] ?? null) === null) {
        return 'c-player-competition-result-score--draw';
    }

    $scoreA = (int)$match['score_a'];
    $scoreB = (int)$match['score_b'];

    return match (true) {
        $scoreA > $scoreB => 'c-player-competition-result-score--win',
        $scoreA < $scoreB => 'c-player-competition-result-score--loss',
        default => 'c-player-competition-result-score--draw',
    };
}

$title = 'Competicao';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars((string)$competition['name']) ?></h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($contextLabels[$competition['context']] ?? $competition['context']) ?> · <?= htmlspecialchars($typeLabels[$competition['type']] ?? $competition['type']) ?></p>
        </div>

        <div class="c-actions">
            <a href="<?= PROJECT_URL ?>/admin/player/competicao_regras.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                Ver regras
            </a>
            <a href="<?= PROJECT_URL ?>/admin/player/competicoes.php" class="c-btn-secondary">
                Voltar
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php if ($isInternal && $classificationEnabled): ?>
            <div class="c-dashboard-grid c-player-competition-metrics">
                <div class="c-dashboard-card c-card--info">
                    <h4>Partidas</h4>
                    <div class="c-metric"><?= count($matches) ?></div>
                </div>
                <div class="c-dashboard-card c-card--info">
                    <h4>Minha posição</h4>
                    <div class="c-metric"><?= htmlspecialchars(classificationFieldValue($playerRankingData ?? [], 'position')) ?></div>
                </div>
                <div class="c-dashboard-card c-card--success">
                    <h4>Meus pontos</h4>
                    <div class="c-metric"><?= htmlspecialchars(classificationFieldValue($playerRankingData ?? [], 'points')) ?></div>
                </div>
                <div class="c-dashboard-card c-card--neutral">
                    <h4>Presenças</h4>
                    <div class="c-metric"><?= htmlspecialchars(classificationFieldValue($playerRankingData ?? [], 'presences')) ?></div>
                </div>
                <div class="c-dashboard-card c-card--neutral">
                    <h4>% presença</h4>
                    <div class="c-metric"><?= htmlspecialchars(classificationFieldValue($playerRankingData ?? [], 'attendance_percent')) ?></div>
                </div>
                <div class="c-dashboard-card c-card--info">
                    <h4>Ranking</h4>
                    <div class="c-metric"><?= count($classificationRows) ?></div>
                </div>
            </div>
        <?php elseif ($isInternal): ?>
            <div class="c-dashboard-grid c-player-competition-metrics">
                <div class="c-dashboard-card c-card--info">
                    <h4>Partidas</h4>
                    <div class="c-metric"><?= count($matches) ?></div>
                </div>
            </div>
        <?php elseif ($isFriendlyExternal): ?>
            <div class="c-dashboard-grid c-player-competition-metrics c-player-competition-metrics--friendly">
                <div class="c-dashboard-card c-card--info">
                    <h4>Partidas</h4>
                    <div class="c-metric"><?= count($matches) ?></div>
                </div>
                <div class="c-dashboard-card c-card--success">
                    <h4>Desempenho</h4>
                    <div class="c-metric-combo">
                        <span><small>V</small><strong><?= $wins ?></strong></span>
                        <span><small>E</small><strong><?= $draws ?></strong></span>
                        <span><small>D</small><strong><?= $losses ?></strong></span>
                    </div>
                </div>
                <div class="c-dashboard-card c-card--info">
                    <h4>Gols</h4>
                    <div class="c-metric-combo">
                        <span><small>Pró</small><strong><?= $goalsFor ?></strong></span>
                        <span><small>Contra</small><strong><?= $goalsAgainst ?></strong></span>
                        <span><small>Saldo</small><strong><?= $goalBalance > 0 ? '+' . $goalBalance : $goalBalance ?></strong></span>
                    </div>
                </div>
                <div class="c-dashboard-card c-card--neutral">
                    <h4>Cartões</h4>
                    <div class="c-metric-combo c-metric-combo--cards">
                        <span><small>Amarelos</small><strong><?= $yellowCards ?></strong></span>
                        <span><small>Vermelhos</small><strong><?= $redCards ?></strong></span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="c-dashboard-grid c-player-competition-metrics">
                <div class="c-dashboard-card c-card--info">
                    <h4>Partidas</h4>
                    <div class="c-metric"><?= count($matches) ?></div>
                </div>
                <div class="c-dashboard-card c-card--info">
                    <h4>Pontos</h4>
                    <div class="c-metric"><?= $points ?></div>
                </div>
                <div class="c-dashboard-card c-card--success">
                    <h4>Vitórias</h4>
                    <div class="c-metric"><?= $wins ?></div>
                </div>
                <div class="c-dashboard-card c-card--neutral">
                    <h4>Empates</h4>
                    <div class="c-metric"><?= $draws ?></div>
                </div>
                <div class="c-dashboard-card c-card--danger">
                    <h4>Derrotas</h4>
                    <div class="c-metric"><?= $losses ?></div>
                </div>
                <div class="c-dashboard-card c-card--neutral">
                    <h4>Saldo</h4>
                    <div class="c-metric"><?= $goalBalance > 0 ? '+' . $goalBalance : $goalBalance ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isInternal && $classificationEnabled): ?>
            <div class="c-card">
                <h3>Classificação</h3>

                <?php if (!$classification): ?>
                    <p>Nenhuma classificação criada para esta competição.</p>
                <?php elseif (empty($classificationRows)): ?>
                    <p>Nenhum jogador pontuado ainda.</p>
                <?php else: ?>
                    <div class="c-table-wrapper">
                        <table class="c-table">
                            <thead>
                                <tr>
                                    <?php foreach ($activeFields as $field): ?>
                                        <th><?= htmlspecialchars($availableFields[$field]['label'] ?? $field) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classificationRows as $row): ?>
                                    <?php $rowData = json_decode((string)($row['data_json'] ?? '[]'), true) ?: []; ?>
                                    <tr>
                                        <?php foreach ($activeFields as $field): ?>
                                            <td><?= htmlspecialchars(classificationFieldValue($rowData, $field)) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="c-card">
            <h3>Partidas agendadas</h3>

            <?php if (empty($scheduledMatches)): ?>
                <p>Nenhuma partida agendada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <?php if (!$isFriendlyExternal): ?>
                                    <th>Rodada/Fase</th>
                                <?php endif; ?>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduledMatches as $match): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong></td>
                                    <?php if (!$isFriendlyExternal): ?>
                                        <td><?= htmlspecialchars($match['round_name'] ?? '-') ?></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($match['match_date'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($match['venue'] ?? '-') ?></td>
                                    <td>
                                        <span class="c-badge <?= playerCompetitionMatchStatusBadge($match['status'] ?? null) ?>">
                                            <?= htmlspecialchars($matchStatusLabels[$match['status']] ?? $match['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/player/partidas_lineup.php?id=<?= (int)$match['id'] ?>">
                                            Escalação
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Minhas partidas</h3>

            <?php if (empty($liveMatches)): ?>
                <p>Nenhuma partida em andamento.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <th>Placar</th>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($liveMatches as $match): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong></td>
                                    <td>
                                        <?php if ($match['score_a'] !== null && $match['score_b'] !== null): ?>
                                            <span class="c-player-competition-result-score <?= playerCompetitionResultClass($match) ?>">
                                                <?= (int)$match['score_a'] ?> x <?= (int)$match['score_b'] ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($match['match_date'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($match['venue'] ?? '-') ?></td>
                                    <td>
                                        <span class="c-badge <?= playerCompetitionMatchStatusBadge($match['status'] ?? null) ?>">
                                            <?= htmlspecialchars($matchStatusLabels[$match['status']] ?? $match['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/player/partidas_lineup.php?id=<?= (int)$match['id'] ?>">
                                            Escalação
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Partidas finalizadas</h3>

            <?php if (empty($finishedMatches)): ?>
                <p>Nenhuma partida finalizada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <th>Placar</th>
                                <?php if (!$isFriendlyExternal): ?>
                                    <th>Rodada/Fase</th>
                                <?php endif; ?>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finishedMatches as $match): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong></td>
                                    <td>
                                        <?php if ($match['score_a'] !== null && $match['score_b'] !== null): ?>
                                            <span class="c-player-competition-result-score <?= playerCompetitionResultClass($match) ?>">
                                                <?= (int)$match['score_a'] ?> x <?= (int)$match['score_b'] ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <?php if (!$isFriendlyExternal): ?>
                                        <td><?= htmlspecialchars($match['round_name'] ?? '-') ?></td>
                                    <?php endif; ?>
                                    <td><?= htmlspecialchars($match['match_date'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($match['venue'] ?? '-') ?></td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/player/partidas_show.php?id=<?= (int)$match['id'] ?>">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-player-competition-metrics {
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
}

.c-player-competition-metrics .c-dashboard-card {
    min-height: 86px;
    padding: 14px;
}

.c-player-competition-metrics .c-metric {
    font-size: 22px;
    line-height: 1.1;
}

.c-player-competition-metrics--friendly {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.c-metric-combo {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
}

.c-metric-combo span {
    display: grid;
    gap: 2px;
}

.c-metric-combo small {
    color: var(--text-secondary);
    font-size: 10px;
    font-weight: 700;
}

.c-metric-combo strong {
    font-size: 18px;
}

.c-metric-combo--cards {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.c-metric-combo--cards span:first-child strong {
    color: #fde68a;
}

.c-metric-combo--cards span:last-child strong {
    color: #fecaca;
}

.c-player-competition-result-score {
    display: inline-grid;
    min-width: 64px;
    height: 28px;
    place-items: center;
    border-radius: 6px;
    font-weight: 900;
}

.c-player-competition-result-score--win {
    background: color-mix(in srgb, var(--success-color, #22c55e) 16%, transparent);
    color: var(--success-color, #22c55e);
}

.c-player-competition-result-score--draw {
    background: color-mix(in srgb, var(--text-secondary, #94a3b8) 16%, transparent);
    color: var(--text-primary);
}

.c-player-competition-result-score--loss {
    background: color-mix(in srgb, var(--danger-color, #ef4444) 16%, transparent);
    color: var(--danger-color, #ef4444);
}

@media (max-width: 1100px) {
    .c-player-competition-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .c-player-competition-metrics--friendly {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .c-player-competition-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
