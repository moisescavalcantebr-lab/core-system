<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectRole(['ADMIN','FINANCE']);
if(!financeTeamEnabled()){http_response_code(404);exit;}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
if($_SERVER['REQUEST_METHOD']==='POST')csrf_verify();
$receivedSql=financeTeamChargeReceivedSql();financeEnsureEntryMetaSchema($pdo);financeEnsureCategoryAddonSchema($pdo);
$actor=(int)projectUser()['id'];$batchId=max(0,(int)($_GET['batch_id']??0));$plan=null;$batch=null;$items=[];$signature='';$requestKey='';
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(($_POST['action']??'')!=='preview')throw new DomainException('Acao invalida.');
        $batchId=0;$plan=financeTeamGroupPlan($pdo,$_POST);$requestKey=(string)($_POST['request_key']??'');
        if(empty($_SESSION['finance_team_group_secret']))$_SESSION['finance_team_group_secret']=bin2hex(random_bytes(32));
        $signature=financeTeamGroupSignature($plan,$requestKey,$actor,(string)$_SESSION['finance_team_group_secret']);
    }elseif($batchId){
        $stmt=$pdo->prepare('SELECT * FROM finance_team_charge_batches WHERE id=?');$stmt->execute([$batchId]);$batch=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$batch){http_response_code(404);exit('Grupo nao encontrado.');}
        $stmt=$pdo->prepare("SELECT c.*,{$receivedSql} AS received_amount FROM finance_team_charge_batch_items i JOIN finance_team_charges c ON c.id=i.charge_id WHERE i.batch_id=? ORDER BY i.player_id");$stmt->execute([$batchId]);$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}catch(DomainException $e){http_response_code(422);exit(htmlspecialchars($e->getMessage()));}
$players=financeTeamPlayers($pdo);
$categories=$pdo->query("SELECT id,name FROM finance_categories WHERE status='active' AND type IN ('income','both') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$recent=$pdo->query('SELECT id,title,total_amount,allocation_mode,created_at FROM finance_team_charge_batches ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
$title='Cobrancas em grupo';ob_start();
?>
<div class="c-page">
 <div class="c-page-header"><h1 class="c-page-title">Cobrancas em grupo e rateios</h1><div class="c-page-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_cobrancas.php">Cobrancas do elenco</a><?php if($batch||$plan): ?><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_grupo.php">Nova selecao</a><?php endif; ?></div></div>
 <div class="c-page-content">
 <?php if($plan): ?>
  <section class="c-card" id="teamGroupPreview">
   <h3>Confira antes de criar</h3><p><?= htmlspecialchars($plan['title']) ?> · Previsto: <?= htmlspecialchars($plan['due_date']) ?></p>
   <p><?= $plan['allocation_mode']==='per_player'?'Mesmo valor por jogador':'Divisao do valor total' ?> · <?= count($plan['players']) ?> jogadores</p>
   <div class="team-group-rows"><?php foreach($plan['players']as$player): ?><div class="team-group-row"><span><?= htmlspecialchars($player['name']) ?><?= $player['status']!=='active'?' (inativo)':'' ?></span><strong><?= financeMoney((float)$player['amount']) ?></strong></div><?php endforeach; ?></div>
   <p>Total em cobrancas: <strong><?= financeMoney((float)$plan['total_amount']) ?></strong></p>
   <?php if($plan['allocation_mode']==='split_total'): ?><p class="c-muted">Diferencas de um centavo sao distribuidas na ordem exibida para fechar o total.</p><?php endif; ?>
   <p class="c-muted">O caixa muda apenas quando um recebimento for confirmado.</p>
   <form method="post" action="<?= PROJECT_URL ?>/admin/financeiro/time_charge_action.php" id="teamGroupConfirm">
    <?= csrf_field() ?><input type="hidden" name="action" value="group_create"><input type="hidden" name="request_key" value="<?= htmlspecialchars($requestKey) ?>"><input type="hidden" name="preview_signature" value="<?= htmlspecialchars($signature) ?>">
    <input type="hidden" name="title" value="<?= htmlspecialchars($plan['title']) ?>"><input type="hidden" name="allocation_mode" value="<?= htmlspecialchars($plan['allocation_mode']) ?>"><input type="hidden" name="amount" value="<?= htmlspecialchars($plan['input_amount']) ?>"><input type="hidden" name="due_date" value="<?= htmlspecialchars($plan['due_date']) ?>"><input type="hidden" name="category_id" value="<?= $plan['category_id']?:'' ?>">
    <?php foreach($plan['players']as$player): ?><input type="hidden" name="player_ids[]" value="<?= (int)$player['player_id'] ?>"><?php endforeach; ?>
    <button class="c-btn-secondary">Confirmar <?= count($plan['players']) ?> cobrancas</button>
   </form>
  </section>
 <?php elseif($batch): $received=0;$remaining=0;$canceledAmount=0;foreach($items as$item){$paid=financeTeamCents($item['received_amount']);$received+=$paid;if($item['status']==='open')$remaining+=max(0,financeTeamCents($item['total_amount'])-$paid);else $canceledAmount+=financeTeamCents($item['total_amount']);} ?>
  <section class="c-card" id="teamGroupResult">
   <h3><?= htmlspecialchars($batch['title']) ?></h3><p><?= $batch['allocation_mode']==='per_player'?'Valor por jogador':'Rateio do total' ?> · <?= count($items) ?> cobrancas · Previsto: <?= htmlspecialchars($batch['due_date']) ?></p>
   <p>Total gerado: <strong><?= financeMoney((float)$batch['total_amount']) ?></strong> · Recebido: <strong><?= financeMoney($received/100) ?></strong> · Em aberto: <strong><?= financeMoney($remaining/100) ?></strong></p>
   <?php if($canceledAmount): ?><p>Cobrancas canceladas: <strong><?= financeMoney($canceledAmount/100) ?></strong></p><?php endif; ?>
   <div class="team-group-rows"><?php foreach($items as$item): ?><div class="team-group-item"><div class="team-group-row"><span><?= htmlspecialchars($item['beneficiary_name']) ?></span><strong><?= financeMoney((float)$item['total_amount']) ?></strong></div><p><?= $item['status']==='canceled'?'Cancelada':'Recebido: '.financeMoney((float)$item['received_amount']) ?> · <a href="<?= PROJECT_URL ?>/admin/financeiro/time_cobrancas.php?charge_id=<?= (int)$item['id'] ?>">Abrir cobranca</a></p></div><?php endforeach; ?></div>
  </section>
 <?php else: ?>
  <form class="c-card team-group-form" method="post" id="teamGroupForm">
   <?= csrf_field() ?><input type="hidden" name="action" value="preview"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(32)) ?>">
   <div class="c-form-grid">
    <div class="c-form-group"><label for="teamGroupTitle">Finalidade</label><input id="teamGroupTitle" class="c-input" name="title" required maxlength="150"></div>
    <div class="c-form-group"><label for="teamGroupMode">Como calcular</label><select id="teamGroupMode" class="c-input" name="allocation_mode" required><option value="">Selecione</option><option value="per_player">Mesmo valor por jogador</option><option value="split_total">Dividir um valor total</option></select></div>
    <div class="c-form-group"><label for="teamGroupAmount" id="teamGroupAmountLabel">Valor</label><input id="teamGroupAmount" class="c-input" type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" required></div>
    <div class="c-form-group"><label for="teamGroupDue">Data prevista</label><input id="teamGroupDue" class="c-input" type="date" name="due_date" required></div>
    <div class="c-form-group"><label for="teamGroupCategory">Categoria (opcional)</label><select id="teamGroupCategory" class="c-input" name="category_id"><option value="">Sem categoria</option><?php foreach($categories as$category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?></select></div>
   </div>
   <fieldset class="team-group-players"><legend>Jogadores que receberao a cobranca</legend><p class="c-muted">Selecione de 1 a 30 jogadores.</p><div class="c-page-actions"><button type="button" class="c-btn-secondary" id="teamGroupActive">Selecionar ativos</button><button type="button" class="c-btn-secondary" id="teamGroupClear">Limpar selecao</button></div><p id="teamGroupCount" role="status">0 selecionados</p>
    <div class="team-group-checks"><?php foreach($players as$player): ?><label><input type="checkbox" name="player_ids[]" value="<?= (int)$player['id'] ?>" data-active="<?= $player['status']==='active'?'1':'0' ?>"><span><?= htmlspecialchars($player['name']) ?><?= $player['status']!=='active'?' (inativo)':'' ?></span></label><?php endforeach; ?></div>
   </fieldset>
   <button class="c-btn-secondary" id="teamGroupPreviewButton" disabled>Conferir distribuicao</button>
  </form>
  <?php if($recent): ?><section class="c-card"><h3>Grupos recentes</h3><?php foreach($recent as$group): ?><p><a href="<?= PROJECT_URL ?>/admin/financeiro/time_grupo.php?batch_id=<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['title']) ?></a> · <?= financeMoney((float)$group['total_amount']) ?></p><?php endforeach; ?></section><?php endif; ?>
 <?php endif; ?>
 </div>
</div>
<style>.team-group-form{display:grid;gap:14px}.team-group-form *{box-sizing:border-box;min-width:0}.team-group-players{border:1px solid var(--border-color);padding:12px}.team-group-checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}.team-group-checks label{display:flex;align-items:center;gap:8px;overflow-wrap:anywhere}.team-group-rows{margin:14px 0}.team-group-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color)}.team-group-row>span{overflow-wrap:anywhere}.team-group-item{padding-bottom:8px}.team-group-item p{margin:8px 0}</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{const form=document.getElementById('teamGroupForm');if(!form)return;const boxes=[...form.querySelectorAll('[name="player_ids[]"]')],mode=document.getElementById('teamGroupMode');const sync=()=>{const count=boxes.filter(box=>box.checked).length;document.getElementById('teamGroupCount').textContent=count+' selecionados';document.getElementById('teamGroupPreviewButton').disabled=count===0||count>30;document.getElementById('teamGroupAmountLabel').textContent=mode.value==='per_player'?'Valor por jogador':(mode.value==='split_total'?'Valor total a dividir':'Valor');};boxes.forEach(box=>box.addEventListener('change',sync));mode.addEventListener('change',sync);document.getElementById('teamGroupActive').addEventListener('click',()=>{boxes.forEach(box=>box.checked=box.dataset.active==='1');sync();});document.getElementById('teamGroupClear').addEventListener('click',()=>{boxes.forEach(box=>box.checked=false);sync();});sync();});
</script>
<?php $content=ob_get_clean();require APP_PATH.'/views/layout_admin.php';
