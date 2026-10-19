<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';$mailOwner=mailCenterRequireOwner($pdo,$config);
try{
    $readingFolder=isset($_GET['folder'])?mailCenterId($_GET['folder'],true):0;$readingMark=$_GET['mark']??'all';
    if(!is_string($readingMark))throw new InvalidArgumentException('Filtro inválido.');mailCenterMarkColumn($readingMark);
    $resourceAccount=mailCenterResourceAccount($pdo,$mailOwner,'message',mailCenterId($_GET['id']??null));$runtime=mailPilotRuntime($pdo,$config,$mailOwner,$resourceAccount);
    $message=$runtime['store']->message(mailCenterId($_GET['id']??null));$account=$runtime['store']->account((int)$message['account_id']);
    $remote=$runtime['api']->message($runtime['oauth']->token((int)$message['account_id']),$message['remote_id'],true);
    if(($remote['id']??'')!==$message['remote_id'])throw new MailProviderException('provider');
    $message=$runtime['store']->refreshHeaders((int)$message['id'],$remote);$parts=MailHtmlRenderer::parts($remote['payload']??[]);$attachments=$runtime['attachments']->remoteDescriptors($message,$parts['attachments']);
    $nextMessage=null;
    if(($_GET['preview']??'')!=='1'){
        $searchContext=$_SESSION['mail_search'][(int)$account['id']]??[];$readingSearch=($searchContext['created']??0)>time()-600?(string)($searchContext['query']??''):'';
        $nextMessage=$runtime['store']->nextMessage((int)$message['id'],$readingSearch,$readingFolder,$readingMark);
    }
}catch(Throwable $error){mailPilotError($error,'/web/admin/central_contas/messages.php');}
ob_start();require __DIR__.'/_message_content.php';$article=ob_get_clean();
if(($_GET['preview']??'')==='1'){header('Content-Type: text/html; charset=utf-8');echo $article;exit;}
$mailTab='messages';$markFilter=$readingMark;$title='Ler mensagem';ob_start(); ?>
<div class="c-page mail-center"><div class="c-page-header"><h1 class="c-page-title">Ler mensagem</h1><div class="mail-full-navigation"><a class="c-btn-secondary" href="messages.php?account=<?= (int)$account['id'] ?>&amp;folder=<?= $readingFolder ?>&amp;mark=<?= rawurlencode($readingMark) ?>">Voltar à caixa</a><?php if($nextMessage!==null): ?><a class="c-btn-secondary" href="message.php?id=<?= $nextMessage ?>&amp;folder=<?= $readingFolder ?>&amp;mark=<?= rawurlencode($readingMark) ?>">Próxima mensagem</a><?php else: ?><button class="c-btn-secondary" type="button" disabled title="Última mensagem desta seleção">Próxima mensagem</button><?php endif; ?></div></div><?= $article ?></div>
<?php $content=ob_get_clean();require APP_PATH.'/views/layout_admin.php'; ?>

