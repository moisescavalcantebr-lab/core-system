<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectRole(['ADMIN','FINANCE']);
if(!financeTeamEnabled()){http_response_code(404);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
csrf_verify();financeTeamEnsureSchema($pdo);
$id=(int)($_POST['id']??0);$reason=trim((string)($_POST['reason']??''));
if($id<=0||$reason===''||mb_strlen($reason)>500){http_response_code(422);exit('Informe o motivo do cancelamento, com ate 500 caracteres.');}
$linked=$pdo->prepare('SELECT charge_id FROM finance_team_charge_payments WHERE entry_id=?');$linked->execute([$id]);$chargeId=(int)$linked->fetchColumn();
$pdo->beginTransaction();
try{
    // Match the lock order used when receiving: charge first, then ledger entry.
    $chargeBefore=null;
    if($chargeId){$lock=$pdo->prepare('SELECT id FROM finance_team_charges WHERE id=? FOR UPDATE');$lock->execute([$chargeId]);$chargeBefore=financeTeamChargeSnapshot($pdo,$chargeId);}
    $stmt=$pdo->prepare('SELECT * FROM finance_entries WHERE id=? FOR UPDATE');$stmt->execute([$id]);$entry=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$entry||!financeTeamTracked($pdo,$id))throw new DomainException('Lancamento nao pertence ao historico do time.');
    if($entry['source']==='team_payment'&&!$chargeId)throw new DomainException('Reabra o recebimento antes de cancelar.');
    if(in_array($entry['source'],['balance_deposit','balance_usage','wallet_payment'],true))throw new DomainException('Credito antecipado exige conciliacao do saldo antes do cancelamento.');
    if($entry['status']==='canceled'){$pdo->rollBack();redirect(PROJECT_URL.'/admin/financeiro/time_elenco.php?entry_id='.$id);exit;}
    $actor=(int)projectUser()['id'];
    $pdo->prepare("UPDATE finance_entries SET status='canceled',updated_by_user_id=? WHERE id=?")->execute([$actor,$id]);
    financeTeamAudit($pdo,$id,'canceled',$actor,$entry,$reason);
    if($chargeBefore)financeTeamChargeAudit($pdo,$chargeId,'payment_canceled',$actor,$chargeBefore,$id,$reason);
    $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code($e instanceof DomainException?422:500);exit($e instanceof DomainException?$e->getMessage():'Nao foi possivel cancelar.');}
redirect(PROJECT_URL.'/admin/financeiro/time_elenco.php?entry_id='.$id);
