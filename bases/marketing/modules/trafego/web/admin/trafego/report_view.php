<?php
$data=$report['data']; $delivery=$report['delivery']; $row=$data['request'];
header('Cache-Control: private, no-store');
if (($_GET['format'] ?? '')==='csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="trafego-'.(int)$row['id'].($delivery?'-final':'-parcial').'.csv"');
    echo TrafficDeliveryService::csv($data['reports']); exit;
}
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatorio de trafego #<?= (int)$row['id'] ?></title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#17212b;font:15px/1.5 Arial,sans-serif}main{max-width:1100px;margin:0 auto;padding:28px 20px}h1{font-size:26px;margin:0}h2{font-size:19px}h3{font-size:16px}p,dd{overflow-wrap:anywhere}.toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:18px 0}a{color:#075f80}button,.toolbar a{padding:8px 12px;border:1px solid #8c9aa7;border-radius:4px;background:white;font:inherit;text-decoration:none;color:#17212b;cursor:pointer}.traffic-section{border-top:1px solid #b9c3cc;margin-top:24px;padding-top:16px}.traffic-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.traffic-details div{min-width:0}dt{color:#495768;font-size:13px}dd{margin:3px 0;white-space:pre-wrap}.traffic-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.traffic-table{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:13px}th,td{padding:10px;text-align:left;border-bottom:1px solid #c5cdd4;vertical-align:top}th{background:#e4e9ee}td:last-child{min-width:170px}.notice{color:#495768}.traffic-chart-track{background:#dde6ec!important}.traffic-chart-row{font-size:13px} @media(max-width:600px){main{padding:20px 14px}.traffic-details{grid-template-columns:1fr}.traffic-grid{grid-template-columns:1fr}h1{font-size:23px}}@media print{body{background:white}.toolbar{display:none}main{max-width:none;padding:0}.traffic-table{overflow:visible}table{font-size:10px}td,th{overflow-wrap:anywhere}td:last-child{min-width:0}h2,h3{break-after:avoid}tr{break-inside:avoid}}
@media print{table{table-layout:fixed}th,td{overflow-wrap:anywhere}}
</style></head><body><main>
<h1>Relatorio <?= $delivery?'final':'parcial' ?> de trafego #<?= (int)$row['id'] ?></h1>
<p><?= trafficEscape($row['title']) ?><?php if ($row['business_name']): ?> · <?= trafficEscape($row['business_name']) ?><?php endif; ?></p>
<div class="toolbar"><a href="?id=<?= (int)$row['id'] ?>&amp;format=csv">Exportar resultados CSV</a><button type="button" onclick="window.print()">Imprimir relatorio</button></div>
<dl class="traffic-details"><div><dt>Destino</dt><dd><?= trafficEscape($row['destination_url']) ?></dd></div><div><dt>Campanha</dt><dd>#<?= (int)$row['campaign_id'] ?> · <?= trafficEscape(TrafficRequestService::STATUSES[$row['status']]) ?></dd></div></dl>
<p class="notice">Dados registrados manualmente pelo Studio. Cliques no link e visitas medidas sao metricas diferentes. Leads permanecem no projeto do cliente.</p>
<?php if ($delivery): ?><section class="traffic-section"><h2>Encerramento da entrega</h2><p style="white-space:pre-wrap"><?= trafficEscape($delivery['summary']) ?></p><p>Encerrado em <?= trafficEscape($delivery['finalized_at']) ?> · <a href="<?= trafficEscape($delivery['evidence_url']) ?>" target="_blank" rel="noopener noreferrer">Referencia final</a></p></section><?php else: ?><p class="notice">Relatorio parcial, sujeito a atualizacoes ate o encerramento da entrega.</p><?php endif; ?>
<section class="traffic-section"><h2>Cobertura dos dados</h2><p><?= (int)$data['coverage']['days'] ?> dia(s) com registros<?php if ($data['coverage']['first_day']): ?>, de <?= trafficEscape($data['coverage']['first_day']) ?> a <?= trafficEscape($data['coverage']['last_day']) ?><?php endif; ?>.</p><ul><?php foreach (['impressions'=>'Impressoes','link_clicks'=>'Cliques no link','visits'=>'Visitas medidas'] as $key=>$label): ?><li><?= $label ?>: <?= (int)$data['coverage']['known'][$key] ?> de <?= (int)$data['coverage']['days'] ?> dia(s) informados.</li><?php endforeach; ?></ul><p class="notice">Os totais somam apenas dados informados; dias e metricas ausentes nao equivalem a zero.</p></section>
<?php $operation=$data['plan']; $reports=$data['reports']; $trafficReportsEditable=false; require __DIR__.'/operation_view.php'; ?>
<?php if (!$reports): ?><p>Nenhum resultado diario registrado.</p><?php endif; ?>
</main></body></html>
