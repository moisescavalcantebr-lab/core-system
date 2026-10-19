<?php
if(!isset($mailOwner,$message,$account,$runtime,$config)){http_response_code(404);exit;}
if(($config['mail_center']['remote_organization_enabled']??false)!==true)return;
$remoteOrganization=$runtime['organization']??null;
if(!$remoteOrganization||!$remoteOrganization->available((int)$account['id']))return;
$returnAccount=($_GET['source']??'')==='unified'?0:(int)$account['id'];
$returnFolder=$returnAccount?(string)($readingFolder??0):($_GET['list_folder']??'all');
if(!is_string($returnFolder))$returnFolder='all';
$remoteDestinations=$remoteOrganization->destinations((int)$account['id']);
$inTrash=$remoteOrganization->isInTrash((int)$message['id']);
$remoteContext=static function()use($message,$returnAccount,$returnFolder,$readingMark):void{ ?>
<?= csrf_field() ?><input type="hidden" name="message_id" value="<?= (int)$message['id'] ?>"><input type="hidden" name="return_account" value="<?= $returnAccount ?>"><input type="hidden" name="return_folder" value="<?= mailCenterEscape($returnFolder) ?>"><input type="hidden" name="return_mark" value="<?= mailCenterEscape($readingMark??'all') ?>">
<?php };
?>
<div class="mail-remote-actions" aria-label="Organizar mensagem">
<form method="post" action="/app/actions/central_contas/message_update.php" data-mail-remote-action><?php $remoteContext(); ?><button class="c-btn-secondary" name="operation" value="<?= (int)$message['is_unread']?'read':'unread' ?>"><?= (int)$message['is_unread']?'Marcar como lida':'Marcar como não lida' ?></button></form>
<?php if($remoteDestinations): ?><form method="post" action="/app/actions/central_contas/message_update.php" data-mail-remote-action><?php $remoteContext(); ?><input type="hidden" name="operation" value="move"><select name="destination_id" required aria-label="Pasta de destino"><option value="">Mover para…</option><?php foreach($remoteDestinations as$f): ?><option value="<?= (int)$f['id'] ?>"><?= mailCenterEscape(mailCenterFolderKind($account['provider_code'],$f['remote_id'],$f['name'])==='inbox'?'Caixa de entrada':$f['name']) ?></option><?php endforeach; ?></select><button class="c-btn-secondary">Mover</button></form><?php endif; ?>
<?php if(!$inTrash): ?>
<details class="mail-trash-menu"><summary class="c-btn-secondary">Lixeira</summary><form method="post" action="/app/actions/central_contas/message_update.php" data-mail-remote-action data-mail-trash-confirm><?php $remoteContext(); ?><input type="hidden" name="operation" value="trash"><label class="mail-confirm"><input type="checkbox" name="confirmed" value="1" required>Confirmo enviar para a lixeira</label><button class="c-btn-secondary">Enviar para lixeira</button></form></details>
<?php elseif($remoteOrganization->canDelete((int)$account['id'])): ?>
<details class="mail-trash-menu"><summary class="c-btn-secondary mail-delete-danger">Excluir definitivamente</summary><form method="post" action="/app/actions/central_contas/message_update.php" data-mail-remote-action data-mail-delete-confirm><?php $remoteContext(); ?><input type="hidden" name="operation" value="delete"><label class="mail-confirm"><input type="checkbox" name="confirmed" value="1" required>Confirmo excluir esta mensagem definitivamente. Não poderá ser recuperada.</label><button class="c-btn-secondary mail-delete-danger">Excluir definitivamente</button></form></details>
<?php else: ?><button class="c-btn-secondary" type="button" disabled title="O Gmail exige uma nova autorização para exclusão definitiva.">Excluir definitivamente</button><small class="mail-delete-permission">O Gmail exige autorização adicional para excluir definitivamente.</small><?php endif; ?>
</div>
