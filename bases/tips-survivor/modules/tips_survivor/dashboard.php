<?php
declare(strict_types=1);

require_once PROJECT_PATH . '/web/admin/tips_survivor/helpers.php';

global $pdo;

if (!$pdo instanceof PDO) {
    return [
        'type' => 'panel',
        'module' => 'tips_survivor',
        'order' => 10,
        'size' => 'wide',
        'html' => '<section class="dash-module"><div class="dash-module-head"><div><h3>Tips Survivor</h3><p>Banco de dados indisponivel para este painel.</p></div></div></section>',
    ];
}

tipsEnsureSchema($pdo);
$summary = tipsDashboardSummary($pdo);

ob_start();
?>
<section class="dash-module dash-module--tips">
    <div class="dash-module-head">
        <div>
            <h3>Tips Survivor</h3>
            <p>Competicoes, palpites, vidas e tokens internos.</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/tips_survivor/index.php" class="c-btn-secondary">Abrir</a>
    </div>

    <div class="dash-tips-grid">
        <div class="dash-metric-card"><span>Competicoes</span><strong><?= (int)$summary['total_competitions'] ?></strong><small><?= (int)$summary['active_competitions'] ?> abertas/ativas</small></div>
        <div class="dash-metric-card"><span>Jogadores ativos</span><strong><?= (int)$summary['start_users'] ?></strong><small><?= (int)$summary['free_users'] ?> em modo inicial</small></div>
        <div class="dash-metric-card"><span>Partidas pendentes</span><strong><?= (int)$summary['scheduled_matches'] ?></strong><small><?= (int)$summary['matches_to_process'] ?> para processar</small></div>
        <div class="dash-metric-card"><span>Participantes vivos</span><strong><?= (int)$summary['active_players'] ?></strong><small><?= (int)$summary['eliminated_players'] ?> eliminados</small></div>
        <div class="dash-metric-card"><span>Tokens internos</span><strong><?= (int)$summary['tokens_total'] ?></strong><small>Sem conversao financeira</small></div>
        <div class="dash-metric-card"><span>Campeoes</span><strong><?= (int)$summary['champions'] ?></strong><small>Historico geral</small></div>
    </div>
</section>

<style>
.dash-module--tips {
    grid-column: 1 / -1;
}

.dash-tips-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

@media (max-width: 900px) {
    .dash-tips-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .dash-tips-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<?php

return [
    'type' => 'panel',
    'module' => 'tips_survivor',
    'order' => 10,
    'size' => 'wide',
    'html' => ob_get_clean(),
];
