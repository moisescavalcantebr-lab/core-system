<?php
declare(strict_types=1);

require_once PROJECT_PATH . '/web/admin/crypto_dca_manager/helpers.php';

global $pdo;

if (!$pdo instanceof PDO) {
    return [
        'type' => 'panel',
        'module' => 'crypto_dca_manager',
        'order' => 10,
        'size' => 'wide',
        'html' => '<section class="dash-module"><div class="dash-module-head"><div><h3>DCA Strategy</h3><p>Banco de dados indisponivel para este painel.</p></div></div></section>',
    ];
}

cryptoDcaEnsureSchema($pdo);
$summary = cryptoDcaDashboardSummary($pdo);

ob_start();
?>
<section class="dash-module dash-module--crypto">
    <div class="dash-module-head">
        <div>
            <h3>DCA Strategy</h3>
            <p>Contas, ciclos e performance cripto.</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php" class="c-btn-secondary">Abrir</a>
    </div>

    <div class="dash-crypto-grid">
        <div class="dash-metric-card">
            <span>Capital alocado</span>
            <strong><?= cryptoDcaMoney((float)$summary['capital_allocated']) ?></strong>
            <small>Total em ciclos</small>
        </div>
        <div class="dash-metric-card">
            <span>Lucro/prejuizo</span>
            <strong><?= cryptoDcaMoney((float)$summary['profit_total']) ?></strong>
            <small>Ciclos fechados</small>
        </div>
        <div class="dash-metric-card">
            <span>Capital em risco</span>
            <strong><?= cryptoDcaMoney((float)$summary['capital_risk']) ?></strong>
            <small>Ciclos abertos</small>
        </div>
        <div class="dash-metric-card">
            <span>Ativos ativos</span>
            <strong><?= (int)$summary['active_assets'] ?></strong>
            <small><?= (int)$summary['watch_assets'] ?> em observacao</small>
        </div>
        <div class="dash-metric-card">
            <span>Top 5</span>
            <strong><?= (int)$summary['top_assets'] ?></strong>
            <small>Alta conviccao</small>
        </div>
        <div class="dash-metric-card">
            <span>X2 / Recuperacao</span>
            <strong><?= (int)$summary['x2_assets'] ?></strong>
            <small>Candidatos por DCA</small>
        </div>
    </div>
</section>

<style>
.dash-module--crypto {
    grid-column: 1 / -1;
}

.dash-crypto-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

@media (max-width: 900px) {
    .dash-crypto-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .dash-crypto-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<?php

return [
    'type' => 'panel',
    'module' => 'crypto_dca_manager',
    'order' => 10,
    'size' => 'wide',
    'html' => ob_get_clean(),
];
