<?php
declare(strict_types=1);
// Included only inside the disposable, simulated-provider fixture loop.
if(PHP_SAPI!=='cli'||!isset($remoteService,$remoteStore,$remoteApi,$remoteHttp,$remoteId,$remoteAccount,$renderControls))exit(1);
$deleteWrites=count($remoteHttp->writes);
pilotExpect($remoteService->canDelete($remoteAccount)===($provider==='zoho'),'Permanent delete scope gate incorrect');
pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'Permanent deletion allowed outside Trash');
pilotExpect(count($remoteHttp->writes)===$deleteWrites,'Invalid permanent delete contacted mutation endpoint');
$remoteService->apply($remoteId,'trash',0,0,true);pilotExpect($remoteService->isInTrash($remoteId),'Trash membership not available to UI');
$deleteControls=$renderControls(true,'trash');
pilotExpect(!str_contains($deleteControls,'Enviar para lixeira')&&str_contains($deleteControls,'Excluir definitivamente'),'Trash UI retained send-to-trash button');
$previewBase=file_get_contents($root.'/_deploy/mail-pilot-preview.html');
if($provider==='gmail'){
    pilotExpect(!str_contains($deleteControls,'name="operation" value="delete"')&&str_contains($deleteControls,'disabled'),'Restricted Gmail UI offered permanent mutation');
    file_put_contents($root.'/_deploy/mail-remote-delete-blocked.html',str_replace('<p class="mail-address">',$deleteControls.'<p class="mail-address">',$previewBase));
    $deleteWrites=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'gmail.modify accepted permanent delete');pilotExpect(count($remoteHttp->writes)===$deleteWrites,'Insufficient Gmail scope still issued DELETE');
    $pdo->prepare('UPDATE core_mail_connections SET scopes=? WHERE account_id=?')->execute([json_encode(['https://mail.google.com/']),$remoteAccount]);
    pilotExpect($remoteService->available($remoteAccount)&&$remoteService->canDelete($remoteAccount),'Full Gmail scope lost organization capability');
}
$deleteControls=$renderControls(true,'trash');pilotExpect(str_contains($deleteControls,'data-mail-delete-confirm')&&str_contains($deleteControls,'Não poderá ser recuperada.'),'Permanent delete lacks irreversible confirmation');
file_put_contents($root.'/_deploy/mail-remote-delete-'.$provider.'.html',str_replace('<p class="mail-address">',$deleteControls.'<p class="mail-address">',$previewBase));
$deleteWrites=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'delete'),'Permanent delete accepted without confirmation');pilotExpect(count($remoteHttp->writes)===$deleteWrites,'Unconfirmed permanent delete issued write');
// Simulate a message restored externally after the UI had shown Trash.
if($provider==='gmail')$remoteHttp->labels=['INBOX','UNREAD'];else$remoteHttp->folder='10';
pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'Stale Trash membership accepted');pilotExpect(count($remoteHttp->writes)===$deleteWrites,'Stale Trash view deleted Inbox mail');
if($provider==='gmail')$remoteHttp->labels=['TRASH','UNREAD'];else$remoteHttp->folder='30';
$remoteService->apply($remoteId,'delete',0,0,true);
pilotExpect($remoteHttp->deleted&&(int)$remoteStore->message($remoteId)['remote_missing']===1,'Confirmed deletion not mirrored locally');
pilotExpect($remoteStore->listing($remoteAccount,'',1,$trashFolder)['rows']===[],'Permanently deleted mail remained listed');
$deleteWrites=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'Deleted message accepted twice');pilotExpect(count($remoteHttp->writes)===$deleteWrites,'Permanent deletion was repeated');
// A timeout after deletion leaves a technical unknown, never a blind second DELETE.
$remoteHttp->deleted=false;$remoteStore->remember($remoteAccount,$remoteApi->message('synthetic',$remoteHttp->id()));$remoteHttp->failure='after';
pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'Lost deletion acknowledgment became success');
pilotExpect($remoteHttp->deleted&&(int)$remoteStore->message($remoteId)['remote_missing']===0,'Unknown deletion was optimistically hidden');
$remoteHttp->failure=null;$deleteWrites=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'Unknown deletion blindly retried');pilotExpect(count($remoteHttp->writes)===$deleteWrites,'Unknown permanent deletion was repeated');
