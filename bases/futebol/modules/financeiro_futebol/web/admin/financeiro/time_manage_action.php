<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectRole(['ADMIN','FINANCE']);
if(!financeTeamEnabled()){http_response_code(404);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
csrf_verify();financeEnsureEntryMetaSchema($pdo);
$actor=(int)projectUser()['id'];$section=(string)($_POST['section']??'');
try{
 if($section==='treasury')financeTeamTreasury($pdo,$_POST,$actor);
 elseif($section==='credit'){
  if(($_POST['action']??'')==='cancel_use')financeTeamCreditCancelUse($pdo,(int)($_POST['id']??0),(string)($_POST['reason']??''),$actor);
  else financeTeamCreditRecord($pdo,$_POST,$actor);
 }else throw new DomainException('Acao invalida.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code($e instanceof DomainException?422:500);exit($e instanceof DomainException?htmlspecialchars($e->getMessage()):'Operacao nao concluida. Reabra o formulario e confira o historico antes de repetir.');}
redirect(PROJECT_URL.'/admin/financeiro/'.($section==='treasury'?'time_caixas.php':'time_creditos.php'));
