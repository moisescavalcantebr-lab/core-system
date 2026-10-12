<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
requireProjectAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
csrf_verify();
$id=(int)($_POST['id']??0);
try {
    [$core,$projectId]=trafficContext(); $user=projectUser();
    $action=TrafficRequestService::text($_POST,'action',20);
    if (!in_array($action,['save','submit','cancel'],true)) throw new InvalidArgumentException('Acao invalida.');
    if ($action==='cancel') {
        TrafficRequestService::cancel($core,$id,$projectId,(int)$user['id'],(int)($_POST['revision']??0));
    } else {
        $page=null;
        if (($_POST['destination_type']??'')==='page') {
            foreach (trafficPages($pdo) as $item) if ((int)$item['id']===(int)($_POST['project_page_id']??0)) { $page=$item; $page['public_url']=trafficPageUrl($item); break; }
        }
        $data=TrafficRequestService::payload($_POST,$user,$page);
        $id=TrafficRequestService::save($core,$projectId,(int)$user['id'],$id,(int)($_POST['revision']??0),$data,$action==='submit',($_POST['authorized']??'')==='1');
    }
    flash('success',$action==='submit'?'Solicitacao enviada.':($action==='cancel'?'Solicitacao cancelada.':'Briefing salvo.'));
} catch (Throwable $e) {
    error_log('Traffic request save failed: '.$e->getMessage());
    flash('error', $e instanceof RuntimeException && !$e instanceof PDOException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Nao foi possivel salvar. Tente novamente.');
    $_SESSION['traffic_form_input'] = $_POST;
}
redirect(PROJECT_URL.'/admin/trafego/request.php'.($id?'?id='.$id:''));
