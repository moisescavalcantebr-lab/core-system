<?php
declare(strict_types=1);
// Runs only inside the pilot suite's disposable database and private directory.
if(PHP_SAPI!=='cli'||!isset($database,$pdo,$store,$cipher,$private,$root,$accounts,$providers))exit(1);

final class ZohoTestHttp extends MailHttpClient
{
    public array $requests=[];
    public string $profileEmail='corp@example.test';
    public int $sends=0;
    public ?string $sendError=null;
    public array $lastBody=[];
    public bool $extraPage=false;
    public ?array $profileAccounts=null;
    public string $replyAddress='reply@example.test';
    public function request(string $method,string $url,array $headers=[],?string $body=null,bool $sending=false):array
    {
        $this->requests[]=[$method,$url,$headers];$path=parse_url($url,PHP_URL_PATH);parse_str(parse_url($url,PHP_URL_QUERY)??'',$q);
        if(str_ends_with($path,'/token/revoke'))return ['status'=>'success'];
        if(($headers[0]??'')!=='Authorization: Zoho-oauthtoken synthetic-zoho-access')throw new RuntimeException('Invalid Zoho authorization header');
        $data=[];
        if($path==='/api/accounts')$data=$this->profileAccounts??[['type'=>'ZOHO_ACCOUNT','enabled'=>true,'accountId'=>'900000000000001','emailAddress'=>[['mailId'=>$this->profileEmail,'isPrimary'=>true,'isConfirmed'=>true,'isAlias'=>false]]]];
        elseif(str_ends_with($path,'/folders'))$data=[['folderId'=>'10','folderName'=>'Entrada','folderType'=>'Inbox'],['folderId'=>'20','folderName'=>'Enviados','folderType'=>'Sent']];
        elseif(str_ends_with($path,'/messages/view')||str_ends_with($path,'/messages/search')){
            $data=[$this->row('100'),$this->row('101')];if($this->extraPage)$data=array_map(fn($n)=>$this->row((string)(100+$n)),range(0,9));
            // Reproduce the live mailbox: the archive view is empty even
            // when ordinary received/sent messages exist.
            if(($q['includearchive']??'false')==='true')$data=[];
        }
        elseif(str_ends_with($path,'/details')){preg_match('~/folders/(\d+)/messages/(\d+)/details$~',$path,$m);$data=$this->row($m[2],$m[1]);}
        elseif(str_ends_with($path,'/header'))$data=['headerContent'=>['Reply-To'=>['Reply <'.$this->replyAddress.'>']],'messageId'=>'100'];
        elseif(str_ends_with($path,'/content'))$data=['messageId'=>'100','content'=>'<p>Zoho sintético</p><script>bad()</script><img src="https://tracker.test/pixel">'];
        elseif(str_ends_with($path,'/attachmentinfo'))$data=['attachments'=>[['attachmentId'=>'77','attachmentName'=>'teste.pdf','attachmentSize'=>17]]];
        elseif($method==='POST'&&str_ends_with($path,'/messages/attachments'))$data=[['storeName'=>'synthetic-store','attachmentName'=>'teste.pdf','attachmentPath'=>'/Mail/teste.pdf']];
        elseif($method==='POST'&&preg_match('~/messages(?:/\d+)?$~',$path)){$this->sends++;$this->lastBody=json_decode((string)$body,true);if($this->sendError)throw new MailProviderException($this->sendError);$data=['messageId'=>'500'];}
        else throw new RuntimeException('Unexpected synthetic Zoho endpoint: '.$path);
        return ['status'=>['code'=>200],'data'=>$data];
    }
    public function row(string $id,string $folder='10'):array{return ['messageId'=>$id,'folderId'=>$folder,'threadId'=>'999','subject'=>'Mensagem Zoho','fromAddress'=>'Person &lt;person@example.test&gt;','toAddress'=>'corp@example.test','ccAddress'=>'Not Provided','receivedTime'=>(string)(time()*1000),'status'=>'0','hasAttachment'=>'1'];}
    public function bytes(string $url,array $headers=[]):string{if(!str_contains($url,'/folders/10/messages/100/attachments/77'))throw new RuntimeException('Unexpected attachment');return '%PDF-1.4 synthetic';}
}

$zohoStart=$checks;
$pdo->exec('UPDATE core_mail_audit_events SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
$pdo->exec('UPDATE core_mail_outbox SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
$zohoSettings=['provider'=>'zoho','client_id'=>'1000.synthetic','client_secret'=>'synthetic-zoho-secret','pilot_email'=>'corp@example.test','region'=>'us','redirect_uri'=>'https://core.example.test/web/admin/central_contas/oauth_zoho_callback.php'];
pilotExpect(MailZohoConfig::validate($zohoSettings,'https://core.example.test')['endpoints']['accounts']==='https://accounts.zoho.com','Zoho region mismatch');
pilotReject(fn()=>MailZohoConfig::endpoints('evil.test'),'Region allowed arbitrary host');
pilotReject(fn()=>MailZohoConfig::validate([...$zohoSettings,'redirect_uri'=>'https://evil.test/callback'],'https://core.example.test'),'Unbound Zoho callback');
pilotReject(fn()=>ZohoMailProvider::data(['status'=>['code'=>403],'data'=>[]]),'Zoho API logical error treated as success');
$canonicalRow=['type'=>'ZOHO_ACCOUNT','enabled'=>true,'accountId'=>'123456789','primaryEmailAddress'=>'corp@example.test'];
pilotExpect(ZohoMailProvider::primaryIdentity([$canonicalRow],'corp@example.test')['accountId']==='123456789','Canonical Zoho primary address rejected without redundant address flags');
pilotExpect(ZohoMailProvider::primaryIdentity([[...$canonicalRow,'enabled'=>'true','emailAddress'=>[['mailId'=>'CORP@example.test','isPrimary'=>'true','isConfirmed'=>'1','isAlias'=>'false']]]],'corp@example.test')['emailAddress']==='corp@example.test','Documented primary identity with serialized boolean flags rejected');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([[...$canonicalRow,'enabled'=>false]],'corp@example.test'),'Disabled primary mailbox accepted');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([[...$canonicalRow,'enabled'=>'unknown']],'corp@example.test'),'Unknown enabled value accepted');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([[...$canonicalRow,'primaryEmailAddress'=>'other@example.test','emailAddress'=>[['mailId'=>'corp@example.test','isAlias'=>true,'isPrimary'=>false,'isConfirmed'=>true]]]],'corp@example.test'),'Alias accepted as configured primary');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([[...$canonicalRow,'emailAddress'=>[['mailId'=>'corp@example.test','isAlias'=>true]]]],'corp@example.test'),'Contradictory alias flag ignored');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([[...$canonicalRow,'emailAddress'=>[['mailId'=>'corp@example.test','isConfirmed'=>false]]]],'corp@example.test'),'Explicit unconfirmed address accepted');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([$canonicalRow,[...$canonicalRow,'accountId'=>'987654321']],'corp@example.test'),'Ambiguous primary mailbox accepted');
pilotReject(fn()=>ZohoMailProvider::primaryIdentity([[...$canonicalRow,'primaryEmailAddress'=>'other@example.test','emailAddress'=>[['mailId'=>'corp@example.test','isAlias'=>false,'isPrimary'=>true,'isConfirmed'=>true]]]],'corp@example.test'),'Canonical identity mismatch bypassed with secondary flags');
$aliasRow=['type'=>'ZOHO_ACCOUNT','enabled'=>true,'accountId'=>'900000000000001','primaryEmailAddress'=>'robot@example.test','emailAddress'=>[['mailId'=>'robot@example.test','isPrimary'=>true,'isConfirmed'=>true,'isAlias'=>false],['mailId'=>'corp@example.test','isPrimary'=>false,'isConfirmed'=>true,'isAlias'=>true]],'sendMailDetails'=>[['fromAddress'=>'corp@example.test','mode'=>'mailbox','status'=>true]]];
pilotExpect(ZohoMailProvider::mailboxIdentity([$aliasRow],'corp@example.test')['emailAddress']==='corp@example.test','Verified configured alias was replaced by the login primary');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([[...$aliasRow,'sendMailDetails'=>[]]],'corp@example.test'),'Login/member address without send permission accepted');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([[...$aliasRow,'emailAddress'=>[]]],'corp@example.test'),'Sender without verified mailbox membership accepted');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([[...$aliasRow,'emailAddress'=>[['mailId'=>'corp@example.test','isPrimary'=>false,'isConfirmed'=>false,'isAlias'=>true]]]],'corp@example.test'),'Unconfirmed alias accepted');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([[...$aliasRow,'sendMailDetails'=>[['fromAddress'=>'other@example.test','mode'=>'mailbox','status'=>true]]]],'corp@example.test'),'Different sender permission accepted');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([[...$aliasRow,'sendMailDetails'=>[['fromAddress'=>'corp@example.test','mode'=>'mailbox','status'=>false]]]],'corp@example.test'),'Disabled send identity accepted');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([[...$aliasRow,'sendMailDetails'=>[['fromAddress'=>'corp@example.test','mode'=>'extfrom','status'=>true,'validated'=>true]]]],'corp@example.test'),'External SMTP login identity accepted as mailbox alias');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([$aliasRow,[...$aliasRow,'accountId'=>'900000000000002']],'corp@example.test'),'Alias ambiguously linked to two mailboxes accepted');
pilotReject(fn()=>ZohoMailProvider::mailboxIdentity([$canonicalRow,$aliasRow],'corp@example.test'),'Primary and alias linked to different mailboxes accepted');
$zohoFunction=$accounts->saveCategory(['name'=>'Trabalho','color'=>'#16a34a']);
$zohoAccount=$accounts->save(['provider_id'=>$providers['zoho'],'category_id'=>$zohoFunction,'email'=>'corp@example.test','label'=>'Zoho sintético']);
$zohoStore=new MailMessageService($pdo,1,$cipher,$zohoAccount);
pilotReject(fn()=>$zohoStore->account($account),'Provider context accepted another owned mailbox');
pilotReject(fn()=>mailCenterResourceAccount($pdo,2,'message',(int)$message['id']),'Resource account lookup leaked owner');
$zhttp=new ZohoTestHttp();$zapi=new ZohoMailProvider($zhttp,'us','corp@example.test',static fn(string $id)=>'10');
pilotExpect($zapi->profile('synthetic-zoho-access')['emailAddress']==='corp@example.test','Zoho primary identity failed');
$wrongHttp=new ZohoTestHttp();$wrongHttp->profileEmail='wrong@example.test';
pilotReject(fn()=>(new ZohoMailProvider($wrongHttp,'us','corp@example.test'))->profile('synthetic-zoho-access'),'Wrong Zoho identity accepted');
$zhttp->profileAccounts=[$aliasRow];
$auth=new MailOAuthService($zohoStore,$zapi,$zohoSettings,$zhttp);$zsession=[];$url=$auth->begin($zohoAccount,$zsession);parse_str(parse_url($url,PHP_URL_QUERY),$zparams);
pilotExpect(parse_url($url,PHP_URL_HOST)==='accounts.zoho.com'&&$zparams['scope']===implode(',',MailZohoConfig::SCOPES)&&!isset($zparams['approval_prompt'])&&!isset($zparams['include_granted_scopes'])&&$zparams['prompt']==='consent'&&$zparams['access_type']==='offline','Wrong production Zoho OAuth parameters');
pilotExpect($zparams['code_challenge_method']==='S256'&&$zsession['mail_oauth']['provider']==='zoho','Zoho PKCE/provider context missing');
$crossSession=$zsession;pilotReject(fn()=>MailOAuthService::consume($crossSession,['state'=>$zparams['state'],'code'=>'synthetic'],1,$zohoSettings['redirect_uri'],'gmail'),'Zoho state accepted on Google callback');
$zpending=MailOAuthService::consume($zsession,['state'=>$zparams['state'],'code'=>'synthetic-code'],1,$zohoSettings['redirect_uri'],'zoho');
$zohoFactory=static function()use($zohoSettings){$response=new GuzzleHttp\Psr7\Response(200,['Content-Type'=>'application/json'],json_encode(['access_token'=>'synthetic-zoho-access','refresh_token'=>'synthetic-zoho-refresh','expires_in'=>3600,'scope'=>implode(',',MailZohoConfig::SCOPES),'token_type'=>'Bearer']));return new League\OAuth2\Client\Provider\GenericProvider(['clientId'=>'synthetic','clientSecret'=>'synthetic','redirectUri'=>$zohoSettings['redirect_uri'],'urlAuthorize'=>'https://accounts.zoho.com/oauth/v2/auth','urlAccessToken'=>'https://accounts.zoho.com/oauth/v2/token','urlResourceOwnerDetails'=>'https://mail.zoho.com/api/accounts','scopeSeparator'=>',','pkceMethod'=>'S256'],['httpClient'=>new GuzzleHttp\Client(['handler'=>GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([$response]))])]);};
$zohoAuth=new MailOAuthService($zohoStore,$zapi,$zohoSettings,$zhttp,$zohoFactory);$zohoAuth->complete($zpending,'synthetic-code');
pilotExpect($zapi->ownsAddress('robot@example.test')&&$zapi->ownsAddress('corp@example.test')&&!$zapi->ownsAddress('outside@example.test'),'Zoho mailbox membership was not restricted to the selected mailbox');
pilotExpect($zohoAuth->token($zohoAccount)==='synthetic-zoho-access','Zoho encrypted token read failed');
$pdo->prepare('UPDATE core_mail_connections SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE account_id=?')->execute([$zohoAccount]);
pilotExpect($zohoAuth->token($zohoAccount)==='synthetic-zoho-access','Zoho refresh failed');
pilotReject(fn()=>$oauth->token($zohoAccount),'Google OAuth used a Zoho connection');
$zsync=new MailSyncService($zohoStore,$zohoAuth,$zapi);$zr=$zsync->run($zohoAccount);
pilotExpect($zr['complete']&&$zr['processed']===2,'Zoho initial polling failed');
$listRequests=array_values(array_filter($zhttp->requests,fn($request)=>str_contains($request[1],'/messages/view?')));
parse_str(parse_url($listRequests[0][1],PHP_URL_QUERY),$pollParams);
pilotExpect(($pollParams['includearchive']??'false')==='false'&&($pollParams['includesent']??'false')==='true','Zoho polling selected archived-only mail or excluded sent messages');
$pdo->prepare('UPDATE core_mail_sync_states SET last_attempt_at=NULL WHERE account_id=?')->execute([$zohoAccount]);$zsync->run($zohoAccount);
pilotExpect(count($zohoStore->listing($zohoAccount)['rows'])===2,'Polling duplicated Zoho metadata');
pilotExpect(!array_filter($zhttp->requests,fn($request)=>str_contains($request[1],'/history')),'Zoho polling used Gmail history');
$zm=$zohoStore->listing($zohoAccount)['rows'][0];$zremote=$zapi->message('synthetic-zoho-access','100',true);$zparts=MailHtmlRenderer::parts($zremote['payload']);
pilotExpect(str_contains($zparts['html'],'Zoho sintético')&&!str_contains(MailHtmlRenderer::render($zparts['html']),'<script'),'Zoho HTML not sanitized');
$zfiles=new MailAttachmentService($zohoStore,$zohoAuth,$zapi,$private.'/attachments',$root);$zm100=$pdo->query("SELECT * FROM core_mail_messages WHERE account_id=".$zohoAccount." AND remote_id='100'")->fetch(PDO::FETCH_ASSOC);$zdesc=$zfiles->remoteDescriptors($zm100,$zparts['attachments']);
pilotExpect($zfiles->download((int)$zdesc[0]['id'])['bytes']==='%PDF-1.4 synthetic','Zoho binary attachment failed');
$zhttp->extraPage=true;$pagination=$zapi->listMessages('synthetic-zoho-access');pilotExpect($pagination['nextPageToken']==='11','Zoho offset pagination failed');$zhttp->extraPage=false;
$zsend=new MailSendService($zohoStore,$zohoAuth,$zapi,$zfiles);$zp=['account_id'=>$zohoAccount,'to'=>'recipient@example.test','subject'=>'Teste Zoho','body'=>'Corpo sintético'];$zd=$zsend->saveDraft($zp);$zfiles->storeBytes($zd,'teste.pdf',"%PDF-1.4\nsynthetic\n");$zsaved=$zsend->draft($zd);$zout=$zsend->submit($zd,(int)$zsaved['revision'],$zsaved['submission_key'],true);
pilotExpect($zout['status']==='confirmed'&&$zhttp->sends===1&&$zhttp->lastBody['fromAddress']==='corp@example.test'&&$zhttp->lastBody['mailFormat']==='plaintext'&&count($zhttp->lastBody['attachments'])===1,'Zoho send/sender/attachment failed');
$zsend->submit($zd,(int)$zsaved['revision'],$zsaved['submission_key'],true);pilotExpect($zhttp->sends===1,'Zoho double submit sent twice');
$zreply=$zsend->reply((int)$zm100['id']);pilotExpect($zreply['account_id']===$zohoAccount&&$zreply['to']===['reply@example.test']&&$zreply['reply_headers']['remote_id']==='100','Zoho reply changed origin or ignored Reply-To');
$zhttp->replyAddress='robot@example.test';pilotReject(fn()=>$zsend->reply((int)$zm100['id']),'Reply addressed to another alias of the same mailbox accepted');$zhttp->replyAddress='reply@example.test';
$zrd=$zsend->saveDraft([...$zp,'reply_message_id'=>$zm100['id'],'subject'=>$zreply['subject']]);$zrs=$zsend->draft($zrd);$zsend->submit($zrd,(int)$zrs['revision'],$zrs['submission_key'],true);
pilotExpect($zhttp->lastBody['action']==='reply'&&$zhttp->sends===2,'Zoho reply endpoint not used');
$zunknownId=$zsend->saveDraft($zp);$zus=$zsend->draft($zunknownId);$zhttp->sendError='unknown';$zu=$zsend->submit($zunknownId,(int)$zus['revision'],$zus['submission_key'],true);$zhttp->sendError=null;
pilotExpect($zu['status']==='unknown','Zoho timeout allowed automatic retry');$zsend->submit($zunknownId,(int)$zus['revision'],$zus['submission_key'],true);$zsend->reconcile((int)$zu['id']);pilotExpect($zhttp->sends===3,'Zoho uncertain result resent');
$all=$store->unifiedListing();pilotExpect(count($all['rows'])===2&&$all['rows'][0]['account_email']==='corp@example.test','Unified cache origin or connection filter failed');
pilotExpect((new MailMessageService($pdo,2,$cipher))->unifiedListing()['rows']===[],'Unified cache leaked to another owner');
pilotReject(fn()=>$zohoStore->unifiedListing(),'Bound mailbox allowed unified context');
$pdo->prepare("UPDATE core_mail_connections SET status='connected',token_envelope=? WHERE account_id=?")->execute([$cipher->seal(['access_token'=>'synthetic-access','refresh_token'=>'synthetic-refresh'],'mail:1:'.$account.':tokens'),$account]);
$pdo->prepare("UPDATE core_mail_accounts SET connection_status='connected' WHERE id=?")->execute([$account]);
pilotExpect(count($store->unifiedListing('Zoho')['rows'])===2&&count($store->listing($account,'Zoho')['rows'])===0&&count($zohoStore->listing($zohoAccount,'Zoho')['rows'])===2,'Busca não respeitou todos os e-mails versus conta individual');
$mixed=$store->unifiedListing();pilotExpect(count(array_unique(array_column($mixed['rows'],'provider_name')))===2&&count($mixed['rows'])===4,'Unified inbox lost provider identities or duplicated metadata');
require __DIR__.'/mail_center_sync_all_test.php';
pilotExpect(count($store->unifiedListing('',1,'inbox')['rows'])===4,'Entrada unificada não reuniu Gmail e Zoho');
$identityColors=[];foreach($store->unifiedListing()['rows']as$coloredRow)$identityColors[$coloredRow['provider_name']]=$coloredRow['account_color'];pilotExpect(($identityColors['Gmail']??'')==='#3b82f6'&&($identityColors['Zoho Mail']??'')==='#16a34a','Lista unificada misturou cores de funções entre contas');
pilotExpect(count($store->unifiedListing('Zoho',1,'inbox')['rows'])===2,'Busca ignorou pasta unificada');
pilotReject(fn()=>$store->unifiedListing('',1,'unknown'),'Pasta unificada desconhecida aceita');
pilotExpect((new MailMessageService($pdo,2,$cipher))->unifiedListing('',1,'inbox')['rows']===[],'Pasta unificada expôs outro proprietário');
foreach(['SENT'=>'sent','DRAFT'=>'drafts','SPAM'=>'spam','TRASH'=>'trash']as$remoteFolder=>$kind){
    $pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(?,?,?,?)')->execute([1,$account,$remoteFolder,$remoteFolder]);
    $sample=$api->message('synthetic','system_'.$kind);$sample['labelIds']=[$remoteFolder];$store->remember($account,$sample);
    pilotExpect(count($store->unifiedListing('',1,$kind)['rows'])===1,'Pasta principal misturou mensagens: '.$kind);
}
// Different local folders mapped to Inbox must not duplicate the same message.
$pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(?,?,?,?)')->execute([1,$zohoAccount,'99','Caixa de entrada']);$extraFolder=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO core_mail_message_folders(message_id,folder_id,account_id,owner_user_id) VALUES(?,?,?,?)')->execute([$zm100['id'],$extraFolder,$zohoAccount,1]);
pilotExpect(count($store->unifiedListing('',1,'inbox')['rows'])===4,'Pasta unificada duplicou mensagem com múltiplas associações');
$custom=$api->message('synthetic','custom_folder_only');$custom['labelIds']=['Label_1'];$pdo->prepare("UPDATE core_mail_folders SET name='Inbox' WHERE account_id=? AND remote_id='Label_1'")->execute([$account]);$store->remember($account,$custom);
pilotExpect(count($store->unifiedListing('',1,'inbox')['rows'])===4,'Marcador Gmail personalizado confundido com pasta de sistema');
foreach(['unified'=>['unified.php',[]],'unified-inbox'=>['unified.php',['folder'=>'inbox']],'unified-sent'=>['unified.php',['folder'=>'sent']],'zoho-inbox'=>['messages.php',['account'=>$zohoAccount]]]as$name=>$viewCase){
    $code='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REQUEST_URI"]="/web/admin/central_contas/messages.php";$_GET='.var_export($viewCase[1],true).';require '.var_export($viewRoot.'/web/admin/central_contas/'.$viewCase[0],true).';';
    $process=proc_open([PHP_BINARY,'-d','display_errors=1','-d','error_reporting=32767','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    pilotExpect(proc_close($process)===0&&!preg_match('/Warning:|Fatal error:|Fixture render error/',$html.$errors)&&str_contains($html,'corp@example.test'),'Zoho/unified production view failed');
    file_put_contents($root.'/_deploy/mail-pilot-'.$name.'.html',$html);
}
$pdo->prepare("UPDATE core_mail_connections SET status='disconnected',token_envelope=NULL WHERE account_id=?")->execute([$account]);
$pdo->prepare("UPDATE core_mail_accounts SET connection_status='disconnected' WHERE id=?")->execute([$account]);
$clientPath=$private.'/zoho-client.json';file_put_contents($clientPath,json_encode(['client_id'=>$zohoSettings['client_id'],'client_secret'=>$zohoSettings['client_secret']]));$beforeSetup=json_decode((string)file_get_contents($private.'/secrets.json'),true);
if(function_exists('posix_geteuid')&&posix_geteuid()===0){chown($private.'/secrets.json',33);chgrp($private.'/secrets.json',33);}
clearstatcache(true,$private.'/secrets.json');$expectedOwner=fileowner($private.'/secrets.json');$expectedGroup=filegroup($private.'/secrets.json');
foreach([false,true]as$apply){$args=[PHP_BINARY,$root.'/app/console/mail_center_zoho_setup.php','--client-json='.$clientPath,'--private-dir='.$private,'--callback='.$zohoSettings['redirect_uri'],'--email=corp@example.test','--region=us'];if($apply)$args[]='--apply';$process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$setupOutput=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);pilotExpect(proc_close($process)===0&&!str_contains($setupOutput,$zohoSettings['client_secret']),'Private Zoho setup failed or leaked credentials');if(!$apply)pilotExpect(json_decode((string)file_get_contents($private.'/secrets.json'),true)===$beforeSetup,'Dry run changed existing secrets');}
$afterSetup=json_decode((string)file_get_contents($private.'/secrets.json'),true);pilotExpect($afterSetup['google']===$beforeSetup['google']&&$afterSetup['keys']===$beforeSetup['keys']&&$afterSetup['zoho']['region']==='us'&&count(glob($private.'/secrets-before-zoho-*.json'))===1,'Zoho setup replaced Gmail or encryption keys');
clearstatcache(true,$private.'/secrets.json');pilotExpect(fileowner($private.'/secrets.json')===$expectedOwner&&filegroup($private.'/secrets.json')===$expectedGroup&&(fileperms($private.'/secrets.json')&0777)===0600,'Zoho setup lost runtime ownership or exposed private file');
$process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$repeated=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);pilotExpect(proc_close($process)===1&&json_decode((string)file_get_contents($private.'/secrets.json'),true)===$afterSetup,'Repeated setup overwrote private settings');
$audit=json_encode($pdo->query('SELECT * FROM core_mail_audit_events')->fetchAll(PDO::FETCH_ASSOC));pilotExpect(!str_contains($audit,'synthetic-zoho-access')&&!str_contains($audit,'Corpo sintético'),'Zoho audit leaked token/body');
pilotExpect($zohoAuth->disconnect($zohoAccount),'Zoho revoke failed');
pilotExpect($store->unifiedListing()['rows']===[],'Disconnected Zoho stayed in unified cache');
echo 'Zoho: '.($checks-$zohoStart).' verificações sintéticas adicionais aprovadas.'.PHP_EOL;
