<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectRole(['ADMIN','FINANCE']);
if(!financeTeamEnabled()){http_response_code(404);exit;}
financeEnsureEntryMetaSchema($pdo);financeEnsureCategoryAddonSchema($pdo);
$playerId=max(0,(int)($_GET['player_id']??0));$chargeId=max(0,(int)($_GET['charge_id']??0));
$players=financeTeamPlayers($pdo);$where='1=1';$params=[];
if($playerId){$where.=' AND c.beneficiary_player_id=?';$params[]=$playerId;}
if($chargeId){$where.=' AND c.id=?';$params[]=$chargeId;}
$stmt=$pdo->prepare("SELECT c.*,COALESCE((SELECT SUM(e.amount) FROM finance_team_charge_payments p JOIN finance_entries e ON e.id=p.entry_id WHERE p.charge_id=c.id AND e.status='paid'),0) AS received_amount,(SELECT i.batch_id FROM finance_team_charge_batch_items i WHERE i.charge_id=c.id) AS batch_id FROM finance_team_charges c WHERE $where ORDER BY c.id DESC LIMIT 100");$stmt->execute($params);$charges=$stmt->fetchAll(PDO::FETCH_ASSOC);
if($chargeId&&!$charges){http_response_code(404);exit('Cobranca nao encontrada.');}
$categories=$pdo->query("SELECT id,name FROM finance_categories WHERE status='active' AND type IN ('income','both') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$title='Cobrancas do elenco';ob_start();
?>
<div class="c-page">
 <div class="c-page-header"><h1 class="c-page-title">Cobrancas do elenco</h1><div class="c-page-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_elenco.php">Caixa do time</a><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_grupo.php">Cobranca em grupo / rateio</a><?php if($chargeId): ?><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_cobrancas.php">Todas as cobrancas</a><?php endif; ?></div></div>
 <div class="c-page-content">
 <?php if(!$chargeId): ?>
  <details class="c-card" id="teamNewCharge"><summary>Nova cobranca individual</summary>
   <form method="post" action="<?= PROJECT_URL ?>/admin/financeiro/time_charge_action.php" class="team-charge-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(32)) ?>">
    <div class="c-form-grid">
     <div class="c-form-group"><label>Jogador cuja cobranca sera quitada</label><select name="beneficiary_player_id" class="c-input" required><option value="">Selecione</option><?php foreach($players as$player): ?><option value="<?= (int)$player['id'] ?>"><?= htmlspecialchars($player['name']) ?><?= $player['status']!=='active'?' (inativo)':'' ?></option><?php endforeach; ?></select></div>
     <div class="c-form-group"><label>Finalidade</label><input name="title" class="c-input" required maxlength="150"></div>
     <div class="c-form-group"><label>Valor total</label><input type="number" name="amount" class="c-input" required min="0.01" max="9999999999.99" step="0.01"></div>
     <div class="c-form-group"><label>Data prevista</label><input type="date" name="due_date" class="c-input" required></div>
     <div class="c-form-group"><label>Categoria (opcional)</label><select name="category_id" class="c-input"><option value="">Sem categoria</option><?php foreach($categories as$category): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?></select></div>
    </div>
    <button class="c-btn-secondary">Criar cobranca</button>
   </form>
  </details>
  <form method="get" class="c-card"><label for="chargePlayerFilter">Jogador da cobranca</label><select id="chargePlayerFilter" name="player_id" class="c-input"><option value="">Todos</option><?php foreach($players as$player): ?><option value="<?= (int)$player['id'] ?>" <?= $playerId===(int)$player['id']?'selected':'' ?>><?= htmlspecialchars($player['name']) ?></option><?php endforeach; ?></select><button class="c-btn-secondary">Filtrar</button></form>
 <?php endif; ?>
 <?php if(!$charges): ?><div class="c-card">Nenhuma cobranca encontrada.</div><?php endif; ?>
 <?php foreach($charges as$charge): $remaining=max(0,financeTeamCents($charge['total_amount'])-financeTeamCents($charge['received_amount']));$canceled=$charge['status']==='canceled';$label=$canceled?'Cancelada':($remaining===0?'Quitada':(financeTeamCents($charge['received_amount'])>0?'Parcial':'Em aberto')); ?>
  <article class="c-card">
   <h3><?= htmlspecialchars($charge['title']) ?></h3><p>Cobranca de <strong><?= htmlspecialchars($charge['beneficiary_name']) ?></strong> · <?= $label ?> · Previsto: <?= htmlspecialchars($charge['due_date']) ?></p>
   <?php if($charge['batch_id']): ?><p><a href="<?= PROJECT_URL ?>/admin/financeiro/time_grupo.php?batch_id=<?= (int)$charge['batch_id'] ?>">Grupo / rateio #<?= (int)$charge['batch_id'] ?></a></p><?php endif; ?>
   <div class="team-charge-values"><span>Total: <strong><?= financeMoney((float)$charge['total_amount']) ?></strong></span><span>Recebido: <strong><?= financeMoney((float)$charge['received_amount']) ?></strong></span><span><?= $canceled?'Saldo cancelado':'Restante' ?>: <strong><?= financeMoney($remaining/100) ?></strong></span></div>
   <?php if(!$chargeId): ?><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_cobrancas.php?charge_id=<?= (int)$charge['id'] ?>">Abrir cobranca</a><?php else: ?>
    <?php if(!$canceled&&$remaining>0): ?>
     <details open class="team-charge-receive"><summary>Registrar recebimento</summary>
      <form method="post" enctype="multipart/form-data" action="<?= PROJECT_URL ?>/admin/financeiro/time_charge_action.php" class="team-charge-form" id="teamChargePayment">
       <?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="charge_id" value="<?= (int)$charge['id'] ?>"><input type="hidden" name="request_key" value="<?= bin2hex(random_bytes(32)) ?>">
       <div class="c-form-grid">
        <div class="c-form-group"><label>Valor recebido agora</label><input type="number" name="amount" class="c-input" required min="0.01" max="<?= financeTeamDecimal($remaining) ?>" step="0.01"></div>
        <div class="c-form-group"><label>Data do recebimento</label><input type="date" name="paid_at" class="c-input" required></div>
        <div class="c-form-group"><label>Quem pagou?</label><select name="team_party_kind" class="c-input" id="chargePartyKind" required><option value="">Selecione</option><option value="player">Jogador cadastrado</option><option value="external">Pessoa ou empresa identificada</option></select></div>
        <div class="c-form-group" id="chargePlayerGroup" hidden><label>Jogador pagador</label><select class="c-input" name="team_player_id" id="chargePayer" disabled><option value="">Selecione</option><?php foreach($players as$player): ?><option value="<?= (int)$player['id'] ?>"><?= htmlspecialchars($player['name']) ?></option><?php endforeach; ?></select></div>
        <div class="c-form-group" id="chargeExternalGroup" hidden><label>Nome de quem pagou</label><input class="c-input" name="team_party_name" id="chargeExternalPayer" disabled maxlength="150"></div>
        <div class="c-form-group"><label>Forma de pagamento (opcional)</label><select name="payment_method" class="c-input"><option value="">Nao informado</option><?php foreach(financePaymentMethodOptions()as$key=>$method): ?><option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($method) ?></option><?php endforeach; ?></select></div>
        <div class="c-form-group"><label>Comprovante (opcional)</label><input name="receipt" type="file" class="c-input" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
       </div><button class="c-btn-secondary">Confirmar recebimento</button>
      </form>
     </details>
    <?php endif; ?>
    <?php $payments=$pdo->prepare('SELECT e.* FROM finance_team_charge_payments p JOIN finance_entries e ON e.id=p.entry_id WHERE p.charge_id=? ORDER BY p.id');$payments->execute([$charge['id']]); ?>
    <h4>Recebimentos</h4>
    <?php foreach($payments->fetchAll(PDO::FETCH_ASSOC)as$payment): ?><p><?= htmlspecialchars((string)$payment['paid_at']) ?> · <?= htmlspecialchars((string)$payment['party_name']) ?> · <?= financeMoney((float)$payment['amount']) ?> · <?= financeStatusLabel($payment['status']) ?> <a href="<?= PROJECT_URL ?>/admin/financeiro/time_elenco.php?entry_id=<?= (int)$payment['id'] ?>">Ver registro</a></p><?php endforeach; ?>
    <?php $history=$pdo->prepare('SELECT h.*,u.name AS actor_name FROM finance_team_charge_history h LEFT JOIN project_users u ON u.id=h.actor_user_id WHERE h.charge_id=? ORDER BY h.id');$history->execute([$charge['id']]); ?>
    <details><summary>Historico da cobranca</summary><ul><?php foreach($history->fetchAll(PDO::FETCH_ASSOC)as$event): $after=json_decode($event['after_json'],true)?:[]; ?><li><?= htmlspecialchars($event['created_at']) ?> · <?= htmlspecialchars(['created'=>'Cobranca criada','payment_recorded'=>'Recebimento registrado','payment_canceled'=>'Recebimento cancelado','canceled'=>'Cobranca cancelada'][$event['action']]??$event['action']) ?> · <?= htmlspecialchars($event['actor_name']??('Responsavel #'.$event['actor_user_id'])) ?> · Restante: <?= financeMoney((float)($after['remaining_amount']??0)) ?><?= $event['reason']?' · '.htmlspecialchars($event['reason']):'' ?></li><?php endforeach; ?></ul></details>
    <?php if(!$canceled&&financeTeamCents($charge['received_amount'])===0): ?><form method="post" action="<?= PROJECT_URL ?>/admin/financeiro/time_charge_action.php" class="team-charge-form" onsubmit="return confirm('Cancelar esta cobranca? O historico sera preservado.');"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="charge_id" value="<?= (int)$charge['id'] ?>"><label>Motivo do cancelamento</label><input name="reason" class="c-input" required maxlength="500"><button class="c-btn-secondary">Cancelar cobranca</button></form><?php endif; ?>
   <?php endif; ?>
  </article>
 <?php endforeach; ?>
 <?php if(count($charges)===100): ?><p>Mostrando as 100 cobrancas mais recentes. Use o filtro por jogador.</p><?php endif; ?>
 </div>
</div>
<style>.team-charge-form{display:grid;gap:12px;margin-top:14px}.team-charge-values{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin:12px 0}.team-charge-receive{margin:18px 0}.team-charge-form *{min-width:0;box-sizing:border-box}.team-charge-form input[type=file]{max-width:100%}</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{const kind=document.getElementById('chargePartyKind');if(!kind)return;const player=document.getElementById('chargePayer'),external=document.getElementById('chargeExternalPayer');const sync=()=>{const isPlayer=kind.value==='player',isExternal=kind.value==='external';document.getElementById('chargePlayerGroup').hidden=!isPlayer;document.getElementById('chargeExternalGroup').hidden=!isExternal;player.disabled=!isPlayer;player.required=isPlayer;external.disabled=!isExternal;external.required=isExternal;};kind.addEventListener('change',sync);sync();});
</script>
<?php $content=ob_get_clean();require APP_PATH.'/views/layout_admin.php';
