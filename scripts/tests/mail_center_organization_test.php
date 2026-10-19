<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($pdo,$store,$account,$cipher,$api,$checks))exit(1);
$orgStart=$checks;$org=new MailLocalOrganizationService($pdo,1);$orgRows=$store->listing($account)['rows'];$orgLatest=$orgRows[0];$orgOlder=$orgRows[1];
$orgToken=$store->connection($account)['token_envelope'];
pilotExpect($org->preferences()['highlight_color']==='#f59e0b','Destaque padrão inválido');
$org->setMark((int)$orgLatest['id'],'favorite',1);$org->setMark((int)$orgLatest['id'],'favorite',1);$org->setMark((int)$orgLatest['id'],'flagged',1);
pilotExpect((int)$org->marks((int)$orgLatest['id'])['is_favorite']===1&&(int)$org->marks((int)$orgLatest['id'])['is_flagged']===1,'Marcações independentes/idempotentes falharam');
pilotExpect(count($store->listing($account,'',1,0,'favorite')['rows'])===1&&count($store->listing($account,'',1,0,'flagged')['rows'])===1,'Filtros locais não selecionaram marcações');
$org->setMark((int)$orgOlder['id'],'pinned',1);pilotExpect((int)$store->listing($account)['rows'][0]['id']===(int)$orgOlder['id'],'Mensagem fixada não foi para o topo');
pilotExpect(count($store->listing($account,'',1,0,'pinned')['rows'])===1,'Filtro fixados inválido');
$org->setMark((int)$orgOlder['id'],'pinned',0);pilotExpect((int)$store->listing($account)['rows'][0]['id']===(int)$orgLatest['id'],'Desafixar não restaurou ordenação');$org->setMark((int)$orgOlder['id'],'pinned',1);
$store->remember($account,$api->message('synthetic',$orgLatest['remote_id']));pilotExpect((int)$org->marks((int)$orgLatest['id'])['is_favorite']===1&&(int)$org->marks((int)$orgLatest['id'])['is_flagged']===1,'Sincronização apagou organização local');
pilotReject(fn()=>$org->setMark((int)$orgLatest['id'],'evil_column',1),'Coluna livre aceita em marcação');pilotReject(fn()=>$org->setMark((int)$orgLatest['id'],'favorite',2),'Valor inválido aceito');pilotReject(fn()=>$store->listing($account,'',1,0,'evil'),'Filtro inválido aceito');
$orgOther=new MailLocalOrganizationService($pdo,2);pilotReject(fn()=>$orgOther->marks((int)$orgLatest['id']),'Marcação exposta a outro dono');pilotReject(fn()=>$orgOther->setMark((int)$orgLatest['id'],'favorite',0),'Outro dono alterou favorito');pilotReject(fn()=>$org->setMark(999999999,'favorite',1),'Mensagem inexistente marcada');
$pdo->prepare('UPDATE core_mail_accounts SET is_active=0 WHERE id=?')->execute([$account]);try{pilotReject(fn()=>$org->setMark((int)$orgLatest['id'],'favorite',0),'Caixa desativada aceitou alteração');}finally{$pdo->prepare('UPDATE core_mail_accounts SET is_active=1 WHERE id=?')->execute([$account]);}
$org->savePreferences('#ABCDEF');pilotExpect($org->preferences()['highlight_color']==='#abcdef','Preferência não persistiu');$orgOther->savePreferences('#123456');pilotExpect($org->preferences()['highlight_color']==='#abcdef','Cor de outro dono afetou proprietário');pilotReject(fn()=>$org->savePreferences('red; background:url(evil)'),'CSS livre aceito em preferência');
pilotExpect($store->connection($account)['token_envelope']===$orgToken,'Organização local alterou token');
try{$pdo->prepare('INSERT INTO core_mail_message_marks(message_id,account_id,owner_user_id) VALUES(?,?,2)')->execute([$orgLatest['id'],$other]);throw new RuntimeException('FK permitiu vínculo cruzado');}catch(PDOException $e){pilotExpect(true,'FK de marcação isolada');}
pilotExpect($org->preferences()['reading_mode']==='panel','Leitura padrão inválida');
$org->savePreferences('#abcdef','full');pilotExpect($org->preferences()['reading_mode']==='full','Modo completo não persistiu');
$org->savePreferences('#123456');pilotExpect($org->preferences()['reading_mode']==='full','Salvar cor apagou modo de leitura');
$orgOther->savePreferences('#123456','panel');pilotExpect($org->preferences()['reading_mode']==='full','Preferência de leitura atravessou dono');
pilotReject(fn()=>$org->savePreferences('#123456','evil'),'Modo de leitura inválido aceito');
pilotReject(fn()=>$org->savePreferences('#123456',[]),'Modo de leitura em array aceito');
pilotExpect($org->preferences()['highlight_color']==='#123456'&&$org->preferences()['reading_mode']==='full','Entrada inválida alterou preferências');
$org->savePreferences('#f59e0b','panel');
$oldIds=[];foreach(['favorite','flagged','pinned','none']as$field){$old=$api->message('synthetic','retention_'.$field);$old['internalDate']=(string)((time()-100*86400)*1000);$oldIds[]=$oldId=$store->remember($account,$old);if($field!=='none')$org->setMark($oldId,$field,1);}
$otherStoreOrg=new MailMessageService($pdo,2,$cipher);$foreignOld=$api->message('synthetic','foreign_old');$foreignOld['internalDate']=(string)((time()-100*86400)*1000);$foreignOldId=$otherStoreOrg->remember($other,$foreignOld);
pilotExpect($org->purgeOldUnmarkedMetadata()===1,'Retenção apagou mensagem importante ou preservou antiga sem marca');
foreach(array_slice($oldIds,0,3)as$oldId)pilotExpect((int)$org->marks($oldId)['id']===$oldId,'Retenção perdeu favorito/sinalização/fixação');
pilotExpect((int)$otherStoreOrg->message($foreignOldId)['id']===$foreignOldId,'Retenção atravessou proprietário');
foreach(array_slice($oldIds,0,3)as$oldId)$pdo->prepare('DELETE FROM core_mail_messages WHERE id=? AND owner_user_id=1')->execute([$oldId]);$pdo->prepare('DELETE FROM core_mail_messages WHERE id=? AND owner_user_id=2')->execute([$foreignOldId]);
$unicodeSubject='Olá, Moisés — confirmação 🚀';$unicodeFrom='Moisés Cavalcante <person@example.test>';
foreach([$unicodeSubject,'Você? Sim!','日本語 ✨']as$plain)pilotExpect(MailMessageService::decodeHeader($plain)===$plain,'Cabeçalho UTF-8 sofreu perda');
$encoded='=?UTF-8?B?'.base64_encode($unicodeSubject).'?=';
pilotExpect(MailMessageService::decodeHeader($encoded)===$unicodeSubject,'Cabeçalho MIME base64 não decodificou');
pilotExpect(MailMessageService::decodeHeader('=?ISO-8859-1?Q?Ol=E1?=')==='Olá','MIME latino não decodificou');
pilotExpect(MailMessageService::decodeHeader($encoded.' '.$unicodeFrom)===$unicodeSubject.' '.$unicodeFrom,'Cabeçalho misto perdeu UTF-8');
pilotExpect(MailMessageService::decodeHeader('=?UTF-8?B?'.base64_encode('Olá ').'?= =?UTF-8?B?'.base64_encode('Moisés').'?=')==='Olá Moisés','Palavras MIME adjacentes falharam');
$priorEncoding=mb_internal_encoding();try{mb_internal_encoding('ISO-8859-1');pilotExpect(MailMessageService::decodeHeader($encoded)===$unicodeSubject&&mb_internal_encoding()==='ISO-8859-1','Decodificador depende/alterou configuração global');}finally{mb_internal_encoding($priorEncoding);}
$unicodeRemote=$api->message('synthetic',$orgLatest['remote_id']);$unicodeRemote['payload']['headers'][0]['value']=$unicodeSubject;$unicodeRemote['payload']['headers'][1]['value']=$unicodeFrom;
$unicodeSaved=$store->refreshHeaders((int)$orgLatest['id'],$unicodeRemote);pilotExpect($unicodeSaved['subject']===$unicodeSubject&&$unicodeSaved['sender']===$unicodeFrom,'Atualização de cabeçalho perdeu caracteres');
pilotExpect((int)$org->marks((int)$orgLatest['id'])['is_favorite']===1&&(int)$org->marks((int)$orgLatest['id'])['is_flagged']===1,'Revisão de cabeçalho apagou marcações');
pilotReject(fn()=>$otherStoreOrg->refreshHeaders((int)$orgLatest['id'],$unicodeRemote),'Outro dono atualizou cabeçalhos');
$unicodeWrong=$unicodeRemote;$unicodeWrong['id']='wrong';pilotReject(fn()=>$store->refreshHeaders((int)$orgLatest['id'],$unicodeWrong),'Revisão aceitou mensagem remota diferente');
$store->refreshHeaders((int)$orgLatest['id'],$api->message('synthetic',$orgLatest['remote_id']));
$navRows=$store->listing($account)['rows'];
foreach($navRows as$navIndex=>$navRow)pilotExpect($store->nextMessage((int)$navRow['id'])===(isset($navRows[$navIndex+1])?(int)$navRows[$navIndex+1]['id']:null),'Próxima mensagem divergiu da ordem de fixação/data/id');
pilotExpect($store->nextMessage((int)$navRows[0]['id'],'busca-sem-resultado')===null,'Próxima ignorou busca');
pilotExpect($store->nextMessage((int)$navRows[0]['id'],'',99999999)===null,'Próxima ignorou pasta');
pilotExpect($store->nextMessage((int)$orgLatest['id'],'',0,'favorite')===null,'Próxima ignorou favorito');
pilotReject(fn()=>$otherStoreOrg->nextMessage((int)$orgLatest['id']),'Próxima atravessou proprietário');
pilotReject(fn()=>$store->nextMessage((int)$orgLatest['id'],'',0,'evil'),'Próxima aceitou filtro inválido');
echo 'Organização local: '.($checks-$orgStart).' verificações adicionais aprovadas.'.PHP_EOL;



