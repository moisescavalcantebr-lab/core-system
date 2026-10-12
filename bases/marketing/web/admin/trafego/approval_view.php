<?php if ($approval): $approvedPlan=json_decode($approval['snapshot'],true,512,JSON_THROW_ON_ERROR); ?>
<section class="traffic-section"><h2>Aprovacao do planejamento · versao <?= (int)$approval['version'] ?></h2>
<p><span class="c-badge"><?= trafficEscape(TrafficApprovalService::STATUSES[$approval['status']] ?? $approval['status']) ?></span></p>
<details <?= $approval['status']==='pending'?'open':'' ?>><summary style="cursor:pointer;padding:8px 0">Planejamento apresentado nesta versao</summary>
<dl class="traffic-details"><?php foreach (['destination_url'=>'Destino','language'=>'Idioma',...TrafficOperationsService::PLAN_FIELDS] as $key=>$label): if (!array_key_exists($key,$approvedPlan)) continue; ?>
<div><dt><?= trafficEscape($label) ?></dt><dd><?= trafficEscape($key==='platform' ? (TrafficRequestService::PLATFORMS[$approvedPlan[$key]] ?? $approvedPlan[$key]) : ($approvedPlan[$key] ?: '-')) ?></dd></div>
<?php endforeach; ?></dl>
</details>
<p>Enviado em <?= trafficEscape($approval['offered_at']) ?><?php if ($approval['decided_at']): ?> · Decisao em <?= trafficEscape($approval['decided_at']) ?><?php endif; ?></p>
<?php if ($approval['message']): ?><p style="white-space:pre-wrap"><?= trafficEscape($approval['message']) ?></p><?php endif; ?>
<?php if (!empty($trafficApprovalEditable) && $approval['status']==='pending' && empty($row['conversation_open']) && in_array($row['status'],['prepared','platform_rejected'],true)): ?>
<form class="traffic-form" method="post" action="<?= PROJECT_URL ?>/admin/trafego/save.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="approval_version" value="<?= (int)$approval['version'] ?>">
<label class="traffic-check"><input type="checkbox" name="approval_confirmed" value="1" required>Li e aprovo o destino, o conteudo, o publico e o periodo desta versao.</label>
<button class="c-btn-secondary" name="action" value="approve">Aprovar planejamento</button>
</form>
<form class="traffic-form" method="post" action="<?= PROJECT_URL ?>/admin/trafego/save.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><input type="hidden" name="approval_version" value="<?= (int)$approval['version'] ?>">
<label>Ajustes desejados<textarea class="c-input" name="message" rows="3" maxlength="3000" required><?= trafficEscape($approvalMessage ?? '') ?></textarea></label>
<button class="c-btn-secondary" name="action" value="request_changes">Solicitar ajustes</button>
</form>
<?php endif; ?></section>
<?php endif; ?>
