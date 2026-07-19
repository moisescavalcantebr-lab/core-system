<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireAdmin();
tipsEnsureSchema($pdo);

$title = 'Tips Survivor';
$summary = tipsDashboardSummary($pdo);
$competitions = tipsRecentCompetitions($pdo);
$matches = tipsRecentMatches($pdo, 6);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Tips Survivor</h1>
            <p class="c-page-subtitle">Palpites esportivos em modo survivor com vidas, pontos e tokens internos.</p>
        </div>
        <?= tipsNav('index') ?>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="tips-grid">
            <div class="tips-card"><span>Competicoes</span><strong><?= (int)$summary['total_competitions'] ?></strong><small><?= (int)$summary['active_competitions'] ?> abertas/ativas</small></div>
            <div class="tips-card"><span>Jogadores ativos</span><strong><?= (int)$summary['start_users'] ?></strong><small><?= (int)$summary['free_users'] ?> em modo inicial</small></div>
            <div class="tips-card"><span>Partidas pendentes</span><strong><?= (int)$summary['scheduled_matches'] ?></strong><small><?= (int)$summary['matches_to_process'] ?> para processar</small></div>
            <div class="tips-card"><span>Participantes ativos</span><strong><?= (int)$summary['active_players'] ?></strong><small><?= (int)$summary['eliminated_players'] ?> eliminados</small></div>
            <div class="tips-card"><span>Tokens internos</span><strong><?= (int)$summary['tokens_total'] ?></strong><small>Sem valor financeiro</small></div>
            <div class="tips-card"><span>Campeoes</span><strong><?= (int)$summary['champions'] ?></strong><small>Historico geral</small></div>
        </div>

        <div class="tips-panel-grid">
            <div class="c-card">
                <h3>Competicoes recentes</h3>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead><tr><th>Nome</th><th>Status</th><th>Participantes</th><th>Ativos</th></tr></thead>
                        <tbody>
                        <?php foreach ($competitions as $competition): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$competition['name']) ?></strong><br><small><?= htmlspecialchars((string)($competition['season'] ?? '-')) ?></small></td>
                                <td><span class="c-badge <?= tipsBadgeClass((string)$competition['status']) ?>"><?= htmlspecialchars(tipsStatusLabel((string)$competition['status'])) ?></span></td>
                                <td><?= (int)$competition['participants_count'] ?></td>
                                <td><?= (int)$competition['active_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($competitions)): ?>
                            <tr><td colspan="4">Nenhuma competicao cadastrada ainda.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="c-card">
                <h3>Regras do MVP</h3>
                <ul class="tips-rules-list">
                    <li>Tokens sao internos e nao possuem saque, venda ou conversao financeira.</li>
                    <li>Acertar vencedor mantem o participante vivo. Errar remove uma vida.</li>
                    <li>Placar, over, ambos marcam, escanteios e cartoes somam pontos.</li>
                    <li>O processamento precisa ser idempotente antes de liberar uso publico.</li>
                </ul>
            </div>
        </div>

        <div class="c-card">
            <h3>Ultimas partidas</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Partida</th><th>Competicao</th><th>Data</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($matches as $match): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$match['home_team']) ?> x <?= htmlspecialchars((string)$match['away_team']) ?></strong><br><small>Rodada <?= (int)$match['round_number'] ?></small></td>
                            <td><?= htmlspecialchars((string)$match['competition_name']) ?></td>
                            <td><?= htmlspecialchars((string)$match['match_datetime']) ?></td>
                            <td><span class="c-badge <?= tipsBadgeClass((string)$match['status']) ?>"><?= htmlspecialchars(tipsStatusLabel((string)$match['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($matches)): ?>
                        <tr><td colspan="4">Nenhuma partida cadastrada ainda.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
