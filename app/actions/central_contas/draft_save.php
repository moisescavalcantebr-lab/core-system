<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';require_once APP_PATH.'/helpers/mail_pilot.php';
$owner=mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();$id=0;
try{
    $account=mailCenterId($_POST['account_id']??null);$runtime=mailPilotRuntime($pdo,$config,$owner,$account);$data=$_POST;unset($data['_csrf']);$id=$runtime['send']->saveDraft($data);
    if(isset($_FILES['attachments'])){
        $files=$_FILES['attachments'];if(!is_array($files['error']??null)||count($files['error'])>3)throw new InvalidArgumentException('Anexe até três arquivos por vez.');
        foreach($files['error']as$i=>$error){if($error===UPLOAD_ERR_NO_FILE)continue;$runtime['attachments']->upload($id,['error'=>$error,'name'=>$files['name'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'']);}
    }
    flash('success','Rascunho salvo. Confira remetente, destinatários e anexos antes de confirmar o envio.');header('Location: /web/admin/central_contas/compose.php?draft='.$id,true,303);exit;
}catch(Throwable $error){mailPilotError($error,$id?'/web/admin/central_contas/compose.php?draft='.$id:'/web/admin/central_contas/accounts.php');}
