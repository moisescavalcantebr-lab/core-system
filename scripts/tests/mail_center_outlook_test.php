<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($database,$pdo,$cipher,$private,$accounts,$providers))exit(1);
final class OutlookTestHttp extends MailHttpClient
{
    public array $requests=[];public int $sends=0;public ?string $sendError=null;public bool $visibleSent=false;public bool $nextPage=false;public bool $evilNext=false;public string $email='personal@outlook.com';public string $raw='';public string $messageId='';
    public function row(string $id='AQMk/incoming+1='):array{return ['id'=>$id,'parentFolderId'=>'AQMk/inbox+1=','conversationId'=>'conversation/1=','subject'=>'Mensagem Outlook','from'=>['emailAddress'=>['address'=>'person@example.test','name'=>'Pessoa']],'toRecipients'=>[['emailAddress'=>['address'=>$this->email]]],'receivedDateTime'=>gmdate('c'),'isRead'=>false,'isDraft'=>false,'hasAttachments'=>true,'internetMessageId'=>'<original-outlook@example.test>'];}
    public function request(string $method,string $url,array $headers=[],?string $body=null,bool $sending=false):array
    {
        $this->requests[]=[$method,$url,$headers];$path=rawurldecode(parse_url($url,PHP_URL_PATH));parse_str(parse_url($url,PHP_URL_QUERY)??'',$query);
        if(($headers[0]??'')!=='Authorization: Bearer synthetic-outlook-access')throw new RuntimeException('Wrong Outlook authorization');
        if($path==='/v1.0/me')return ['mail'=>$this->email];
        if($path==='/v1.0/me/mailFolders/sentitems')return ['id'=>'AQMk/sent+1='];
        if($path==='/v1.0/me/mailFolders')return ['value'=>[['id'=>'AQMk/inbox+1=','displayName'=>'Entrada'],['id'=>'AQMk/sent+1=','displayName'=>'Enviados']]];
        if($path==='/v1.0/me/messages'){
            $r=['value'=>[$this->row()]];if($this->nextPage)$r['@odata.nextLink']='https://graph.microsoft.com/v1.0/me/messages?$skiptoken=opaque%2Btoken';if($this->evilNext)$r['@odata.nextLink']='https://evil.test/v1.0/me/messages?token=bad';return $r;
        }
        if(str_ends_with($path,'/attachments/AQMk/file+1='))return ['@odata.type'=>'#microsoft.graph.fileAttachment','id'=>'AQMk/file+1=','size'=>17];
        if(str_ends_with($path,'/attachments'))return ['value'=>[['@odata.type'=>'#microsoft.graph.fileAttachment','id'=>'AQMk/file+1=','name'=>'teste.pdf','size'=>17,'contentType'=>'application/pdf']]];
        if($path==='/v1.0/me/messages/AQMk/incoming+1=')return $this->row()+['body'=>['contentType'=>'html','content'=>'<p>Outlook sintético</p><img src="https://tracker.test/pixel">'],'replyTo'=>[['emailAddress'=>['address'=>'reply@example.test']]]];
        if($path==='/v1.0/me/sendMail'){
            $this->sends++;$this->raw=(string)base64_decode((string)$body,true);preg_match('/Message-ID:\s*<([^>]+)>/i',$this->raw,$m);$this->messageId=$m[1]??'';
            if($this->sendError)throw new MailProviderException($this->sendError);return [];
        }
        if($path==='/v1.0/me/mailFolders/sentitems/messages')return ['value'=>$this->visibleSent?[['id'=>'AQMk/sentMessage+1=','internetMessageId'=>'<'.$this->messageId.'>','isDraft'=>false]]:[]];
        throw new RuntimeException('Unexpected synthetic Outlook endpoint');
    }
    public function bytes(string $url,array $headers=[]):string{if(!str_ends_with($url,'/attachments/AQMk%2Ffile%2B1%3D/$value'))throw new RuntimeException('Unsafe attachment endpoint');return '%PDF-1.4 synthetic';}
}
$outlookStart=$checks;
$settings=['provider'=>'outlook','client_id'=>'11111111-2222-3333-4444-555555555555','client_secret'=>'synthetic-outlook-secret','pilot_email'=>'personal@outlook.com','redirect_uri'=>'https://core.example.test/web/admin/central_contas/oauth_outlook_callback.php'];
pilotExpect(MailOutlookConfig::validate($settings,'https://core.example.test')['provider']==='outlook','Outlook config rejected');
pilotReject(fn()=>MailOutlookConfig::validate([...$settings,'redirect_uri'=>'https://evil.test/callback'],'https://core.example.test'),'Unbound Outlook callback accepted');
pilotReject(fn()=>MailOutlookConfig::validate([...$settings,'client_id'=>'wrong'],'https://core.example.test'),'Invalid Microsoft client ID accepted');
$outlookId=$accounts->save(['provider_id'=>$providers['outlook'],'email'=>$settings['pilot_email'],'label'=>'Outlook pessoal']);
$ostore=new MailMessageService($pdo,1,$cipher,$outlookId);$ohttp=new OutlookTestHttp();$oapi=new OutlookMailProvider($ohttp);
pilotExpect($oapi->profile('synthetic-outlook-access')['emailAddress']===$settings['pilot_email'],'Outlook identity not read from Graph');
$tokenScopes='https://graph.microsoft.com/User.Read https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/Mail.Send';
$factory=static function()use(&$tokenScopes,$settings){$response=new GuzzleHttp\Psr7\Response(200,['Content-Type'=>'application/json'],json_encode(['access_token'=>'synthetic-outlook-access','refresh_token'=>'synthetic-outlook-refresh','expires_in'=>3600,'scope'=>$tokenScopes,'token_type'=>'Bearer']));return new League\OAuth2\Client\Provider\GenericProvider(['clientId'=>$settings['client_id'],'clientSecret'=>$settings['client_secret'],'redirectUri'=>$settings['redirect_uri'],'urlAuthorize'=>'https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize','urlAccessToken'=>'https://login.microsoftonline.com/consumers/oauth2/v2.0/token','urlResourceOwnerDetails'=>'https://graph.microsoft.com/v1.0/me','scopeSeparator'=>' ','scopes'=>MailOutlookConfig::SCOPES,'pkceMethod'=>'S256'],['httpClient'=>new GuzzleHttp\Client(['handler'=>GuzzleHttp\HandlerStack::create(new GuzzleHttp\Handler\MockHandler([$response]))])]);};
$oauthOutlook=new MailOAuthService($ostore,$oapi,$settings,$ohttp,$factory);$pdo->exec('UPDATE core_mail_audit_events SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');$session=[];
$url=$oauthOutlook->begin($outlookId,$session);parse_str(parse_url($url,PHP_URL_QUERY),$params);
pilotExpect(parse_url($url,PHP_URL_HOST)==='login.microsoftonline.com'&&str_contains($url,'/consumers/')&&!isset($params['access_type'])&&!isset($params['approval_prompt'])&&$params['code_challenge_method']==='S256','Wrong Outlook OAuth flow');
pilotExpect(in_array('offline_access',explode(' ',$params['scope']),true)&&!str_contains($params['scope'],'ReadWrite'),'Missing offline or excessive Outlook permission');
$cross=$session;pilotReject(fn()=>MailOAuthService::consume($cross,['state'=>$params['state'],'code'=>'synthetic-code'],1,$settings['redirect_uri'],'zoho'),'Outlook state crossed provider');
$pending=MailOAuthService::consume($session,['state'=>$params['state'],'code'=>'synthetic-code'],1,$settings['redirect_uri'],'outlook');
$tokenScopes.=' https://graph.microsoft.com/Mail.ReadWrite';pilotReject(fn()=>$oauthOutlook->complete($pending,'synthetic-code'),'Excess Outlook permission accepted');$tokenScopes='User.Read Mail.Read Mail.Send';
$ohttp->email='other@outlook.com';pilotReject(fn()=>$oauthOutlook->complete($pending,'synthetic-code'),'Wrong Microsoft identity accepted');$ohttp->email=$settings['pilot_email'];$oauthOutlook->complete($pending,'synthetic-code');
pilotExpect($oauthOutlook->token($outlookId)==='synthetic-outlook-access','Outlook token not encrypted/read');
pilotReject(fn()=>$zohoAuth->token($outlookId),'Zoho context accepted Outlook');
$pdo->prepare('UPDATE core_mail_connections SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 MINUTE WHERE account_id=?')->execute([$outlookId]);pilotExpect($oauthOutlook->token($outlookId)==='synthetic-outlook-access','Microsoft refresh failed');
$osync=new MailSyncService($ostore,$oauthOutlook,$oapi);$r=$osync->run($outlookId);pilotExpect($r['complete']&&$r['processed']===1&&count($ostore->listing($outlookId)['rows'])===1,'Outlook polling failed to store base64 Graph identifiers');
$ohttp->nextPage=true;$list=$oapi->listMessages('synthetic-outlook-access','after:'.(time()-86400));pilotExpect(str_contains($list['nextPageToken'],'opaque%2Btoken'),'Graph nextLink was modified');$oapi->listMessages('synthetic-outlook-access','',$list['nextPageToken']);$ohttp->nextPage=false;
$before=count($ohttp->requests);pilotReject(fn()=>$oapi->listMessages('synthetic-outlook-access','','https://evil.test/v1.0/me/messages?$skip=10'),'Foreign Graph pagination host accepted');pilotExpect(count($ohttp->requests)===$before,'Bearer token sent to a foreign host');
pilotReject(fn()=>$oapi->listMessages('synthetic-outlook-access','','https://graph.microsoft.com/v1.0/users/other/messages?$skip=1'),'Graph pagination crossed mailbox');
$ohttp->evilNext=true;pilotReject(fn()=>$oapi->listMessages('synthetic-outlook-access'),'Malicious nextLink stored');$ohttp->evilNext=false;
$oapi->listMessages('synthetic-outlook-access','from:person@example.test');$last=$ohttp->requests[array_key_last($ohttp->requests)];parse_str(parse_url($last[1],PHP_URL_QUERY),$q);pilotExpect(isset($q['$search'])&&!isset($q['$filter']),'Outlook search mixed unsupported filter');
$om=$ostore->listing($outlookId)['rows'][0];$full=$oapi->message('synthetic-outlook-access',$om['remote_id'],true);$parts=MailHtmlRenderer::parts($full['payload']);pilotExpect(count($parts['attachments'])===1,'Outlook attachments missing');
$oattachments=new MailAttachmentService($ostore,$oauthOutlook,$oapi,$private.'/attachments',$root);$odescriptors=$oattachments->remoteDescriptors($om,$parts['attachments']);pilotExpect(str_starts_with($oattachments->download((int)$odescriptors[0]['id'])['bytes'],'%PDF-'),'Outlook attachment download failed');
$osend=new MailSendService($ostore,$oauthOutlook,$oapi,$oattachments);pilotExpect($osend->reply((int)$om['id'])['to']===['reply@example.test'],'Outlook Reply-To ignored');
$pdo->exec('UPDATE core_mail_outbox SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
$payloadOutlook=['account_id'=>$outlookId,'to'=>'recipient@example.test','cc'=>'copy@example.test','subject'=>'Teste Outlook','body'=>'Texto sintético'];
$draftIdOutlook=$osend->saveDraft($payloadOutlook);$draftOutlook=$osend->draft($draftIdOutlook);$oattachments->storeBytes($draftIdOutlook,'teste.pdf','%PDF-1.4 synthetic');$draftOutlook=$osend->draft($draftIdOutlook);$accepted=$osend->submit($draftIdOutlook,(int)$draftOutlook['revision'],$draftOutlook['submission_key'],true);
pilotExpect($accepted['status']==='accepted'&&$ohttp->sends===1,'Graph 202 without ID was classified as uncertain or delivered');
pilotExpect(str_contains($ohttp->raw,$settings['pilot_email'])&&str_contains($ohttp->raw,'multipart/mixed')&&str_contains($ohttp->raw,'copy@example.test'),'Microsoft MIME lost sender, attachment or CC');
$osend->submit($draftIdOutlook,(int)$draftOutlook['revision'],$draftOutlook['submission_key'],true);pilotExpect($ohttp->sends===1,'Accepted Outlook email resent');
pilotExpect($osend->reconcile((int)$accepted['id'])['status']==='accepted','Missing sent copy marked delivered');$ohttp->visibleSent=true;pilotExpect($osend->reconcile((int)$accepted['id'])['status']==='confirmed'&&$ohttp->sends===1,'Sent copy reconciliation failed or resent');
$ohttp->sendError='unknown';$ohttp->visibleSent=false;$d2=$osend->saveDraft($payloadOutlook);$d2row=$osend->draft($d2);$uncertain=$osend->submit($d2,(int)$d2row['revision'],$d2row['submission_key'],true);pilotExpect($uncertain['status']==='unknown','Outlook timeout became retryable');$osend->submit($d2,(int)$d2row['revision'],$d2row['submission_key'],true);pilotExpect($ohttp->sends===2,'Uncertain Microsoft send retried');
pilotExpect(count($store->unifiedListing()['rows'])===1&&$store->unifiedListing()['rows'][0]['provider_name']==='Outlook','Outlook not isolated in unified listing');
// Render the real controller with the existing isolated view bootstrap.
$code='$_SERVER["REQUEST_METHOD"]="GET";$_SERVER["REQUEST_URI"]="/web/admin/central_contas/messages.php";$_GET=["account"=>'.$outlookId.'];require '.var_export($viewRoot.'/web/admin/central_contas/messages.php',true).';';$process=proc_open([PHP_BINARY,'-d','display_errors=1','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$html=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);pilotExpect(proc_close($process)===0&&!preg_match('/Warning:|Fatal error:/',$html.$errors)&&str_contains($html,'personal@outlook.com'),'Outlook view failed');file_put_contents($root.'/_deploy/mail-pilot-outlook-inbox.html',$html);
pilotExpect(!$oauthOutlook->disconnect($outlookId),'Local Outlook disconnect claimed global consent revocation');pilotReject(fn()=>$oauthOutlook->token($outlookId),'Disconnected Outlook token remained usable');
$remaining=$pdo->prepare('SELECT token_envelope FROM core_mail_connections WHERE account_id=?');$remaining->execute([$outlookId]);pilotExpect($remaining->fetchColumn()===null,'Microsoft token not deleted locally');
$client=$private.'/outlook-client.json';file_put_contents($client,json_encode(['client_id'=>$settings['client_id'],'client_secret'=>$settings['client_secret']]));
$beforeSettings=json_decode((string)file_get_contents($private.'/secrets.json'),true);clearstatcache(true,$private.'/secrets.json');$beforeOwner=fileowner($private.'/secrets.json');$beforeGroup=filegroup($private.'/secrets.json');
foreach([false,true]as$apply){
    $args=[PHP_BINARY,$root.'/app/console/mail_center_outlook_setup.php','--client-json='.$client,'--private-dir='.$private,'--callback='.$settings['redirect_uri'],'--email='.$settings['pilot_email']];if($apply)$args[]='--apply';
    $process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$output=stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);pilotExpect(proc_close($process)===0&&!str_contains($output,$settings['client_secret']),'Outlook private setup failed or exposed secret');
    if(!$apply)pilotExpect(json_decode((string)file_get_contents($private.'/secrets.json'),true)===$beforeSettings,'Outlook dry run changed secrets');
}
$afterSettings=json_decode((string)file_get_contents($private.'/secrets.json'),true);$preserved=$afterSettings;unset($preserved['outlook']);pilotExpect($preserved===$beforeSettings&&count(glob($private.'/secrets-before-outlook-*.json'))===1,'Outlook setup changed Gmail/Zoho/keys or lost backup');
clearstatcache(true,$private.'/secrets.json');pilotExpect(fileowner($private.'/secrets.json')===$beforeOwner&&filegroup($private.'/secrets.json')===$beforeGroup&&(fileperms($private.'/secrets.json')&0777)===0600,'Outlook setup lost runtime ownership/private mode');
$process=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);pilotExpect(proc_close($process)===1&&json_decode((string)file_get_contents($private.'/secrets.json'),true)===$afterSettings,'Outlook setup overwrote existing configuration');
echo 'Outlook: '.($checks-$outlookStart).' verificações sintéticas adicionais aprovadas.'.PHP_EOL;
