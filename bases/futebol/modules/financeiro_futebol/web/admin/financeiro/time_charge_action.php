<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectRole(['ADMIN','FINANCE']);
if(!financeTeamEnabled()){http_response_code(404);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
csrf_verify();financeEnsureEntryMetaSchema($pdo);financeEnsureCategoryAddonSchema($pdo);
$actor=(int)projectUser()['id'];$action=(string)($_POST['action']??'');$id=(int)($_POST['charge_id']??0);
try{
    if($action==='create')$id=financeTeamChargeCreate($pdo,$_POST,$actor);
    elseif($action==='pay'){
        financeTeamChargeSnapshot($pdo,$id);
        financeTeamParty($pdo,$_POST);
        $receipt=financeReceiptUpload($_FILES['receipt']??[],'team_payment');
        financeTeamChargePay($pdo,$id,$_POST,$actor,$receipt);
    }elseif($action==='cancel')financeTeamChargeCancel($pdo,$id,(string)($_POST['reason']??''),$actor);
    else throw new DomainException('Acao invalida.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code($e instanceof DomainException?422:500);exit($e instanceof DomainException?htmlspecialchars($e->getMessage()):'Nao foi possivel concluir. Nenhum recebimento foi gravado parcialmente.');}
redirect(PROJECT_URL.'/admin/financeiro/time_cobrancas.php?charge_id='.$id);
