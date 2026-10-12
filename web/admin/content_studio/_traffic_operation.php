<?php
$operation=TrafficOperationsService::plan($pdo,$id);
$reports=TrafficOperationsService::reports($pdo,$id);
$trafficReportsEditable=true;
require dirname(__DIR__,3).'/modules/trafego/web/admin/trafego/operation_view.php';
$approval=TrafficApprovalService::latest($pdo,$id);
$trafficApprovalEditable=false;
require dirname(__DIR__,3).'/modules/trafego/web/admin/trafego/approval_view.php';
if ($operation && in_array($row['status'],['prepared','platform_rejected'],true)
    && (!$approval || !in_array($approval['status'],['pending','approved'],true))):
?>
<section class="traffic-section"><h2>Enviar planejamento ao cliente</h2>
<form method="post" action="/app/actions/content_studio/traffic_review.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="decision" value="approval_offer">
<button class="c-btn-secondary" <?= !empty($row['conversation_open'])?'disabled':'' ?>>Solicitar aprovacao do planejamento</button>
</form></section>
<?php endif;
if (in_array($row['status'],['prepared','platform_rejected'],true)):
    $plan=$operation ?? ['platform'=>$row['platform']==='recommend'?'':$row['platform'],'objective'=>$row['objective'],
        'region'=>$row['region'],'audience'=>$row['audience'],'starts_at'=>$row['starts_at'],'ends_at'=>$row['ends_at']];
?>
<section class="traffic-section"><h2>Planejamento operacional</h2>
<form class="traffic-form" method="post" action="/app/actions/content_studio/traffic_review.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="decision" value="operation_save">
<div class="traffic-grid"><label>Plataforma definida<select class="c-input" name="platform" required><option value="">Selecione</option><option value="meta" <?= $plan['platform']==='meta'?'selected':'' ?>>Meta</option><option value="google" <?= $plan['platform']==='google'?'selected':'' ?>>Google</option></select></label>
<?php foreach (['objective'=>'Objetivo definido','region'=>'Regiao definida','placements'=>'Canais / posicionamentos','ad_title'=>'Titulo do anuncio'] as $key=>$label): ?><label><?= $label ?><input class="c-input" name="<?= $key ?>" maxlength="<?= $key==='placements'?255:($key==='ad_title'?160:($key==='region'?180:120)) ?>" <?= $key!=='placements'?'required':'' ?> value="<?= trafficEscape($plan[$key] ?? '') ?>"></label><?php endforeach; ?></div>
<?php foreach (['audience'=>'Publico definido','ad_text'=>'Texto do anuncio'] as $key=>$label): ?><label><?= $label ?><textarea class="c-input" rows="3" name="<?= $key ?>" maxlength="<?= $key==='ad_text'?5000:3000 ?>" <?= $key==='ad_text'?'required':'' ?>><?= trafficEscape($plan[$key] ?? '') ?></textarea></label><?php endforeach; ?>
<div class="traffic-grid"><?php foreach (['starts_at'=>'Inicio planejado','ends_at'=>'Fim planejado'] as $key=>$label): ?><label><?= $label ?><input class="c-input" name="<?= $key ?>" type="date" required value="<?= trafficEscape($plan[$key] ?? '') ?>"></label><?php endforeach; ?></div>
<?php foreach (['creative_url'=>'Link do material do anuncio','external_campaign_url'=>'Link de referencia da campanha','evidence_url'=>'Link de referencia da operacao'] as $key=>$label): ?><label><?= $label ?><input class="c-input" type="url" name="<?= $key ?>" maxlength="500" value="<?= trafficEscape($plan[$key] ?? '') ?>"></label><?php endforeach; ?>
<label>ID da campanha na plataforma<input class="c-input" name="external_campaign_id" maxlength="120" value="<?= trafficEscape($plan['external_campaign_id'] ?? '') ?>"></label>
<button class="c-btn-secondary">Salvar planejamento</button></form></section>
<?php endif; ?>
<?php $transitions=TrafficOperationsService::TRANSITIONS[$row['status']] ?? []; if ($transitions): ?>
<section class="traffic-section"><h2>Registro manual na plataforma</h2>
<form class="traffic-form" method="post" action="/app/actions/content_studio/traffic_review.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="decision" value="operation_transition">
<label>Status observado<select class="c-input" name="operation_status"><?php foreach ($transitions as $state): ?><option value="<?= $state ?>" <?= ($state==='platform_review' || ($state==='active' && $row['status']==='platform_review')) && !TrafficApprovalService::isApproved($pdo,$row)?'disabled':'' ?>><?= trafficEscape(TrafficRequestService::STATUSES[$state]) ?></option><?php endforeach; ?></select></label>
<label>Motivo / resumo<textarea class="c-input" name="operation_notes" rows="3" maxlength="3000"></textarea></label>
<label class="traffic-check"><input type="checkbox" name="manual_confirmed" value="1" required>Confirmo que realizei ou verifiquei esta etapa na plataforma de anuncios.</label>
<button class="c-btn-secondary" <?= !$operation || !empty($row['conversation_open']) || (in_array($row['status'],['prepared','platform_rejected'],true) && !TrafficApprovalService::isApproved($pdo,$row))?'disabled':'' ?>>Registrar etapa</button>
</form></section><?php endif; ?>
<?php if (in_array($row['status'],['active','paused','completed'],true)):
    $reportDay=is_string($_GET['report_day'] ?? null) ? $_GET['report_day'] : date('Y-m-d');
    $reportData=[];
    foreach ($reports as $record) if ($record['report_date']===$reportDay) { $reportData=$record; break; }
?>
<section class="traffic-section" id="daily-results"><h2>Resultados por dia</h2><form class="traffic-form" method="post" action="/app/actions/content_studio/traffic_review.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="decision" value="report_save">
<label>Dia dos resultados<input class="c-input" type="date" name="report_date" required max="<?= date('Y-m-d') ?>" value="<?= trafficEscape($reportData['report_date'] ?? date('Y-m-d')) ?>"></label>
<div class="traffic-grid"><?php foreach (['impressions'=>'Impressoes do dia','link_clicks'=>'Cliques no link do dia','visits'=>'Visitas medidas no dia'] as $key=>$label): ?><label><?= $label ?><input class="c-input" type="number" min="0" max="2147483647" name="<?= $key ?>" value="<?= trafficEscape($reportData[$key] ?? '') ?>"></label><?php endforeach; ?></div>
<label>Fonte dos resultados<input class="c-input" name="source" maxlength="180" required value="<?= trafficEscape($reportData['source'] ?? '') ?>"></label>
<label>Link do relatorio / evidencia<input class="c-input" type="url" name="report_evidence_url" maxlength="500" required value="<?= trafficEscape($reportData['evidence_url'] ?? '') ?>"></label>
<label>Observacoes<textarea class="c-input" name="report_notes" rows="3" maxlength="3000"><?= trafficEscape($reportData['notes'] ?? '') ?></textarea></label>
<button class="c-btn-secondary">Salvar resultados do dia</button>
</form></section><?php endif; ?>
