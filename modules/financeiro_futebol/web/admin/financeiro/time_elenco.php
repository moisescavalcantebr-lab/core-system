<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectRole(['ADMIN','FINANCE']);
if(!financeTeamEnabled()){http_response_code(404);exit;}
financeEnsureEntryMetaSchema($pdo);
$playerId=max(0,(int)($_GET['player_id']??0));$entryId=max(0,(int)($_GET['entry_id']??0));
$players=financeTeamPlayers($pdo);$where='EXISTS(SELECT 1 FROM finance_team_history h WHERE h.entry_id=e.id)';$params=[];
if($playerId){$where.=" AND ((e.party_module='jogadores' AND e.party_id=?) OR EXISTS(SELECT 1 FROM finance_team_charge_payments cp JOIN finance_team_charges c ON c.id=cp.charge_id WHERE cp.entry_id=e.id AND c.beneficiary_player_id=?))";$params[]=$playerId;$params[]=$playerId;}
if($entryId){$where.=' AND e.id=?';$params[]=$entryId;}
$stmt=$pdo->prepare('SELECT e.*,u.name AS actor_name,(SELECT cp.charge_id FROM finance_team_charge_payments cp WHERE cp.entry_id=e.id) AS charge_id,(SELECT c.beneficiary_name FROM finance_team_charge_payments cp JOIN finance_team_charges c ON c.id=cp.charge_id WHERE cp.entry_id=e.id) AS beneficiary_name FROM finance_entries e LEFT JOIN project_users u ON u.id=e.created_by_user_id WHERE '.$where.' ORDER BY e.id DESC LIMIT 100');$stmt->execute($params);$entries=$stmt->fetchAll(PDO::FETCH_ASSOC);
$summaryStmt=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN e.status='paid' AND e.type='income' THEN e.amount ELSE 0 END),0) AS received,COALESCE(SUM(CASE WHEN e.status='paid' AND e.type='expense' THEN e.amount ELSE 0 END),0) AS paid,COALESCE(SUM(CASE WHEN e.status='pending' AND e.type='income' THEN e.amount ELSE 0 END),0) AS pending FROM finance_entries e WHERE $where");$summaryStmt->execute($params);$summary=$summaryStmt->fetch(PDO::FETCH_ASSOC);
$title='Financeiro do Time';ob_start();
if(!$entryId)$summary['pending']=(float)$summary['pending']+(float)financeTeamOutstanding($pdo,$playerId?:null);
?>
<div class="c-page">
 <div class="c-page-header"><h1 class="c-page-title">Financeiro do Time</h1><div class="c-page-actions"><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_caixas.php">Caixas e transferencias</a><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_creditos.php">Creditos dos jogadores</a><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_cobrancas.php">Cobrancas do elenco</a><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/create.php">Novo lancamento</a><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/lancamentos.php">Todos os lancamentos</a></div></div>
 <div class="c-page-content">
  <form method="get" class="c-card"><label for="teamPlayerFilter">Historico por jogador</label><select id="teamPlayerFilter" name="player_id" class="c-input"><option value="">Todas as identificacoes</option><?php foreach($players as$player): ?><option value="<?= (int)$player['id'] ?>" <?= $playerId===(int)$player['id']?'selected':'' ?>><?= htmlspecialchars($player['name']) ?><?= $player['status']!=='active'?' (inativo)':'' ?></option><?php endforeach; ?></select><button class="c-btn-secondary">Filtrar</button></form>
  <?php if($playerId): ?><p class="c-muted">Movimentacoes em que o jogador e pagador ou beneficiario. As cobrancas em aberto pertencem ao jogador selecionado.</p><?php endif; ?>
  <div class="c-card">Recebido: <strong><?= financeMoney((float)$summary['received']) ?></strong> · Pago: <strong><?= financeMoney((float)$summary['paid']) ?></strong> · A receber: <strong><?= financeMoney((float)$summary['pending']) ?></strong><p class="c-muted">Movimentacoes registradas com as novas regras. Registros anteriores continuam em Todos os lancamentos.</p></div>
  <?php if(!$entries): ?><div class="c-card">Nenhum lancamento encontrado.</div><?php endif; ?>
  <?php foreach($entries as$entry): ?>
   <article class="c-card">
    <h3><?= htmlspecialchars($entry['title']) ?></h3>
    <p><?= $entry['type']==='income'?'De':'Para' ?>: <strong><?= htmlspecialchars((string)$entry['party_name']) ?></strong> · <?= financeMoney((float)$entry['amount']) ?> · <?= financeStatusLabel($entry['status']) ?></p>
    <?php if($entry['charge_id']): ?><p>Quitacao da cobranca de <strong><?= htmlspecialchars($entry['beneficiary_name']) ?></strong> · <a href="<?= PROJECT_URL ?>/admin/financeiro/time_cobrancas.php?charge_id=<?= (int)$entry['charge_id'] ?>">Ver cobranca</a></p><?php endif; ?>
    <p>Previsto: <?= htmlspecialchars($entry['due_date']??'—') ?> · Confirmado: <?= htmlspecialchars($entry['paid_at']??'—') ?> · Registrado por: <?= htmlspecialchars($entry['actor_name']??('Responsavel #'.$entry['created_by_user_id'])) ?></p>
    <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/time_elenco.php?entry_id=<?= (int)$entry['id'] ?>">Historico</a>
    <?php if($entry['status']==='pending'): ?><a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/financeiro/edit.php?id=<?= (int)$entry['id'] ?>">Editar ou confirmar</a><?php endif; ?>
    <?php if($entryId): ?>
     <?php $history=$pdo->prepare('SELECT h.*,u.name AS actor_name FROM finance_team_history h LEFT JOIN project_users u ON u.id=h.actor_user_id WHERE h.entry_id=? ORDER BY h.id');$history->execute([$entry['id']]); ?>
     <ul><?php foreach($history->fetchAll(PDO::FETCH_ASSOC) as$event): $snapshot=json_decode($event['after_json'],true)?:[]; ?><li><?= htmlspecialchars($event['created_at']) ?> · <?= htmlspecialchars(['created'=>'Registrado','updated'=>'Atualizado','confirmed'=>'Confirmado','canceled'=>'Cancelado'][$event['action']]??$event['action']) ?> · <?= htmlspecialchars($event['actor_name']??('Responsavel #'.$event['actor_user_id'])) ?> · <?= financeMoney((float)($snapshot['amount']??0)) ?> · <?= htmlspecialchars((string)($snapshot['party_name']??'')) ?><?= $event['reason']?' · '.htmlspecialchars($event['reason']):'' ?></li><?php endforeach; ?></ul>
     <?php if($entry['status']!=='canceled'&&!in_array($entry['source'],['balance_deposit','balance_usage','wallet_payment'],true)): ?>
      <form method="post" action="<?= PROJECT_URL ?>/admin/financeiro/time_cancel.php" onsubmit="return confirm('Cancelar este lancamento e ajustar o saldo? O historico sera preservado.');">
       <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$entry['id'] ?>"><label>Motivo do cancelamento</label><input class="c-input" name="reason" required maxlength="500"><button class="c-btn-secondary">Cancelar com motivo</button>
      </form>
     <?php endif; ?>
    <?php endif; ?>
   </article>
  <?php endforeach; ?>
  <?php if(count($entries)===100): ?><p>Mostrando os 100 lancamentos mais recentes. Use o filtro por jogador.</p><?php endif; ?>
 </div>
</div>
<?php $content=ob_get_clean();require APP_PATH.'/views/layout_admin.php';
