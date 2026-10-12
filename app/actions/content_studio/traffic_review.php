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
    TrafficRequestService::review($pdo,$id,(int)($_SESSION['core_user']['id']??0),(int)($_POST['revision']??0),
        TrafficRequestService::text($_POST,'decision',30),TrafficRequestService::text($_POST,'message',5000));
    flash('success','Solicitacao atualizada.');
} catch (Throwable $e) {
    error_log('Traffic review failed: '.$e->getMessage());
    flash('error', $e instanceof RuntimeException && !$e instanceof PDOException || $e instanceof InvalidArgumentException ? $e->getMessage() : 'Nao foi possivel atualizar a solicitacao.');
}
redirect('/web/admin/content_studio/requests.php?id='.$id);
