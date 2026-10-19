<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();
$json=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');$location='/web/admin/central_contas/unified.php';
try{
    mailPilotLoadCode();if(!mailCenterOrganizationReady($pdo))throw new DomainException('Organização local aguardando preparação.');
    $message=mailCenterId($_POST['message_id']??null);$mark=$_POST['mark']??null;
    if(!is_string($mark)||!preg_match('/^(favorite|flagged|pinned):([01])$/D',$mark,$matches))throw new InvalidArgumentException('Marcação inválida.');
    $account=mailCenterId($_POST['return_account']??0,true);$filter=$_POST['return_mark']??'all';
    if(!is_string($filter))throw new InvalidArgumentException('Filtro inválido.');mailCenterMarkColumn($filter);
    $organization=new MailLocalOrganizationService($pdo,$owner);$before=$organization->marks($message);
    if($account&&$account!==(int)$before['account_id'])throw new OutOfBoundsException('Mensagem indisponível nesta caixa.');
    $folder=$_POST['return_folder']??'all';
    if($account){$folder=mailCenterId($folder,true);$location='/web/admin/central_contas/messages.php?account='.$account.'&folder='.$folder;}
    else{if(!is_string($folder)||($folder!=='all'&&!isset(mailCenterUnifiedFolders()[$folder])))throw new InvalidArgumentException('Pasta inválida.');$location.='?folder='.rawurlencode($folder);}
    $location.='&mark='.rawurlencode($filter);
    $marks=$organization->setMark($message,$matches[1],(int)$matches[2]);
    if($json){header('Content-Type: application/json; charset=utf-8');echo json_encode(['marks'=>$marks]);exit;}
    header('Location: '.$location,true,303);exit;
}catch(Throwable $e){
    if($json){http_response_code($e instanceof OutOfBoundsException?404:($e instanceof InvalidArgumentException||$e instanceof DomainException?422:500));header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>'Não foi possível salvar a marcação local.']);exit;}
    mailPilotError($e,$location);
}
