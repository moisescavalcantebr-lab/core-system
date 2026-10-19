<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$mailOwner=mailCenterRequireOwner($pdo,$config);$mailTab='unified';$title='Central de E-mail';$rows=[];$more=false;$page=1;$search='';$notice='';$unifiedFolder='all';$markFilter='all';
try {
    $markFilter=$_GET['mark']??'all';if(!is_string($markFilter)){$markFilter='all';throw new InvalidArgumentException('Filtro inválido.');}mailCenterMarkColumn($markFilter);
    $unifiedFolder=$_GET['folder']??'all';
    if(!is_string($unifiedFolder)||!in_array($unifiedFolder,['all','inbox','sent','drafts','spam','trash'],true)){$unifiedFolder='all';$markFilter='all';throw new InvalidArgumentException('Pasta unificada inválida.');}
    if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
        mailCenterRequirePost();if(!is_string($_POST['search']??null)||mb_strlen($_POST['search'])>200)throw new InvalidArgumentException('Busca inválida.');
        $_SESSION['mail_unified_search']=['query'=>trim($_POST['search']),'created'=>time()];header('Location: /web/admin/central_contas/unified.php?folder='.rawurlencode($unifiedFolder).'&mark='.rawurlencode($markFilter),true,303);exit;
    }
    $searchState=$_SESSION['mail_unified_search']??[];$search=($searchState['created']??0)>time()-600?(string)($searchState['query']??''):'';
    $page=isset($_GET['page'])?mailCenterId($_GET['page']):1;
    mailPilotLoadCode();$private=MailPilotConfig::load($config,ROOT_PATH);MailPilotConfig::requireHttps($config,$_SERVER);
    $store=new MailMessageService($pdo,$mailOwner,new MailTokenCipher($private['keys'],$private['active_key']));
    $result=$store->unifiedListing($search,$page,$unifiedFolder,$markFilter);$rows=$result['rows'];$more=$result['more'];
}catch(Throwable $e){$notice=$e instanceof DomainException||$e instanceof InvalidArgumentException?$e->getMessage():'A caixa unificada está indisponível. Confira as conexões.';}
ob_start();require __DIR__.'/_inbox.php';$content=ob_get_clean();require APP_PATH.'/views/layout_admin.php';
