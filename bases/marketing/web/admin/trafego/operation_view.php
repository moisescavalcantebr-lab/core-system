<?php if ($operation): ?>
<section class="traffic-section"><h2>Planejamento da divulgacao</h2>
<dl class="traffic-details"><?php foreach (TrafficOperationsService::PLAN_FIELDS as $key=>$label): ?><div><dt><?= trafficEscape($label) ?></dt><dd><?= trafficEscape($key==='platform' ? (TrafficRequestService::PLATFORMS[$operation[$key]] ?? $operation[$key]) : ($operation[$key] ?: '-')) ?></dd></div><?php endforeach; ?></dl>
</section>
<?php endif; ?>
<?php if ($reports): $totals=TrafficOperationsService::totals($reports); ?>
<section class="traffic-section"><h2>Resultados registrados manualmente</h2>
<div class="traffic-grid"><?php foreach (['impressions'=>'Impressoes','link_clicks'=>'Cliques no link','visits'=>'Visitas medidas'] as $key=>$label): ?><div><span><?= $label ?></span><strong style="display:block;font-size:22px;margin:8px 0"><?= $totals[$key]===null ? 'Nao informado' : number_format($totals[$key],0,',','.') ?></strong></div><?php endforeach; ?></div>
<div class="traffic-table"><table class="c-table"><thead><tr><th>Dia</th><th>Impressoes</th><th>Cliques no link</th><th>Visitas medidas</th><th>Fonte</th><th>Referencia</th><th>Observacoes</th></tr></thead><tbody>
<?php foreach ($reports as $report): ?><tr><td><?= trafficEscape($report['report_date']) ?><?php if (!empty($trafficReportsEditable)): ?> <a href="requests.php?id=<?= (int)$row['id'] ?>&amp;report_day=<?= trafficEscape($report['report_date']) ?>#daily-results">Editar</a><?php endif; ?></td><?php foreach (['impressions','link_clicks','visits'] as $key): ?><td><?= $report[$key]===null ? '-' : number_format((int)$report[$key],0,',','.') ?></td><?php endforeach; ?><td><?= trafficEscape($report['source']) ?></td><td><a href="<?= trafficEscape($report['evidence_url']) ?>" target="_blank" rel="noopener noreferrer">Consultar</a></td><td><?= trafficEscape($report['notes']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<h3>Cliques por dia</h3><div class="traffic-chart" aria-label="Cliques no link registrados por dia">
<?php $max=max(1,...array_map(static fn($report)=>(int)($report['link_clicks'] ?? 0),$reports)); foreach ($reports as $report): ?>
<div class="traffic-chart-row"><span><?= trafficEscape($report['report_date']) ?></span><div class="traffic-chart-track"><div class="traffic-chart-bar" style="width:<?= $report['link_clicks']===null ? 0 : (int)round((int)$report['link_clicks']/$max*100) ?>%"></div></div><strong><?= $report['link_clicks']===null ? '-' : (int)$report['link_clicks'] ?></strong></div>
<?php endforeach; ?></div></section>
<?php endif; ?>
<style>.traffic-chart{display:grid;gap:10px;margin:14px 0}.traffic-chart-row{display:grid;grid-template-columns:90px minmax(0,1fr) 60px;align-items:center;gap:10px}.traffic-chart-track{height:12px;background:var(--bg-hover,#1e293b)}.traffic-chart-bar{height:12px;background:#06b6d4}.traffic-chart-row strong{text-align:right}</style>
