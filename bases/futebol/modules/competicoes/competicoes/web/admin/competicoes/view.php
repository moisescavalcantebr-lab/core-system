<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAdmin();

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

$classification = null;
$classificationRows = [];
$classificationLeader = null;
try {
    if (!$classificationEnabled) {
        throw new RuntimeException('Classificacao nao instalada.');
    }

    $stmt = $pdo->prepare("SELECT * FROM classification_tables WHERE competition_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$id]);
    $classification = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (($competition['context'] ?? '') === 'internal') {
        classificationRebuildInternalCompetition($pdo, $id);

        if ($classification) {
            $stmt = $pdo->prepare("SELECT * FROM classification_rows WHERE table_id = ? ORDER BY id ASC");
            $stmt->execute([(int)$classification['id']]);
            $classificationRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($classificationRows)) {
                $classificationLeader = json_decode((string)($classificationRows[0]['data_json'] ?? '[]'), true) ?: null;
            }
        }
    }
} catch (Throwable $e) {
    $classification = null;
    $classificationRows = [];
    $classificationLeader = null;
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
$finishedMatches = 0;
foreach ($matches as $match) {
    if (($match['status'] ?? '') === 'finished') {
        $finishedMatches++;
        $yellowCards += (int)($match['yellow_cards_a'] ?? 0) + (int)($match['yellow_cards_b'] ?? 0);
        $redCards += (int)($match['red_cards_a'] ?? 0) + (int)($match['red_cards_b'] ?? 0);
    }
}

function competitionRelatedCount(PDO $pdo, int $competitionId): int
{
    $total = 0;

    foreach (['competition_participants', 'statistic_records', 'matches'] as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE competition_id = ?");
            $stmt->execute([$competitionId]);
            $total += (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }

    return $total;
}

$isClosed = in_array($competition['status'] ?? '', ['finished', 'canceled'], true);
$isFriendlyExternal = ($competition['context'] ?? '') === 'external' && ($competition['type'] ?? '') === 'friendly';
$hasLiveMatch = false;
try {
    $hasLiveMatch = (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE status = 'live'")->fetchColumn() > 0;
} catch (Throwable $e) {
    $hasLiveMatch = false;
}
$scheduledMatchesCount = 0;
$scheduledMatches = [];
$finishedMatchesList = [];
foreach ($matches as $match) {
    if (($match['status'] ?? '') === 'scheduled') {
        $scheduledMatchesCount++;
        $scheduledMatches[] = $match;
    }

    if (($match['status'] ?? '') === 'finished') {
        $finishedMatchesList[] = $match;
    }
}
$scheduledMatchLimit = projectPlanLimit('matches_scheduled', 3);
$canCreateMatch = !$isClosed && $scheduledMatchesCount < $scheduledMatchLimit;

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
$statusLabels = [
    'active' => 'Ativa',
    'draft' => 'Rascunho',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

$matchStatusLabels = [
    'scheduled' => 'Agendada',
    'live' => 'Em andamento',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function competitionViewMatchStatusBadge(?string $status): string
{
    return match ($status) {
        'live' => 'c-badge--success',
        'scheduled' => 'c-badge--info',
        'finished' => 'c-badge--neutral',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function competitionViewResultClass(array $match): string
{
    if (($match['score_a'] ?? null) === null || ($match['score_b'] ?? null) === null) {
        return 'c-competition-result-score--draw';
    }

    $scoreA = (int)$match['score_a'];
    $scoreB = (int)$match['score_b'];

    return match (true) {
        $scoreA > $scoreB => 'c-competition-result-score--win',
        $scoreA < $scoreB => 'c-competition-result-score--loss',
        default => 'c-competition-result-score--draw',
    };
}

$title = 'Ver Competicao';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars((string)$competition['name']) ?></h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($contextLabels[$competition['context']] ?? $competition['context']) ?> · <?= htmlspecialchars($typeLabels[$competition['type']] ?? $competition['type']) ?></p>
        </div>

        <div>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/edit.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                Editar
            </a>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/rules.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                Regras
            </a>
            <?php if ($canCreateMatch): ?>
                <a href="<?= PROJECT_URL ?>/admin/partidas/create.php?competition_id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                    Criar Partida
                </a>
            <?php endif; ?>
            <?php if (($competition['context'] ?? '') !== 'external' && $classificationEnabled): ?>
                <?php if ($classification): ?>
                    <a href="<?= PROJECT_URL ?>/admin/classificacao/view.php?id=<?= (int)$classification['id'] ?>" class="c-btn-secondary">
                        Ver Classificacao
                    </a>
                <?php else: ?>
                    <a href="<?= PROJECT_URL ?>/admin/classificacao/create.php?competition_id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                        Criar Classificacao
                    </a>
                <?php endif; ?>
            <?php endif; ?>
            <form action="<?= PROJECT_URL ?>/admin/competicoes/delete.php?id=<?= (int)$competition['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Apagar esta competicao? Todas as partidas e dados vinculados serao apagados. Esta acao nao pode ser desfeita.');">
                <?= csrf_field(); ?>
                <button class="c-btn-secondary">Excluir</button>
            </form>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/index.php" class="c-btn-secondary">
                Voltar
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if (($competition['context'] ?? '') === 'internal'): ?>
            <div class="c-dashboard-grid c-competition-metrics">
                <div class="c-dashboard-card c-card--info">
                    <h4>Partidas</h4>
                    <div class="c-metric"><?= count($matches) ?></div>
                </div>
                <div class="c-dashboard-card c-card--success">
                    <h4>Finalizadas</h4>
                    <div class="c-metric"><?= $finishedMatches ?></div>
                </div>
                <?php if ($classificationEnabled): ?>
                    <div class="c-dashboard-card c-card--info">
                        <h4>No ranking</h4>
                        <div class="c-metric"><?= count($classificationRows) ?></div>
                    </div>
                    <div class="c-dashboard-card c-card--neutral">
                        <h4>Lider</h4>
                        <div class="c-metric c-metric--text"><?= htmlspecialchars((string)($classificationLeader['name'] ?? '-')) ?></div>
                    </div>
                    <div class="c-dashboard-card c-card--neutral">
                        <h4>Pontos lider</h4>
                        <div class="c-metric"><?= htmlspecialchars(classificationFieldValue($classificationLeader ?? [], 'points')) ?></div>
                    </div>
                    <div class="c-dashboard-card c-card--neutral">
                        <h4>Presencas lider</h4>
                        <div class="c-metric"><?= htmlspecialchars(classificationFieldValue($classificationLeader ?? [], 'presences')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif (($competition['context'] ?? '') === 'external'): ?>
            <div class="c-dashboard-grid c-competition-metrics">
                <div class="c-dashboard-card c-card--info">
                    <h4>Partidas</h4>
                    <div class="c-metric"><?= count($matches) ?></div>
                </div>
                <?php if (!$isFriendlyExternal): ?>
                    <div class="c-dashboard-card c-card--info">
                        <h4>Pontos</h4>
                        <div class="c-metric"><?= $points ?></div>
                    </div>
                <?php endif; ?>
                <div class="c-dashboard-card c-card--neutral c-metric-combo-card">
                    <h4>Desempenho</h4>
                    <div class="c-metric-combo">
                        <span class="c-metric-combo-item c-metric-combo-item--success">
                            <small>Vitórias</small>
                            <strong><?= $wins ?></strong>
                        </span>
                        <span class="c-metric-combo-item c-metric-combo-item--neutral">
                            <small>Empates</small>
                            <strong><?= $draws ?></strong>
                        </span>
                        <span class="c-metric-combo-item c-metric-combo-item--danger">
                            <small>Derrotas</small>
                            <strong><?= $losses ?></strong>
                        </span>
                    </div>
                </div>
                <div class="c-dashboard-card c-card--neutral c-metric-combo-card">
                    <h4>Gols</h4>
                    <div class="c-metric-combo">
                        <span class="c-metric-combo-item c-metric-combo-item--info">
                            <small>A favor</small>
                            <strong><?= $goalsFor ?></strong>
                        </span>
                        <span class="c-metric-combo-item c-metric-combo-item--danger">
                            <small>Contra</small>
                            <strong><?= $goalsAgainst ?></strong>
                        </span>
                        <span class="c-metric-combo-item c-metric-combo-item--success">
                            <small>Saldo</small>
                            <strong><?= $goalBalance > 0 ? '+' . $goalBalance : $goalBalance ?></strong>
                        </span>
                    </div>
                </div>
                <div class="c-dashboard-card c-card--neutral c-metric-combo-card">
                    <h4>Cartões</h4>
                    <div class="c-metric-combo c-metric-combo--cards">
                        <span class="c-metric-combo-item c-metric-combo-item--warning">
                            <small>Amarelos</small>
                            <strong><?= $yellowCards ?></strong>
                        </span>
                        <span class="c-metric-combo-item c-metric-combo-item--danger">
                            <small>Vermelhos</small>
                            <strong><?= $redCards ?></strong>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="c-card">
            <h3>Partidas agendadas</h3>

            <?php if ($hasLiveMatch): ?>
                <p class="c-alert c-alert--info">Existe uma partida em andamento. Finalize a partida aberta antes de iniciar outra.</p>
            <?php endif; ?>

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
                                        <span class="c-badge <?= competitionViewMatchStatusBadge($match['status'] ?? null) ?>">
                                            <?= htmlspecialchars($matchStatusLabels[$match['status']] ?? $match['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$match['id'] ?>">
                                            Ver
                                        </a>
                                        <?php if (!$hasLiveMatch): ?>
                                            <form action="<?= PROJECT_URL ?>/admin/partidas/start.php?id=<?= (int)$match['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Iniciar esta partida?');">
                                                <?= csrf_field(); ?>
                                                <button class="c-btn-secondary">Iniciar</button>
                                            </form>
                                        <?php endif; ?>
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

            <?php if (empty($finishedMatchesList)): ?>
                <p>Nenhuma partida finalizada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <th>Placar</th>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finishedMatchesList as $match): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong></td>
                                    <td>
                                        <?php if ($match['score_a'] !== null && $match['score_b'] !== null): ?>
                                            <span class="c-competition-result-score <?= competitionViewResultClass($match) ?>">
                                                <?= (int)$match['score_a'] ?> x <?= (int)$match['score_b'] ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($match['match_date'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($match['venue'] ?? '-') ?></td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$match['id'] ?>">
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
.c-competition-metrics {
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
}

.c-competition-metrics .c-dashboard-card {
    min-height: 86px;
    padding: 14px;
}

.c-competition-metrics .c-dashboard-card h4 {
    margin-bottom: 8px;
}

.c-competition-metrics .c-metric {
    font-size: 22px;
    line-height: 1.1;
}

.c-competition-metrics .c-metric--text {
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-metric-combo-card {
    min-width: 0;
}

.c-metric-combo {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.c-metric-combo--cards {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.c-metric-combo-item {
    display: grid;
    gap: 2px;
    min-width: 0;
}

.c-metric-combo-item small {
    color: var(--text-secondary);
    font-size: 10px;
    font-weight: 700;
    line-height: 1.1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-metric-combo-item strong {
    font-size: 18px;
    line-height: 1.1;
}

.c-metric-combo-item--info strong {
    color: var(--info-color, #0891b2);
}

.c-metric-combo-item--danger strong {
    color: var(--danger-color, #ef4444);
}

.c-metric-combo-item--success strong {
    color: var(--success-color, #22c55e);
}

.c-metric-combo-item--neutral strong {
    color: var(--text-primary);
}

.c-metric-combo-item--warning strong {
    color: var(--warning-color, #f59e0b);
}

.c-competition-result-score {
    display: inline-grid;
    min-width: 64px;
    height: 28px;
    place-items: center;
    border-radius: 6px;
    font-weight: 900;
}

.c-competition-result-score--win {
    background: color-mix(in srgb, var(--success-color, #22c55e) 16%, transparent);
    color: var(--success-color, #22c55e);
}

.c-competition-result-score--draw {
    background: color-mix(in srgb, var(--text-secondary, #94a3b8) 16%, transparent);
    color: var(--text-primary);
}

.c-competition-result-score--loss {
    background: color-mix(in srgb, var(--danger-color, #ef4444) 16%, transparent);
    color: var(--danger-color, #ef4444);
}

@media (max-width: 1100px) {
    .c-competition-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .c-competition-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
