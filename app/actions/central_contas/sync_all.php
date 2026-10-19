<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
$location='/web/admin/central_contas/unified.php';
try{
    $folder=$_POST['return_folder']??'all';
    if(!is_string($folder)||($folder!=='all'&&!isset(mailCenterUnifiedFolders()[$folder])))throw new InvalidArgumentException('Pasta unificada inválida.');
    $location.='?folder='.rawurlencode($folder);
    mailPilotLoadCode();
    $service=new MailSyncAllService($pdo,$owner,static function(int $account)use($pdo,$config,$owner):array{
        return mailPilotRuntime($pdo,$config,$owner,$account)['sync']->run($account);
    });
    $result=$service->run();
    if(!$result['total'])$message='Nenhuma caixa conectada para sincronizar.';
    else{
        $message=$result['updated'].' caixa(s) atualizada(s).';
        if($result['pending'])$message.=' Há mais mensagens para carregar; clique novamente para continuar.';
        if($result['failed'])$message.=' '.$result['failed'].' caixa(s) não puderam ser atualizadas. Confira as conexões ou aguarde antes de tentar novamente.';
    }
    flash($result['failed']?'error':'success',$message);
    header('Location: '.$location,true,303);exit;
}catch(Throwable $error){mailPilotError($error,$location);}
