<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($database,$pdo,$cipher,$accounts,$providers))exit(1);

final class TrashReconcileHttp extends MailHttpClient
{
    public array $probes=[];
    public array $locations=[];
    public ?string $failure=null;
    public bool $mismatch=false;
    public array $listed=[];
    public ?Closure $afterProbe=null;
    public array $folders=[['folderId'=>'10','folderName'=>'Inbox','folderType'=>'Inbox'],['folderId'=>'20','folderName'=>'Archive','folderType'=>'Custom'],['folderId'=>'30','folderName'=>'Projects','folderType'=>'Custom'],['folderId'=>'40','folderName'=>'Trash','folderType'=>'Trash']];
    public function request(string $method,string $url,array $headers=[],?string $body=null,bool $sending=false):array
    {
        if($method!=='GET'||$body!==null||$sending)throw new RuntimeException('Reconciliation attempted a write');
        $path=parse_url($url,PHP_URL_PATH);
        if($path==='/api/accounts')$data=[['type'=>'ZOHO_ACCOUNT','enabled'=>true,'accountId'=>'99','primaryEmailAddress'=>'reconcile@example.test']];
        elseif(str_ends_with($path,'/folders'))$data=$this->folders;
        elseif(str_ends_with($path,'/messages/view'))$data=$this->listed;
        elseif(preg_match('~/folders/(\d+)/messages/(\d+)/details$~',$path,$match)){
            $this->probes[]=[$match[1],$match[2]];
            if($this->afterProbe)($this->afterProbe)();
            if($this->failure)throw new MailProviderException($this->failure);
            if(($this->locations[$match[2]]??'')!==$match[1])return ['status'=>['code'=>404],'data'=>[]];
            $data=$this->row($match[2],$this->mismatch?'999':$match[1]);
        }else throw new RuntimeException('Unexpected reconciliation endpoint');
        return ['status'=>['code'=>200],'data'=>$data];
    }
    public function row(string $id,string $folder):array
    {return ['messageId'=>$id,'folderId'=>$folder,'receivedTime'=>(string)((time()-90*86400)*1000),'status'=>'1','subject'=>'Synthetic reconciliation','fromAddress'=>'sender@example.test','toAddress'=>'reconcile@example.test'];}
}

$reconcileStart=$checks;$pdo->exec('UPDATE core_mail_audit_events SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
$reconcileAccount=$accounts->save(['provider_id'=>$providers['zoho'],'email'=>'reconcile@example.test','label'=>'Reconciliation fixture']);
$reconcileStore=new MailMessageService($pdo,1,$cipher,$reconcileAccount);
$reconcileHttp=new TrashReconcileHttp();$reconcileApi=new ZohoMailProvider($reconcileHttp,'us','reconcile@example.test',static fn(string $id):string=>'40');
$reconcileOauth=new RemoteFixtureOAuth($reconcileStore,$reconcileApi,[],new PilotHttp());
$pdo->prepare('INSERT INTO core_mail_connections(owner_user_id,account_id,remote_identity,scopes,token_envelope) VALUES(1,?,?,?,?)')->execute([$reconcileAccount,'reconcile@example.test','[]','synthetic']);
$inventory=$reconcileApi->folders('synthetic');
foreach($inventory['labels']as$f)$pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(1,?,?,?)')->execute([$reconcileAccount,$f['id'],$f['name']]);
$reconcile=new MailTrashReconciler($reconcileStore,$reconcileApi);
$seed=static function(string $id)use($reconcileStore,$reconcileAccount):int{return $reconcileStore->remember($reconcileAccount,['id'=>$id,'internalDate'=>(string)((time()-90*86400)*1000),'labelIds'=>['40','UNREAD'],'payload'=>['headers'=>[['name'=>'Subject','value'=>'Synthetic reconciliation']]]]);};
$run=static fn(array $state=[]):array=>$reconcile->run($reconcileAccount,'synthetic',$inventory,$state,1,microtime(true)+18);
$missing=static function(string $remote)use($pdo,$reconcileAccount):int{$s=$pdo->prepare('SELECT remote_missing FROM core_mail_messages WHERE owner_user_id=1 AND account_id=? AND remote_id=?');$s->execute([$reconcileAccount,$remote]);return (int)$s->fetchColumn();};
$first=$seed('100');$state=$run();
pilotExpect(count($reconcileHttp->probes)===3&&$missing('100')===0&&$state['next']===3,'Partial inventory hid a message or exceeded budget');
$state=$run($state);pilotExpect($missing('100')===1&&count($reconcileHttp->probes)===4,'Missing Trash message not reconciled after all folders');
pilotExpect((int)$reconcileStore->message($first)['id']===$first,'Reconciliation deleted cached row');
// Old restored/archived messages must be reattached even outside the polling window.
foreach(['10','20','30','40']as$i=>$folder){
    $id=(string)(101+$i);$local=$seed($id);$reconcileHttp->locations[$id]=$folder;$reconcileHttp->probes=[];
    $state=$run($state);if(isset($state['next']))$state=$run($state);
    $s=$pdo->prepare('SELECT f.remote_id FROM core_mail_message_folders mf JOIN core_mail_folders f ON f.id=mf.folder_id WHERE mf.message_id=?');$s->execute([$local]);
    pilotExpect($s->fetchColumn()===$folder&&$missing($id)===0,'Restored/archived/Trash metadata lost');
    pilotExpect((int)$reconcileStore->message($local)['is_unread']===0,'Fresh read state not retained');
}
// Isolate subsequent scenarios from the one message deliberately remaining in Trash.
$reconcileStore->missing($reconcileAccount,'104');
$seed('200');
foreach(['network','quota','auth','provider']as$failure){
    $reconcileHttp->failure=$failure;pilotReject(fn()=>$run(),'Provider failure treated as missing');
    pilotExpect($missing('200')===0,'Provider failure hid cached message');
}
$reconcileHttp->failure=null;$reconcileHttp->locations['200']='40';$reconcileHttp->mismatch=true;
pilotReject(fn()=>$run(),'Mismatched metadata accepted');pilotExpect($missing('200')===0,'Malformed response hid message');$reconcileHttp->mismatch=false;
// No probes after deadline, and a new folder inventory restarts partial evidence.
$reconcileHttp->probes=[];$expired=$reconcile->run($reconcileAccount,'synthetic',$inventory,[],1,microtime(true)-1);
pilotExpect($reconcileHttp->probes===[]&&$missing('200')===0,'Deadline not respected');
unset($reconcileHttp->locations['200']);$partial=$run();
$expanded=$inventory;$expanded['labels'][]=['id'=>'50','name'=>'New','type'=>'Custom'];$reconcileHttp->probes=[];
$reconcile->run($reconcileAccount,'synthetic',$expanded,$partial,1,microtime(true)+18);
pilotExpect($reconcileHttp->probes[0][0]==='40'&&$missing('200')===0,'Folder change reused partial missing evidence');
$ambiguous=$inventory;$ambiguous['labels'][]=['id'=>'60','name'=>'Other Trash','type'=>'Trash'];$reconcileHttp->probes=[];
pilotExpect($reconcile->run($reconcileAccount,'synthetic',$ambiguous,[],1,microtime(true)+18)===[]&&$reconcileHttp->probes===[],'Ambiguous Trash inventory probed or changed cache');
// A concurrent reconnect prevents cache changes.
$reconcileHttp->locations['200']='10';
$reconcileHttp->afterProbe=static function()use($pdo,$reconcileAccount):void{$pdo->prepare('UPDATE core_mail_connections SET generation=2 WHERE account_id=?')->execute([$reconcileAccount]);};
pilotReject(fn()=>$run(),'Concurrent reconnect accepted stale reconciliation');pilotExpect($missing('200')===0,'Reconnect hid message');
$reconcileHttp->afterProbe=null;$pdo->prepare('UPDATE core_mail_connections SET generation=1 WHERE account_id=?')->execute([$reconcileAccount]);
// Integration uses the normal encrypted sync cursor and existing lease/cooldown.
$reconcileHttp->probes=[];$reconcileHttp->locations['200']='10';
$result=(new MailSyncService($reconcileStore,$reconcileOauth,$reconcileApi))->run($reconcileAccount);
pilotExpect($result['complete']&&$result['processed']===0&&$missing('200')===0,'Reconciliation interfered with empty polling batch');
pilotExpect(count($reconcileHttp->probes)===2,'Sync did not use bounded reconciliation');
$s=$pdo->prepare('SELECT cursor_envelope,lease_token FROM core_mail_sync_states WHERE account_id=?');$s->execute([$reconcileAccount]);$saved=$s->fetch(PDO::FETCH_ASSOC);$cursor=$cipher->open($saved['cursor_envelope'],'mail:1:'.$reconcileAccount.':sync');
pilotExpect(isset($cursor['trash_reconcile'])&&$saved['lease_token']===null,'Reconciliation cursor not sealed or lease not released');
pilotReject(fn()=>(new MailMessageService($pdo,2,$cipher))->account($reconcileAccount),'Other owner accessed reconciliation account');
// A stale polling page must not resurrect a message verified absent afterward.
$reconcileHttp->listed=[$reconcileHttp->row('300','40')];$reconcileApi->listMessages('synthetic');
try{$reconcileApi->messageInFolder('synthetic','300','40');throw new RuntimeException('Missing fixture returned metadata');}catch(MailProviderException $e){pilotExpect($e->kind==='missing','Missing detail response not classified');}
pilotReject(fn()=>$reconcileApi->message('synthetic','300'),'Stale polling row survived fresh missing probe');
echo 'Reconciliação de Lixeira: '.($checks-$reconcileStart).' verificações sintéticas adicionais aprovadas.'.PHP_EOL;
