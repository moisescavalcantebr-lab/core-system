<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';
require __DIR__ . '/competition_helpers.php';

$classificationFieldsPath = __DIR__ . '/../classificacao/fields.php';
$classificationEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('individual_classification')
    && is_file($classificationFieldsPath);

if ($classificationEnabled) {
    require_once $classificationFieldsPath;
}

requireProjectAdmin();

$friendlyCardsEnabled = function_exists('getSetting')
    ? getSetting('friendly_cards_enabled', '1') !== '0'
    : true;

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
$isChampionshipExternal = ($competition['context'] ?? '') === 'external' && ($competition['type'] ?? '') === 'championship';
$isDefaultFriendly = competitionIsDefaultFriendly($competition);
$showCardsMetric = !$isDefaultFriendly || $friendlyCardsEnabled;
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
$nextScheduledMatch = $scheduledMatches[0] ?? null;
$upcomingMatches = array_slice($scheduledMatches, 1);
$scheduledMatchLimit = projectPlanLimit('matches_scheduled', 4);
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
            <p class="c-page-subtitle">
                <?= $isDefaultFriendly
                    ? 'Partidas rápidas e estatísticas simples do seu time'
                    : ($isChampionshipExternal
                        ? 'Campeonato ativo com partidas, agenda e resumo do Meu Time'
                        : htmlspecialchars($contextLabels[$competition['context']] ?? $competition['context']) . ' · ' . htmlspecialchars($typeLabels[$competition['type']] ?? $competition['type'])) ?>
            </p>
        </div>

        <div>
            <?php if (!$isDefaultFriendly): ?>
                <a href="<?= PROJECT_URL ?>/admin/competicoes/edit.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                    Editar
                </a>
                <a href="<?= PROJECT_URL ?>/admin/competicoes/rules.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                    Regras
                </a>
            <?php endif; ?>
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
            <?php if (!$isDefaultFriendly): ?>
                <form action="<?= PROJECT_URL ?>/admin/competicoes/delete.php?id=<?= (int)$competition['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Apagar esta competicao? Todas as partidas e dados vinculados serao apagados. Esta acao nao pode ser desfeita.');">
                    <?= csrf_field(); ?>
                    <button class="c-btn-secondary">Excluir</button>
                </form>
            <?php endif; ?>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/index.php" class="c-btn-secondary">
                Voltar
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-competition-overview">
            <div class="c-competition-metrics">
                <?php if (($competition['context'] ?? '') === 'internal'): ?>
                    <div class="c-dashboard-card c-card--info">
                        <h4>Partidas</h4>
                        <div class="c-metric"><?= count($matches) ?></div>
                        <div class="c-metric-notes">
                            <small><?= $finishedMatches ?> finalizada<?= $finishedMatches === 1 ? '' : 's' ?></small>
                            <small><?= $scheduledMatchesCount ?>/<?= $scheduledMatchLimit ?> agendadas</small>
                        </div>
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
                    <?php endif; ?>
                <?php elseif (($competition['context'] ?? '') === 'external'): ?>
                    <div class="c-dashboard-card c-card--info">
                        <h4>Partidas</h4>
                        <div class="c-metric"><?= count($matches) ?></div>
                        <div class="c-metric-notes">
                            <small><?= $finishedMatches ?> finalizada<?= $finishedMatches === 1 ? '' : 's' ?></small>
                            <small><?= $scheduledMatchesCount ?>/<?= $scheduledMatchLimit ?> agendadas</small>
                        </div>
                    </div>
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
                    <?php if ($showCardsMetric): ?>
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
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="c-card c-next-match-card">
                <div class="c-next-match-header">
                    <div>
                        <h3>Próxima partida</h3>
                        <p>Use este destaque para iniciar apenas o próximo jogo da agenda.</p>
                    </div>
                    <?php if ($canCreateMatch): ?>
                        <a href="<?= PROJECT_URL ?>/admin/partidas/create.php?competition_id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                            Nova partida
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($hasLiveMatch): ?>
                    <p class="c-alert c-alert--info">Existe uma partida em andamento. Finalize a partida aberta antes de iniciar outra.</p>
                <?php elseif (!$nextScheduledMatch): ?>
                    <p>Nenhuma partida agendada.</p>
                <?php else: ?>
                    <div class="c-next-match-body">
                        <div>
                            <span class="c-next-label">Partida</span>
                            <strong><?= htmlspecialchars($nextScheduledMatch['participant_a'] . ' x ' . ($nextScheduledMatch['participant_b'] ?? '-')) ?></strong>
                        </div>
                        <div>
                            <span class="c-next-label">Data e hora</span>
                            <strong><?= htmlspecialchars($nextScheduledMatch['match_date'] ?? '-') ?></strong>
                        </div>
                        <div>
                            <span class="c-next-label">Local</span>
                            <strong><?= htmlspecialchars($nextScheduledMatch['venue'] ?? '-') ?></strong>
                        </div>
                    </div>
                    <div class="c-next-match-actions">
                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$nextScheduledMatch['id'] ?>">
                            Ver
                        </a>
                        <form action="<?= PROJECT_URL ?>/admin/partidas/start.php?id=<?= (int)$nextScheduledMatch['id'] ?>" method="POST" onsubmit="return confirm('Iniciar esta partida?');">
                            <?= csrf_field(); ?>
                            <button class="c-btn-secondary">Iniciar partida</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="c-card c-history-card">
                <h3>Histórico</h3>
                <p><?= count($finishedMatchesList) ?> partida(s) finalizada(s).</p>
                <a href="<?= PROJECT_URL ?>/admin/competicoes/finished.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                    Ver finalizadas
                </a>
            </div>

            <div class="c-card c-upcoming-card">
                <h3>Próximas na agenda</h3>

                <?php if (empty($upcomingMatches)): ?>
                    <p>Nenhuma outra partida agendada.</p>
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
                                <?php foreach ($upcomingMatches as $match): ?>
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
</div>

<style>
.c-competition-overview {
    display: grid;
    grid-template-columns: minmax(170px, .55fr) minmax(0, 1.2fr) minmax(220px, .72fr);
    gap: 12px;
    margin-bottom: 14px;
    align-items: start;
}

.c-next-match-card,
.c-history-card {
    min-height: 190px;
}

.c-upcoming-card {
    grid-column: 2 / 4;
    grid-row: 2;
}

.c-next-match-header,
.c-next-match-actions {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}

.c-next-match-header p {
    margin: 4px 0 0;
    color: var(--text-secondary);
}

.c-next-match-body {
    display: grid;
    grid-template-columns: 1.35fr .8fr .8fr;
    gap: 8px;
    margin: 12px 0;
}

.c-next-match-body > div {
    padding: 10px;
    border: 1px solid var(--border-color, rgba(255,255,255,.14));
    background: color-mix(in srgb, var(--sidebar-bg, #111827) 76%, transparent);
}

.c-next-label {
    display: block;
    margin-bottom: 6px;
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 700;
}

.c-next-match-actions {
    justify-content: flex-start;
    align-items: center;
}

.c-history-card {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.c-competition-metrics {
    display: grid;
    grid-row: 1 / 3;
    grid-template-columns: 1fr;
    gap: 8px;
}

.c-competition-metrics .c-dashboard-card {
    min-height: 70px;
    padding: 12px;
}

.c-competition-metrics .c-dashboard-card h4 {
    margin-bottom: 8px;
}

.c-competition-metrics .c-metric {
    font-size: 22px;
    line-height: 1.1;
}

.c-metric-notes {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 10px;
    margin-top: 4px;
}

.c-metric-notes small {
    color: var(--text-secondary);
    font-size: 11px;
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
    .c-competition-overview {
        grid-template-columns: 1fr;
    }

    .c-upcoming-card {
        grid-column: auto;
        grid-row: auto;
    }

    .c-competition-metrics {
        grid-row: auto;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-next-match-card,
    .c-history-card {
        min-height: auto;
    }
}

@media (max-width: 700px) {
    .c-competition-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-competition-metrics .c-dashboard-card {
        min-height: 88px;
    }

    .c-next-match-body {
        grid-template-columns: 1fr;
    }

    .c-next-match-header {
        flex-direction: column;
    }

    .c-next-match-actions {
        flex-direction: row;
        flex-wrap: nowrap;
    }

    .c-next-match-actions > a,
    .c-next-match-actions > form {
        flex: 1 1 0;
    }

    .c-next-match-actions button,
    .c-next-match-actions .c-btn-secondary {
        width: 100%;
        text-align: center;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
