<section class="traffic-section"><h2><?= $delivery?'Entrega encerrada':'Relatorio da campanha' ?></h2>
<div class="traffic-actions"><a class="c-btn-secondary" href="<?= trafficEscape($trafficReportUrl) ?>" target="_blank" rel="noopener noreferrer">Consultar relatorio <?= $delivery?'final':'parcial' ?></a><a class="c-btn-secondary" href="<?= trafficEscape($trafficReportUrl.'&format=csv') ?>">Exportar resultados CSV</a></div>
<?php if ($delivery): ?><p>Encerrado em <?= trafficEscape($delivery['finalized_at']) ?>.</p><p style="white-space:pre-wrap"><?= trafficEscape($delivery['summary']) ?></p><a href="<?= trafficEscape($delivery['evidence_url']) ?>" target="_blank" rel="noopener noreferrer">Referencia final da entrega</a><?php endif; ?>
</section>
