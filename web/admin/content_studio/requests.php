<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';
require APP_PATH.'/helpers/auth.php';
requireAdmin();
ContentStudioService::ensureSchema($pdo);
TrafficRequestService::ensureSchema($pdo);
$title='Solicitacoes de trafego'; $id=(int)($_GET['id']??0); $status=(string)($_GET['status']??'');
$rows=TrafficRequestService::list($pdo,null,$status); $row=null; $events=[];
if ($id) {
    try { $row=TrafficRequestService::find($pdo,$id); if ($row['status']==='draft') throw new RuntimeException('Rascunho privado do projeto.'); $events=TrafficRequestService::events($pdo,$id); }
    catch (RuntimeException $e) { http_response_code(404); exit('Solicitacao indisponivel.'); }
}
function trafficEscape(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
ob_start();
?>
<div class="c-page c-content-studio"><div class="c-page-header"><h1>Solicitacoes de trafego</h1><?php require __DIR__.'/_nav.php'; ?></div>
<?php flash_show(); ?>
<?php if (!$row): ?>
<form method="get" class="traffic-actions"><label>Status <select class="c-input" name="status"><option value="">Todos</option><?php foreach (TrafficRequestService::STATUSES as $value=>$label): if ($value==='draft') continue; ?><option value="<?= $value ?>" <?= $status===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="c-btn-secondary">Filtrar</button></form>
<?php if (!$rows): ?><p>Nenhuma solicitacao recebida.</p><?php else: ?><div class="traffic-table"><table class="c-table"><thead><tr><th>Solicitacao</th><th>Projeto</th><th>Plataforma</th><th>Status</th><th>Atualizada</th><th></th></tr></thead><tbody><?php foreach ($rows as $item): ?><tr><td><?= trafficEscape($item['title']) ?></td><td><?= trafficEscape($item['project_name']) ?></td><td><?= trafficEscape(TrafficRequestService::PLATFORMS[$item['platform']]) ?></td><td><?= trafficEscape(TrafficRequestService::STATUSES[$item['status']]) ?></td><td><?= trafficEscape($item['updated_at']) ?></td><td><a class="c-btn-secondary" href="?id=<?= (int)$item['id'] ?>">Analisar</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php else: ?>
<div class="traffic-actions"><a class="c-btn-secondary" href="requests.php">Voltar</a><span class="c-badge"><?= trafficEscape(TrafficRequestService::STATUSES[$row['status']]) ?></span></div>
<h2>#<?= $id ?> · <?= trafficEscape($row['title']) ?></h2>
<dl class="traffic-details"><?php foreach (['project_id'=>'Projeto ID','business_name'=>'Negocio','contact_name'=>'Responsavel','contact_email'=>'E-mail','contact_phone'=>'Telefone','destination_url'=>'Destino','page_title'=>'Pagina do projeto','platform'=>'Plataforma solicitada','objective'=>'Objetivo','region'=>'Regiao','audience'=>'Publico','language'=>'Idioma','starts_at'=>'Inicio desejado','ends_at'=>'Fim desejado','materials'=>'Materiais e referencias','notes'=>'Observacoes','authorized_at'=>'Autorizacao registrada'] as $key=>$label): ?><div><dt><?= $label ?></dt><dd><?= trafficEscape($row[$key] ?: '-') ?></dd></div><?php endforeach; ?></dl>
<?php if (!empty($row['guidance_question'])): ?><section class="traffic-section"><h2>Duvida do cliente</h2><p style="white-space:pre-wrap"><?= trafficEscape($row['guidance_question']) ?></p></section><?php endif; ?>
<?php if (in_array($row['status'],['submitted','reviewing','changes_requested','prepared'],true)): ?>
<form class="traffic-form traffic-section" method="post" action="/app/actions/content_studio/traffic_review.php">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>">
<label>Retorno ao cliente<textarea class="c-input" name="message" rows="4" maxlength="5000"></textarea></label>
<label>Mensagem predefinida<select class="c-input" id="traffic-message-template"><option value="">Selecione</option><?php foreach (TrafficRequestService::MESSAGE_TEMPLATES as $key=>$template): ?><option value="<?= $key ?>"><?= trafficEscape($template['label']) ?></option><?php endforeach; ?></select></label>
<div class="traffic-actions"><button class="c-btn-secondary" name="decision" value="reply">Enviar resposta</button><?php if ($row['status']==='submitted'): ?><button class="c-btn-secondary" name="decision" value="reviewing">Iniciar analise</button><?php elseif ($row['status']==='reviewing'): ?><button class="c-btn-secondary" name="decision" value="prepared">Preparar campanha em rascunho</button><?php endif; ?><?php if (in_array($row['status'],['submitted','reviewing'],true)): ?><button class="c-btn-secondary" name="decision" value="changes_requested">Solicitar ajustes</button><button class="c-btn-secondary" name="decision" value="rejected">Nao atender</button><?php endif; ?></div>
</form><?php endif; ?>
<?php if (in_array($row['status'],['submitted','reviewing','changes_requested','prepared'],true)): ?>
<script>
const templates=<?= json_encode(TrafficRequestService::MESSAGE_TEMPLATES,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?>;
const templateSelect=document.getElementById('traffic-message-template');
templateSelect.addEventListener('change',()=>{const selected=templates[templateSelect.value];if(!selected)return;const field=document.querySelector('textarea[name=message]');if(field.value.trim()&&!confirm('Substituir a mensagem que voce escreveu?')){templateSelect.value='';return;}field.value=selected.message;field.focus();});
</script><?php endif; ?>
<?php if ($row['campaign_id']): ?><p>Campanha #<?= (int)$row['campaign_id'] ?> em rascunho na preparacao. <a href="campaigns.php">Consultar campanhas</a></p><?php endif; ?>
<section class="traffic-section"><h2>Historico compartilhado</h2><ol class="traffic-events"><?php foreach ($events as $event): ?><li><small><?= trafficEscape($event['created_at']) ?> · <?= $event['actor_type']==='core'?'Content Studio':'Projeto' ?></small><p><?= trafficEscape($event['message']) ?></p></li><?php endforeach; ?></ol></section>
<?php endif; ?></div>
<?php require dirname(__DIR__,3).'/modules/trafego/web/admin/trafego/style.php'; $content=ob_get_clean(); require APP_PATH.'/views/layout_admin.php'; ?>
