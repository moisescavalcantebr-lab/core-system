<?php
declare(strict_types=1);

global $pdo;

if (!$pdo instanceof PDO) {
    return [];
}

$team = [];
$roster = [
    'starters_total' => 0,
    'reserves_total' => 0,
    'active_total' => 0,
    'pending_total' => 0,
    'inactive_total' => 0,
];
$friendly = null;
$friendlySummary = [
    'scheduled_total' => 0,
    'finished_total' => 0,
    'live_total' => 0,
    'win_total' => 0,
    'draw_total' => 0,
    'loss_total' => 0,
];
$match = null;
$matchState = 'Sem agenda';

try {
    $team = $pdo->query("SELECT * FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];

    $roster = $pdo->query("
        SELECT
            COALESCE(SUM(status = 'active' AND COALESCE(roster_status, 'titular') = 'titular'), 0) AS starters_total,
            COALESCE(SUM(status = 'active' AND roster_status = 'reserva'), 0) AS reserves_total,
            COALESCE(SUM(status = 'active'), 0) AS active_total,
            COALESCE(SUM(status = 'pending'), 0) AS pending_total,
            COALESCE(SUM(status = 'inactive'), 0) AS inactive_total
        FROM players
    ")->fetch(PDO::FETCH_ASSOC) ?: $roster;

    $friendly = $pdo->query("
        SELECT id, name
        FROM competitions
        WHERE type = 'friendly'
        ORDER BY id ASC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($friendly) {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(status = 'scheduled'), 0) AS scheduled_total,
                COALESCE(SUM(status = 'finished'), 0) AS finished_total,
                COALESCE(SUM(status = 'live'), 0) AS live_total,
                COALESCE(SUM(status = 'finished' AND score_a IS NOT NULL AND score_b IS NOT NULL AND score_a > score_b), 0) AS win_total,
                COALESCE(SUM(status = 'finished' AND score_a IS NOT NULL AND score_b IS NOT NULL AND score_a = score_b), 0) AS draw_total,
                COALESCE(SUM(status = 'finished' AND score_a IS NOT NULL AND score_b IS NOT NULL AND score_a < score_b), 0) AS loss_total
            FROM matches
            WHERE competition_id = ?
        ");
        $stmt->execute([(int)$friendly['id']]);
        $friendlySummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: $friendlySummary;
    }

    $live = $pdo->query("
        SELECT participant_a, participant_b, match_date
        FROM matches
        WHERE status = 'live'
        ORDER BY COALESCE(match_date, created_at) DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    $next = null;
    if (!$live) {
        $next = $pdo->query("
            SELECT participant_a, participant_b, match_date
            FROM matches
            WHERE status = 'scheduled'
            ORDER BY COALESCE(match_date, created_at) ASC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $match = $live ?: $next;
    $matchState = $live ? 'Ao vivo' : ($next ? 'Proxima' : 'Sem agenda');
} catch (Throwable $e) {
    return [];
}

$teamName = (string)($team['name'] ?? 'Meu Time');
$plannedStarters = (int)($team['starters_count'] ?? 11);
$plannedReserves = min(8, (int)($team['reserves_count'] ?? 8));
$starters = (int)($roster['starters_total'] ?? 0);
$reserves = (int)($roster['reserves_total'] ?? 0);
$active = (int)($roster['active_total'] ?? 0);
$pending = (int)($roster['pending_total'] ?? 0);
$inactive = (int)($roster['inactive_total'] ?? 0);

ob_start();
?>
<section class="dash-module dash-module--football">
    <div class="dash-module-head">
        <div>
            <h3>Resumo do futebol</h3>
            <p>Elenco, escalação e partidas rápidas do projeto.</p>
        </div>
    </div>

    <div class="dash-football-layout">
        <div class="dash-chart-card dash-football-card">
            <h4>Próxima partida</h4>
            <?php if (!$match): ?>
                <p>Nenhuma partida agendada.</p>
            <?php else: ?>
                <div class="dash-status-card dash-football-status">
                    <span><?= htmlspecialchars($matchState) ?></span>
                    <strong class="dash-match-highlight">
                        <?= htmlspecialchars((string)$match['participant_a']) ?>
                        <small>x</small>
                        <?= htmlspecialchars((string)($match['participant_b'] ?? '-')) ?>
                    </strong>
                    <small><?= !empty($match['match_date']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$match['match_date']))) : '-' ?></small>
                </div>
            <?php endif; ?>
        </div>

        <div class="dash-chart-card dash-football-card">
            <h4>Jogadores</h4>
            <div class="dash-pair-card dash-football-metrics">
                <div>
                    <span>Ativos</span>
                    <strong class="dash-good"><?= $active ?></strong>
                </div>
                <div>
                    <span>Pendentes</span>
                    <strong><?= $pending ?></strong>
                </div>
                <div>
                    <span>Inativos</span>
                    <strong><?= $inactive ?></strong>
                </div>
            </div>
        </div>

        <div class="dash-chart-card dash-football-card">
            <h4>Amistoso</h4>
            <div class="dash-pair-card dash-football-metrics">
                <div>
                    <span>Agendadas</span>
                    <strong><?= (int)($friendlySummary['scheduled_total'] ?? 0) ?></strong>
                </div>
                <div>
                    <span>Finalizadas</span>
                    <strong><?= (int)($friendlySummary['finished_total'] ?? 0) ?></strong>
                </div>
                <div>
                    <span>Em andamento</span>
                    <strong class="dash-good"><?= (int)($friendlySummary['live_total'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <div class="dash-chart-card dash-football-card">
            <h4>Partidas</h4>
            <div class="dash-pair-card dash-football-metrics">
                <div>
                    <span>Vitórias</span>
                    <strong class="dash-good"><?= (int)($friendlySummary['win_total'] ?? 0) ?></strong>
                </div>
                <div>
                    <span>Empates</span>
                    <strong><?= (int)($friendlySummary['draw_total'] ?? 0) ?></strong>
                </div>
                <div>
                    <span>Derrotas</span>
                    <strong class="dash-bad"><?= (int)($friendlySummary['loss_total'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <div class="dash-chart-card">
            <h4>Treino</h4>
            <div class="dash-upgrade-box">
                <strong>Recurso do Start</strong>
                <p>Escalações internas e treino com dois times do elenco.</p>
                <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/upgrade/index.php">Ver upgrade</a>
            </div>
        </div>

        <div class="dash-chart-card">
            <h4>Campeonato</h4>
            <div class="dash-upgrade-box">
                <strong>Recurso do Start</strong>
                <p>Campeonato ativo com partidas, tabela e estatísticas avançadas.</p>
                <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/upgrade/index.php">Ver upgrade</a>
            </div>
        </div>
    </div>
</section>
<?php

return [
    'type' => 'panel',
    'module' => 'meu_time',
    'group' => 'futebol',
    'group_label' => 'Futebol',
    'order' => 10,
    'size' => 'wide',
    'html' => ob_get_clean(),
];
