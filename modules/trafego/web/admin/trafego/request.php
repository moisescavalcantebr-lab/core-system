<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectAdmin();
$title = 'Solicitacao de divulgacao'; $id = (int)($_GET['id'] ?? 0); $error = ''; $events = []; $pages = [];
$row = ['id'=>0,'revision'=>0,'status'=>'draft','platform'=>'recommend','destination_type'=>'external','language'=>'Portugues'];
try {
    [$core,$projectId] = trafficContext();
    if ($id) { $row = TrafficRequestService::find($core,$id,$projectId); $events = TrafficRequestService::events($core,$id,$projectId); }
    $pages = trafficPages($pdo);
} catch (Throwable $e) { $error = $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'Nao foi possivel abrir a solicitacao.'; http_response_code(503); }
$editable = in_array($row['status'],['draft','changes_requested'],true);
if (!$error && $editable && isset($_SESSION['traffic_form_input'])) {
    $input = $_SESSION['traffic_form_input'];
    unset($_SESSION['traffic_form_input']);
    if ((int)($input['id'] ?? 0) === $id) {
        foreach (['title','business_name','contact_phone','objective','region','audience','language','materials','notes',
            'destination_url','starts_at','ends_at','project_page_id'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) $row[$key] = $input[$key];
        }
        if (is_string($input['platform'] ?? null) && isset(TrafficRequestService::PLATFORMS[$input['platform']])) $row['platform'] = $input['platform'];
        if (in_array($input['destination_type'] ?? '',['page','external','whatsapp'],true)) $row['destination_type'] = $input['destination_type'];
    }
}
$user = projectUser();
ob_start();
?>
<div class="c-page-header"><h1><?= $id ? 'Solicitacao #'.$id : 'Solicitar divulgacao' ?></h1><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/trafego/index.php">Voltar</a></div>
<?php flash_show(); ?>
<?php if ($error): ?><p role="alert"><?= trafficEscape($error) ?></p><?php else: ?>
<p><span class="c-badge"><?= trafficEscape(TrafficRequestService::STATUSES[$row['status']]) ?></span></p>
<?php if ($editable): ?>
<form method="post" action="<?= PROJECT_URL ?>/admin/trafego/save.php" class="traffic-form">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>">
<div class="traffic-grid"><label>Titulo<input class="c-input" name="title" maxlength="160" required value="<?= trafficEscape($row['title'] ?? '') ?>"></label><label>Negocio / empresa<input class="c-input" name="business_name" maxlength="160" value="<?= trafficEscape($row['business_name'] ?? '') ?>"></label></div>
<p>Responsavel: <?= trafficEscape($user['name']) ?> · <?= trafficEscape($user['email']) ?></p>
<div class="traffic-grid"><label>Telefone de contato<input class="c-input" name="contact_phone" maxlength="40" value="<?= trafficEscape($row['contact_phone'] ?? '') ?>"></label><label>Plataforma<select class="c-input" name="platform"><?php foreach (TrafficRequestService::PLATFORMS as $value=>$label): ?><option value="<?= $value ?>" <?= $row['platform']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label></div>
<div class="traffic-grid"><label>Destino<select class="c-input" name="destination_type" id="traffic-type"><?php foreach (['external'=>'Link externo','page'=>'Landing Page do projeto','whatsapp'=>'WhatsApp'] as $value=>$label): ?><option value="<?= $value ?>" <?= $row['destination_type']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label data-page-field>Pagina publicada<select class="c-input" name="project_page_id"><option value="">Selecione</option><?php foreach ($pages as $page): ?><option value="<?= (int)$page['id'] ?>" <?= (int)($row['project_page_id']??0)===(int)$page['id']?'selected':'' ?>><?= trafficEscape($page['title']) ?></option><?php endforeach; ?></select></label></div>
<label data-url-field>Link de destino<input class="c-input" name="destination_url" type="url" maxlength="500" value="<?= trafficEscape($row['destination_url'] ?? '') ?>"></label>
<div class="traffic-grid"><label>Objetivo<input class="c-input" name="objective" maxlength="120" value="<?= trafficEscape($row['objective'] ?? '') ?>"></label><label>Regiao de divulgacao<input class="c-input" name="region" maxlength="180" value="<?= trafficEscape($row['region'] ?? '') ?>"></label></div>
<label>Publico desejado<textarea class="c-input" name="audience" rows="3" maxlength="3000"><?= trafficEscape($row['audience'] ?? '') ?></textarea></label>
<div class="traffic-grid"><label>Idioma<input class="c-input" name="language" maxlength="60" value="<?= trafficEscape($row['language']) ?>"></label><label>Inicio desejado<input class="c-input" name="starts_at" type="date" value="<?= trafficEscape($row['starts_at'] ?? '') ?>"></label><label>Fim desejado<input class="c-input" name="ends_at" type="date" value="<?= trafficEscape($row['ends_at'] ?? '') ?>"></label></div>
<label>Materiais e referencias<textarea class="c-input" name="materials" rows="3" maxlength="3000"><?= trafficEscape($row['materials'] ?? '') ?></textarea></label>
<label>Observacoes<textarea class="c-input" name="notes" rows="4" maxlength="5000"><?= trafficEscape($row['notes'] ?? '') ?></textarea></label>
<label class="traffic-check"><input type="checkbox" name="authorized" value="1">Confirmo que posso representar este negocio e autorizar a divulgacao do destino e dos materiais informados.</label>
<div class="traffic-actions"><button class="c-btn-secondary" name="action" value="save">Salvar rascunho</button><button class="c-btn-secondary" name="action" value="submit"><?= $row['status']==='changes_requested'?'Reenviar para analise':'Enviar para analise' ?></button></div>
</form>
<script>const type=document.getElementById('traffic-type');function destinationFields(){document.querySelector('[data-page-field]').hidden=type.value!=='page';document.querySelector('[data-url-field]').hidden=type.value==='page';}type.addEventListener('change',destinationFields);destinationFields();</script>
<?php else: ?>
<dl class="traffic-details"><?php foreach (['title'=>'Titulo','business_name'=>'Negocio','destination_url'=>'Destino','objective'=>'Objetivo','region'=>'Regiao','audience'=>'Publico','language'=>'Idioma','starts_at'=>'Inicio desejado','ends_at'=>'Fim desejado','materials'=>'Materiais','notes'=>'Observacoes'] as $key=>$label): ?><div><dt><?= $label ?></dt><dd><?= trafficEscape($row[$key] ?: '-') ?></dd></div><?php endforeach; ?></dl>
<?php endif; ?>
<?php if ($row['status']==='prepared'): ?><p class="traffic-notice">Campanha #<?= (int)$row['campaign_id'] ?> preparada em rascunho. Nenhum pagamento ou anuncio foi ativado.</p><?php endif; ?>
<?php if ($id && in_array($row['status'],['draft','submitted','reviewing','changes_requested'],true)): ?><form class="traffic-section" method="post" action="<?= PROJECT_URL ?>/admin/trafego/save.php" onsubmit="return confirm('Cancelar esta solicitacao?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><button class="c-btn-secondary" name="action" value="cancel">Cancelar solicitacao</button></form><?php endif; ?>
<?php if ($events): ?><section class="traffic-section"><h2>Historico</h2><ol class="traffic-events"><?php foreach ($events as $event): ?><li><small><?= trafficEscape($event['created_at']) ?> · <?= $event['actor_type']==='core'?'Content Studio':'Projeto' ?></small><p><?= trafficEscape($event['message']) ?></p></li><?php endforeach; ?></ol></section><?php endif; ?>
<?php endif; require __DIR__.'/style.php'; $content=ob_get_clean(); require APP_PATH.'/views/layout_admin.php'; ?>
