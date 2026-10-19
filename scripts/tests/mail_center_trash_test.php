<?php
declare(strict_types=1);
// Synthetic fixtures only, inside the pilot suite's disposable database.
if(PHP_SAPI!=='cli'||!isset($database,$pdo,$cipher,$api,$accounts,$providers))exit(1);
$trashStart=$checks;$trashStores=[];$trashIds=[];$trashFolderIds=[];
foreach(['gmail','zoho']as$trashProvider){
    $trashAccount=$accounts->save(['provider_id'=>$providers[$trashProvider],'email'=>'trash-fixture@'.($trashProvider==='gmail'?'gmail.com':'example.test'),'label'=>'Trash fixture']);
    $pdo->prepare("UPDATE core_mail_accounts SET connection_status='connected' WHERE id=? AND owner_user_id=1")->execute([$trashAccount]);
    $pdo->prepare('INSERT INTO core_mail_connections(owner_user_id,account_id,remote_identity,scopes,token_envelope) VALUES(1,?,?,?,?)')->execute([$trashAccount,'fixture@example.test','synthetic','synthetic-not-a-token']);
    $trashStore=new MailMessageService($pdo,1,$cipher,$trashAccount);$trashStores[]=$trashStore;
    $trashLabels=$trashProvider==='gmail'?['INBOX'=>'Inbox','Label_fixture'=>'Projects','TRASH'=>'Trash']:['9001'=>'Inbox','9002'=>'Projects','9003'=>'Trash'];
    $trashLocal=[];foreach($trashLabels as$remote=>$name){$pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(1,?,?,?)')->execute([$trashAccount,$remote,$name]);$trashLocal[]=(int)$pdo->lastInsertId();}
    $trashRemote=array_map('strval',array_keys($trashLabels));$trashMessageIds=[];
    foreach(['normal','trashed']as$i=>$state){$fixture=$api->message('synthetic','trash_fixture_'.$state);$fixture['payload']['headers'][0]['value']='trash-fixture-search';$fixture['labelIds']=array_slice($trashRemote,0,$i===0?2:3);$fixture['internalDate']=(string)((time()-$i*60)*1000);$trashMessageIds[]=$trashStore->remember($trashAccount,$fixture);}
    $trashIds[]=$trashMessageIds[1];$trashFolderIds[]=$trashLocal[2];
    $trashMarks=new MailLocalOrganizationService($pdo,1);foreach(['favorite','flagged','pinned']as$mark)$trashMarks->setMark($trashMessageIds[1],$mark,1);
    foreach([0,$trashLocal[0],$trashLocal[1]]as$folder)pilotExpect(array_column($trashStore->listing($trashAccount,'trash-fixture-search',1,$folder)['rows'],'id')==[$trashMessageIds[0]],'Lixeira apareceu em lista, busca ou pasta comum');
    foreach(['favorite','flagged','pinned']as$mark)pilotExpect($trashStore->listing($trashAccount,'',1,0,$mark)['rows']===[],'Marcação fez lixeira reaparecer');
    pilotExpect(array_column($trashStore->listing($trashAccount,'',1,$trashLocal[2])['rows'],'id')==[$trashMessageIds[1]],'Pasta Lixeira ficou inacessível');
    pilotExpect($trashStore->nextMessage($trashMessageIds[0])===null,'Navegação incluiu mensagem da lixeira');
    pilotExpect((int)$trashStore->message($trashMessageIds[1])['id']===$trashMessageIds[1],'Filtro apagou metadados');
    pilotReject(fn()=>(new MailMessageService($pdo,2,$cipher))->message($trashMessageIds[1]),'Lixeira atravessou proprietário');
}
$trashUnified=new MailMessageService($pdo,1,$cipher);
pilotExpect(count($trashUnified->unifiedListing('trash-fixture-search')['rows'])===2,'Unificada não excluiu lixeira das duas contas');
pilotExpect(count($trashUnified->unifiedListing('trash-fixture-search',1,'inbox')['rows'])===2,'Entrada unificada incluiu mensagem também marcada como lixeira');
pilotExpect(count($trashUnified->unifiedListing('trash-fixture-search',1,'trash')['rows'])===2,'Lixeira unificada perdeu mensagens');
foreach(['favorite','flagged','pinned']as$mark)pilotExpect($trashUnified->unifiedListing('trash-fixture-search',1,'all',$mark)['rows']===[],'Filtro unificado expôs lixeira');
pilotReject(fn()=>$trashStores[0]->listing($trashAccount),'Contexto de conta atravessado');
echo 'Filtro de lixeira: '.($checks-$trashStart).' verificações adicionais aprovadas.'.PHP_EOL;
