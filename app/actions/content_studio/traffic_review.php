<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';
require APP_PATH.'/helpers/auth.php';
requireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
csrf_verify();
$id=(int)($_POST['id']??0);
try {
    ContentStudioService::ensureSchema($pdo); TrafficRequestService::ensureSchema($pdo);
    $decision=TrafficRequestService::text($_POST,'decision',30);
    $actor=(int)($_SESSION['core_user']['id']??0); $revision=(int)($_POST['revision']??0);
    if ($decision==='operation_save') TrafficOperationsService::savePlan($pdo,$id,$actor,$revision,$_POST);
    elseif ($decision==='operation_transition') TrafficOperationsService::transition($pdo,$id,$actor,$revision,$_POST);
    elseif ($decision==='report_save') TrafficOperationsService::saveReport($pdo,$id,$actor,$revision,$_POST);
    else TrafficRequestService::review($pdo,$id,$actor,$revision,$decision,TrafficRequestService::text($_POST,'message',5000));
    flash('success',$decision==='reply'?'Resposta enviada.':($decision==='close_conversation'?'Conversa encerrada.':'Solicitacao atualizada.'));
} catch (Throwable $e) {
    error_log('Traffic review failed: '.$e->getMessage());
    flash('error', $e instanceof RuntimeException && !$e instanceof PDOException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Nao foi possivel atualizar a solicitacao.');
}
redirect('/web/admin/content_studio/requests.php?id='.$id);
