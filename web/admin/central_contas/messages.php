<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$mailOwner=mailCenterRequireOwner($pdo,$config);$mailTab='messages';$title='Central de E-mail';$service=new MailAccountService($pdo,$mailOwner);
$accounts=array_values(array_filter($service->accounts(),static fn($a)=>in_array($a['provider_code'],['gmail','zoho','outlook'],true)&&(int)$a['is_active']===1));$ready=mailPilotReady($pdo,$config);$rows=[];$folders=[];$drafts=[];$sent=[];$more=false;$account=0;$search='';$mode='local';$page=1;$folderId=0;$notice='';$markFilter='all';
try{
    $markFilter=$_GET['mark']??'all';if(!is_string($markFilter)){$markFilter='all';throw new InvalidArgumentException('Filtro inválido.');}mailCenterMarkColumn($markFilter);
    if($ready){
        $account=isset($_GET['account'])?mailCenterId($_GET['account']):(int)($accounts[0]['id']??0);$runtime=mailPilotRuntime($pdo,$config,$mailOwner,$account);
        if($account){
            $box=$runtime['store']->account($account);$runtime['store']->connection($account);$folders=$runtime['store']->folders($account);$drafts=$runtime['send']->drafts($account);$sent=$runtime['send']->sent($account);
            if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
                mailCenterRequirePost();if(!is_string($_POST['search']??'')||mb_strlen($_POST['search']??'')>200||!in_array($_POST['search_mode']??'',['local','provider'],true))throw new InvalidArgumentException('Busca inválida.');
                $_SESSION['mail_search'][$account]=['query'=>trim($_POST['search']),'mode'=>$_POST['search_mode'],'tokens'=>[1=>''],'created'=>time()];header('Location: /web/admin/central_contas/messages.php?account='.$account.'&mark='.rawurlencode($markFilter),true,303);exit;
            }
            if(($_GET['clear_search']??'')==='1')unset($_SESSION['mail_search'][$account]);
            $context=$_SESSION['mail_search'][$account]??[];if(($context['created']??0)<time()-600)$context=[];$search=$context['query']??'';$mode='local';$page=isset($_GET['page'])?mailCenterId($_GET['page']):1;
            $folderId=isset($_GET['folder'])?mailCenterId($_GET['folder'],true):0;$result=$runtime['store']->listing($account,$search,$page,$folderId,$markFilter);$rows=$result['rows'];$more=$result['more'];
            $s=$pdo->prepare('SELECT last_synced_at,failure_code FROM core_mail_sync_states WHERE account_id=? AND owner_user_id=?');$s->execute([$account,$mailOwner]);$sync=$s->fetch(PDO::FETCH_ASSOC)?:[];
        }
    }
}catch(Throwable $error){if($error instanceof OutOfBoundsException){http_response_code(404);exit('Conta indisponível.');}$notice=$error instanceof DomainException||$error instanceof InvalidArgumentException||$error instanceof MailProviderException?$error->getMessage():'Não foi possível carregar a caixa.';$rows=[];}
ob_start();require __DIR__.'/_inbox.php';$content=ob_get_clean();require APP_PATH.'/views/layout_admin.php';
