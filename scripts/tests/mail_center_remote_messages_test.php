<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($database,$pdo,$cipher,$accounts,$providers,$api))exit(1);

final class RemoteFixtureOAuth extends MailOAuthService
{public function token(int $account):string{return 'synthetic-organization-token';}}

final class RemoteFixtureHttp extends MailHttpClient
{
    public array $calls=[];public array $writes=[];public array $labels=[];public bool $unread=true;public string $folder='10';public ?string $failure=null;public bool $missingDestination=false;public ?Closure $afterWrite=null;public bool $refreshArmed=false;
    public function __construct(public string $provider) {$this->labels=['INBOX','Label_keep','UNREAD'];}
    public function id():string{return $this->provider==='gmail'?'m_fixture':'1709876190693100009';}
    public function request(string $method,string $url,array $headers=[],?string $body=null,bool $sending=false):array
    {
        $path=parse_url($url,PHP_URL_PATH);$this->calls[]=[$method,$path];$data=json_decode($body??'{}',true);
        if($method!=='GET'){
            $this->writes[]=[$method,$url,$data,$sending];
            if($this->failure==='before')throw new MailProviderException('provider');
            if($this->failure==='quota')throw new MailProviderException('quota',120);
            if($this->failure==='ignored')return $this->provider==='gmail'?['id'=>$this->id()]:['status'=>['code'=>200]];
            if($this->provider==='gmail'){
                if(str_ends_with($path,'/trash')){$this->labels=array_values(array_unique([...$this->labels,'TRASH']));}
                elseif(str_ends_with($path,'/untrash'))$this->labels=array_values(array_diff($this->labels,['TRASH']));
                elseif(str_ends_with($path,'/modify'))$this->labels=array_values(array_unique([...array_diff($this->labels,$data['removeLabelIds']),...$data['addLabelIds']]));
                else throw new RuntimeException('Unexpected Gmail write');
            }else{
                if($method==='DELETE'){
                    if(!str_ends_with($url,'?expunge=false')||$this->folder==='30')throw new RuntimeException('Unsafe trash endpoint');$this->folder='30';
                }elseif($method==='PUT'&&str_ends_with($path,'/updatemessage')){
                    if(($data['messageId']??null)!==[$this->id()])throw new RuntimeException('Message ID precision lost');
                    match($data['mode']){'markAsRead'=>$this->unread=false,'markAsUnread'=>$this->unread=true,'moveMessage'=>$this->folder=$data['destfolderId'],default=>throw new RuntimeException('Unexpected mode')};
                }else throw new RuntimeException('Unexpected Zoho write');
            }
            if($this->afterWrite)($this->afterWrite)();
            if($this->failure==='after')throw new MailProviderException('unknown');
            if($this->failure==='refresh')$this->refreshArmed=true;
            return $this->provider==='gmail'?['id'=>$this->id()]:['status'=>['code'=>200]];
        }
        if($this->failure==='read')throw new MailProviderException('network');
        if($this->refreshArmed)throw new MailProviderException('network');
        if($this->provider==='gmail'){
            if(str_ends_with($path,'/labels'))return ['labels'=>array_values(array_filter([['id'=>'INBOX','name'=>'Inbox'],['id'=>'TRASH','name'=>'Trash'],['id'=>'Label_keep','name'=>'Keep'],['id'=>'Label_target','name'=>'Target']],fn($f)=>!$this->missingDestination||$f['id']!=='Label_target'))];
            if(str_ends_with($path,'/messages/'.$this->id()))return ['id'=>$this->id(),'labelIds'=>$this->labels,'internalDate'=>(string)(time()*1000),'payload'=>['headers'=>[['name'=>'Subject','value'=>'Synthetic organization fixture']]]];
        }else{
            if($path==='/api/accounts')return ['status'=>['code'=>200],'data'=>[['type'=>'ZOHO_ACCOUNT','enabled'=>true,'accountId'=>'900000000000001','primaryEmailAddress'=>'remote@example.test']]];
            if(str_ends_with($path,'/folders'))return ['status'=>['code'=>200],'data'=>array_values(array_filter([['folderId'=>'10','folderName'=>'Inbox','folderType'=>'Inbox'],['folderId'=>'20','folderName'=>'Target','folderType'=>'Custom'],['folderId'=>'30','folderName'=>'Trash','folderType'=>'Trash']],fn($f)=>!$this->missingDestination||$f['folderId']!=='20'))];
            if(preg_match('~/folders/(\d+)/messages/'.$this->id().'/details$~',$path,$m)){
                if($m[1]!==$this->folder)throw new MailProviderException('missing');
                return ['status'=>['code'=>200],'data'=>['messageId'=>$this->id(),'folderId'=>$this->folder,'receivedTime'=>(string)(time()*1000),'status'=>$this->unread?'0':'1','subject'=>'Synthetic organization fixture']];
            }
        }
        throw new RuntimeException('Unexpected synthetic read');
    }
}

$remoteStart=$checks;
foreach(['gmail','zoho']as$provider){
    // Isolate fixture setup from the real account service's per-minute audit limit.
    $pdo->exec('UPDATE core_mail_audit_events SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
    $remoteAccount=$accounts->save(['provider_id'=>$providers[$provider],'email'=>$provider==='gmail'?'remote-fixture@gmail.com':'remote@example.test','label'=>'Remote fixture']);
    $remoteStore=new MailMessageService($pdo,1,$cipher,$remoteAccount);$remoteHttp=new RemoteFixtureHttp($provider);
    $remoteApi=$provider==='gmail'?new GmailMailProvider($remoteHttp):new ZohoMailProvider($remoteHttp,'us','remote@example.test',fn($id)=>$remoteHttp->folder);
    $remoteOauth=new RemoteFixtureOAuth($remoteStore,$remoteApi,[],new PilotHttp());$remoteService=new MailRemoteMessageService($remoteStore,$remoteOauth,$remoteApi,true);
    $pdo->prepare("UPDATE core_mail_accounts SET connection_status='connected' WHERE id=?")->execute([$remoteAccount]);
    $pdo->prepare('INSERT INTO core_mail_connections(owner_user_id,account_id,remote_identity,scopes,token_envelope) VALUES(1,?,?,?,?)')->execute([$remoteAccount,'fixture@example.test','[]','synthetic-envelope']);
    pilotExpect(!$remoteService->available($remoteAccount),'Missing scopes enabled writes');
    $fixture=$api->message('synthetic',$remoteHttp->id());$fixture['labelIds']=$provider==='gmail'?$remoteHttp->labels:['10','UNREAD'];
    $remoteFolders=$remoteApi->folders('synthetic');$folderMap=[];
    foreach($remoteFolders['labels']as$f){$pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(1,?,?,?)')->execute([$remoteAccount,$f['id'],$f['name']]);$folderMap[$f['id']]=(int)$pdo->lastInsertId();}
    $remoteId=$remoteStore->remember($remoteAccount,$fixture);$target=$folderMap[$provider==='gmail'?'Label_target':'20'];$trashFolder=$folderMap[$provider==='gmail'?'TRASH':'30'];
    $writes=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'read'),'Missing scopes accepted action');pilotExpect(count($remoteHttp->writes)===$writes,'Missing scopes contacted mutation endpoint');
    $pdo->prepare('UPDATE core_mail_connections SET scopes=? WHERE account_id=?')->execute([json_encode(MailRemoteMessageService::scopes($provider)),$remoteAccount]);
    pilotExpect($remoteService->available($remoteAccount),'Granted scopes failed availability');
    $disabled=new MailRemoteMessageService($remoteStore,$remoteOauth,$remoteApi,false);$calls=count($remoteHttp->calls);pilotReject(fn()=>$disabled->apply($remoteId,'read'),'Disabled feature wrote remotely');pilotExpect(count($remoteHttp->calls)===$calls,'Disabled feature called provider');
    foreach([['evil',0,0,true],['trash',0,0,false],['move',99999999,0,true],['move',$trashFolder,0,true],['read',$target,0,true],['move',$target,99999999,true]]as$args)pilotReject(fn()=>$remoteService->apply($remoteId,...$args),'Invalid action/context passed');
    $marks=new MailLocalOrganizationService($pdo,1);$marks->setMark($remoteId,'favorite',1);
    $remoteService->apply($remoteId,'read');pilotExpect((int)$remoteStore->message($remoteId)['is_unread']===0,'Read state not mirrored');
    $remoteService->apply($remoteId,'unread');pilotExpect((int)$remoteStore->message($remoteId)['is_unread']===1,'Unread state not mirrored');
    $remoteService->apply($remoteId,'move',$target);pilotExpect(count($remoteStore->listing($remoteAccount,'',1,$target)['rows'])===1,'Move not reflected in folder');
    if($provider==='gmail')pilotExpect(in_array('Label_keep',$remoteHttp->labels,true)&&!in_array('INBOX',$remoteHttp->labels,true),'Move removed unrelated labels or retained Inbox');
    if($provider==='gmail'){
        $remoteService->apply($remoteId,'move',$folderMap['INBOX'],$target);pilotExpect(!in_array('Label_target',$remoteHttp->labels,true)&&in_array('Label_keep',$remoteHttp->labels,true),'Explicit source removal lost other labels');
        $remoteHttp->labels=['SPAM','UNREAD','Label_keep'];$remoteService->apply($remoteId,'move',$folderMap['INBOX']);pilotExpect(!in_array('SPAM',$remoteHttp->labels,true)&&in_array('INBOX',$remoteHttp->labels,true),'Move out of spam kept spam classification');
    }
    $remoteService->apply($remoteId,'trash',0,0,true);pilotExpect($remoteStore->listing($remoteAccount)['rows']===[]&&count($remoteStore->listing($remoteAccount,'',1,$trashFolder)['rows'])===1,'Trash not reflected in cache');
    $writes=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'trash',0,0,true),'Second trash allowed');pilotExpect(count($remoteHttp->writes)===$writes,'Already trashed message sent again');
    $remoteService->apply($remoteId,'move',$target);pilotExpect(count($remoteStore->listing($remoteAccount)['rows'])===1,'Restore by move failed');
    pilotExpect((int)$marks->marks($remoteId)['is_favorite']===1,'Remote action erased local favorite');
    $pdo->prepare('UPDATE core_mail_sync_states SET lease_token=?,lease_until=UTC_TIMESTAMP()+INTERVAL 1 MINUTE WHERE account_id=?')->execute([str_repeat('a',32),$remoteAccount]);
    $calls=count($remoteHttp->calls);pilotReject(fn()=>$remoteService->apply($remoteId,'read'),'Action raced active sync');pilotExpect(count($remoteHttp->calls)===$calls,'Active sync still allowed HTTP');
    $pdo->prepare('UPDATE core_mail_sync_states SET lease_token=NULL,lease_until=NULL WHERE account_id=?')->execute([$remoteAccount]);
    $remoteHttp->missingDestination=true;$writes=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'move',$target),'Stale destination accepted');pilotExpect(count($remoteHttp->writes)===$writes,'Stale destination issued write');$remoteHttp->missingDestination=false;
    foreach(['read','before','after','refresh']as$failure){$remoteHttp->failure=$failure;$before=$remoteStore->message($remoteId)['is_unread'];pilotReject(fn()=>$remoteService->apply($remoteId,'read'),'Failure accepted as success');pilotExpect($remoteStore->message($remoteId)['is_unread']===$before,'Failed/uncertain action changed local cache');$leaseState=$pdo->query('SELECT lease_token FROM core_mail_sync_states WHERE account_id='.(int)$remoteAccount)->fetchColumn();pilotExpect($leaseState===null,'Failure leaked lease');}
    $remoteHttp->failure=null;$remoteHttp->refreshArmed=false;
    $remoteHttp->afterWrite=static function()use($pdo,$remoteAccount):void{$pdo->prepare('UPDATE core_mail_connections SET generation=generation+1 WHERE account_id=?')->execute([$remoteAccount]);};
    $before=$remoteStore->message($remoteId)['is_unread'];pilotReject(fn()=>$remoteService->apply($remoteId,'read'),'Reconnection race accepted stale result');pilotExpect($remoteStore->message($remoteId)['is_unread']===$before,'Reconnection race updated local message');$remoteHttp->afterWrite=null;
    $remoteHttp->failure='ignored';pilotReject(fn()=>$remoteService->apply($remoteId,'unread'),'Acknowledgment without changed state accepted');$remoteHttp->failure=null;
    $remoteHttp->failure='quota';pilotReject(fn()=>$remoteService->apply($remoteId,'read'),'Quota accepted as success');$remoteHttp->failure=null;
    pilotExpect((bool)$pdo->query('SELECT retry_after>UTC_TIMESTAMP() FROM core_mail_sync_states WHERE account_id='.(int)$remoteAccount)->fetchColumn(),'Quota lost cooldown');
    $calls=count($remoteHttp->calls);pilotReject(fn()=>$remoteService->apply($remoteId,'read'),'Quota cooldown ignored');pilotExpect(count($remoteHttp->calls)===$calls,'Cooldown called provider');
    $pdo->prepare('UPDATE core_mail_sync_states SET retry_after=NULL,failure_code=NULL WHERE account_id=?')->execute([$remoteAccount]);
    $pdo->prepare('UPDATE core_mail_messages SET is_unread=1 WHERE id=?')->execute([$remoteId]);
    require_once $root.'/app/helpers/csrf.php';
    $renderControls=static function(bool $enabled)use($remoteService,$remoteStore,$remoteId,$remoteAccount,$root):string{
        $mailOwner=1;$message=$remoteStore->message($remoteId);$account=$remoteStore->account($remoteAccount);$runtime=['organization'=>$remoteService];$config=['mail_center'=>['remote_organization_enabled'=>$enabled]];$readingFolder=0;$readingMark='all';
        $_SESSION['_csrf']='synthetic-csrf';$_GET=['source'=>'unified','list_folder'=>'inbox'];ob_start();require $root.'/web/admin/central_contas/_message_organization.php';return ob_get_clean();
    };
    pilotExpect($renderControls(false)==='','Disabled feature exposed controls');$controls=$renderControls(true);
    pilotExpect(substr_count($controls,'data-mail-remote-action')===3&&str_contains($controls,'Marcar como lida')&&str_contains($controls,'value="inbox"'),'Controls lost actions or unified context');
    pilotExpect(str_contains($controls,'name="confirmed" value="1" required')&&!str_contains($controls,'synthetic-organization-token'),'Trash lacks confirmation or control leaks token');
    if($provider==='gmail'){
        $html=file_get_contents($root.'/_deploy/mail-pilot-preview.html');$html=str_replace('<p class="mail-address">',$controls.'<p class="mail-address">',$html);file_put_contents($root.'/_deploy/mail-remote-preview.html',$html);
    }
    $trashOnlyWrites=count($remoteHttp->writes);pilotReject(fn()=>$remoteService->apply($remoteId,'delete',0,0,true),'Permanent deletion still accepted');pilotExpect(count($remoteHttp->writes)===$trashOnlyWrites,'Rejected delete contacted mutation endpoint');
    $remoteService->apply($remoteId,'trash',0,0,true);pilotExpect($remoteService->isInTrash($remoteId),'Trash membership not available');
    $trashOnlyControls=$renderControls(true);pilotExpect(!str_contains($trashOnlyControls,'Enviar para lixeira')&&!str_contains($trashOnlyControls,'Excluir definitivamente')&&str_contains($trashOnlyControls,'Mover'),'Trash should only offer read state and restoring by move');
    $trashOnlyPreview=file_get_contents($root.'/_deploy/mail-pilot-preview.html');file_put_contents($root.'/_deploy/mail-trash-only-'.$provider.'.html',str_replace('<p class="mail-address">',$trashOnlyControls.'<p class="mail-address">',$trashOnlyPreview));
    $remoteService->apply($remoteId,'move',$target);
    $otherRemote=new MailRemoteMessageService(new MailMessageService($pdo,2,$cipher),$remoteOauth,$remoteApi,true);pilotReject(fn()=>$otherRemote->apply($remoteId,'read'),'Remote update crossed owner');
    foreach($remoteHttp->writes as$write)pilotExpect($write[3]===true,'Write timeout could be treated as safely retryable');
}
$routeFile=$viewRoot.'/app/actions/central_contas/message_update.php';copy($root.'/app/actions/central_contas/message_update.php',$routeFile);
$routePayload=['_csrf'=>'synthetic-csrf','message_id'=>$remoteId,'operation'=>'read'];
pilotExpect(organizationRoute($routeFile,$routePayload,'GET')['status']===405,'Remote action permitted GET');
pilotExpect(organizationRoute($routeFile,[...$routePayload,'_csrf'=>'bad'])['status']===403,'Remote action ignored CSRF');
pilotExpect(organizationRoute($routeFile,$routePayload)['status']===403,'Remote action ignored disabled feature');
file_put_contents($viewRoot.'/app/bootstrap/bootstrap.php',' $config["mail_center"]["remote_organization_enabled"]=true;',FILE_APPEND);
pilotExpect(organizationRoute($routeFile,[...$routePayload,'return_account'=>$other])['status']===404,'Remote route accepted foreign account');
pilotExpect(organizationRoute($routeFile,[...$routePayload,'message_id'=>999999999])['status']===404,'Remote route exposed nonexistent message');
$pdo->exec('UPDATE core_mail_audit_events SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
foreach(['gmail','zoho']as$scopeProvider){
    $scopeAccount=$accounts->save(['provider_id'=>$providers[$scopeProvider],'email'=>$scopeProvider==='gmail'?'scope-fixture@gmail.com':'scope@example.test','label'=>'Scopes fixture']);
    $scopeSettings=['provider'=>$scopeProvider,'client_id'=>'synthetic','client_secret'=>'synthetic-secret','pilot_email'=>$scopeProvider==='gmail'?'scope-fixture@gmail.com':'scope@example.test','region'=>'us','redirect_uri'=>'https://core.example.test/callback','remote_organization_enabled'=>true];
    $scopeOauth=new MailOAuthService(new MailMessageService($pdo,1,$cipher,$scopeAccount),$api,$scopeSettings,new PilotHttp());$scopeSession=[];$scopeUrl=$scopeOauth->begin($scopeAccount,$scopeSession);parse_str(parse_url($scopeUrl,PHP_URL_QUERY),$scopeQuery);
    $scopeGranted=preg_split('/[\s,]+/',$scopeQuery['scope']);pilotExpect(!array_diff(MailRemoteMessageService::scopes($scopeProvider),$scopeGranted),'Opt-in authorization omitted organization scopes');
}
$remoteAudit=json_encode($pdo->query("SELECT action,result FROM core_mail_audit_events WHERE action='message.remote_updated'")->fetchAll(PDO::FETCH_ASSOC));
pilotExpect(str_contains($remoteAudit,'unknown')&&str_contains($remoteAudit,'success')&&!str_contains($remoteAudit,'synthetic-organization-token'),'Remote audit missing result or leaking token');
echo 'Organização remota: '.($checks-$remoteStart).' verificações sintéticas adicionais aprovadas.'.PHP_EOL;
