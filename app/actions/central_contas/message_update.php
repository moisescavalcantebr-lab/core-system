<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
if(($config['mail_center']['remote_organization_enabled']??false)!==true){http_response_code(403);exit('Organização remota ainda não habilitada.');}
$location='/web/admin/central_contas/unified.php';
$json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
try{
    $id=mailCenterId($_POST['message_id']??null);$account=mailCenterResourceAccount($pdo,$owner,'message',$id);
    $returnAccount=mailCenterId($_POST['return_account']??0,true);if($returnAccount&&$returnAccount!==$account)throw new OutOfBoundsException('Mensagem indisponível nesta conta.');
    $folder=$_POST['return_folder']??($returnAccount?'0':'all');$mark=$_POST['return_mark']??'all';
    if(!is_string($mark))throw new InvalidArgumentException('Filtro inválido.');mailCenterMarkColumn($mark);
    if($returnAccount){$folder=mailCenterId($folder,true);$location='/web/admin/central_contas/messages.php?account='.$account.'&folder='.$folder;}
    else{if(!is_string($folder)||($folder!=='all'&&!isset(mailCenterUnifiedFolders()[$folder])))throw new InvalidArgumentException('Pasta inválida.');$location.='?folder='.rawurlencode($folder);}
    $location.='&mark='.rawurlencode($mark);
    $action=$_POST['operation']??null;if(!is_string($action))throw new InvalidArgumentException('Ação inválida.');
    if(in_array($action,['read','unread'],true))$location='/web/admin/central_contas/message.php?id='.$id.'&source='.($returnAccount?'account':'unified').'&'.($returnAccount?'folder=':'list_folder=').rawurlencode((string)$folder).'&mark='.rawurlencode($mark);
    $runtime=mailPilotRuntime($pdo,$config,$owner,$account);
    $runtime['organization']->apply($id,$action,mailCenterId($_POST['destination_id']??0,true),$action==='move'&&$returnAccount?(int)$folder:0,($_POST['confirmed']??'')==='1');
    if($json&&in_array($action,['read','unread'],true)){
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
        echo json_encode(['id'=>$id,'is_unread'=>$action==='unread'?1:0],JSON_THROW_ON_ERROR);exit;
    }
    flash('success','Mensagem atualizada no provedor.');header('Location: '.$location,true,303);exit;
}catch(Throwable $e){
    if($json){http_response_code($e instanceof OutOfBoundsException?404:422);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['error'=>'Não foi possível confirmar a alteração. Atualize a página antes de tentar novamente.']);exit;}
    mailPilotError($e,$location);
}
