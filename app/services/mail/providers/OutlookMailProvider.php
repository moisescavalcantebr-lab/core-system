<?php
declare(strict_types=1);

/** Delegated Microsoft Graph access to the signed-in personal mailbox only. */
final class OutlookMailProvider implements MailProviderInterface
{
    private const BASE='https://graph.microsoft.com/v1.0/me';
    private const FIELDS='id,conversationId,parentFolderId,subject,from,toRecipients,ccRecipients,receivedDateTime,sentDateTime,isRead,isDraft,hasAttachments,internetMessageId';
    private array $messages=[];
    private ?string $sentFolder=null;
    public function __construct(private MailHttpClient $http) {}
    public function capabilities():array{return ['read'=>true,'send'=>true,'search'=>true,'attachments'=>true,'remote_modify'=>false,'sync'=>'poll'];}
    private static function id(string $id):string
    {
        if(!preg_match('~^[a-zA-Z0-9_+=/-]{1,255}$~D',$id))throw new InvalidArgumentException('Identificador Outlook inválido.');
        return rawurlencode($id);
    }
    private function get(string $token,string $path,array $query=[]):array
    {return $this->http->request('GET',self::BASE.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):''),['Authorization: Bearer '.$token,'Prefer: IdType="ImmutableId", outlook.body-content-type="html"']);}
    private static function next(string $url,string $path):string
    {
        $p=parse_url($url);
        if(strlen($url)>8192||($p['scheme']??'')!=='https'||($p['host']??'')!=='graph.microsoft.com'||($p['path']??'')!=='/v1.0/me'.$path||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment'])||!isset($p['query']))throw new MailProviderException('cursor');
        return $url;
    }
    private function page(string $token,string $url,string $path):array
    {return $this->http->request('GET',self::next($url,$path),['Authorization: Bearer '.$token,'Prefer: IdType="ImmutableId", outlook.body-content-type="html"']);}
    public function profile(string $accessToken):array
    {
        $row=$this->get($accessToken,'',['$select'=>'id,mail,userPrincipalName']);$mail=strtolower((string)($row['mail']??$row['userPrincipalName']??''));
        if(!filter_var($mail,FILTER_VALIDATE_EMAIL))throw new MailProviderException('provider');
        return ['emailAddress'=>$mail];
    }
    public function folders(string $accessToken):array
    {
        $sent=$this->get($accessToken,'/mailFolders/sentitems',['$select'=>'id']);$this->sentFolder=(string)($sent['id']??'');self::id($this->sentFolder);
        $rows=[];$next='';$pages=0;
        do{
            $response=$next!==''?$this->page($accessToken,$next,'/mailFolders'):$this->get($accessToken,'/mailFolders',['$top'=>100,'$select'=>'id,displayName']);
            foreach($response['value']??[]as$row){self::id((string)($row['id']??''));$rows[]=['id'=>$row['id'],'name'=>(string)($row['displayName']??'Pasta')];}
            $next=(string)($response['@odata.nextLink']??'');
            if(++$pages>=5&&$next!=='')throw new DomainException('Muitas pastas Outlook para esta atualização.');
        }while($next!=='');
        return ['labels'=>$rows];
    }
    private static function address(array $item):string
    {
        $a=$item['emailAddress']??[];$email=(string)($a['address']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return '';
        $name=MailMessageService::clean((string)($a['name']??''),200);
        return $name!==''?str_replace(['<','>'],['',''],$name).' <'.$email.'>':$email;
    }
    private function normalized(array $row):array
    {
        $id=(string)($row['id']??'');self::id($id);$folder=(string)($row['parentFolderId']??'');self::id($folder);
        $time=strtotime((string)($row['receivedDateTime']??$row['sentDateTime']??''));if($time===false||$time<1)throw new MailProviderException('provider');
        $this->messages[$id]=$row;$labels=[$folder];if(($row['isRead']??true)===false)$labels[]='UNREAD';if($folder===$this->sentFolder)$labels[]='SENT';
        $headers=[['name'=>'Subject','value'=>(string)($row['subject']??'')],['name'=>'From','value'=>self::address($row['from']??[])],['name'=>'Message-ID','value'=>(string)($row['internetMessageId']??'')]];
        foreach(['toRecipients'=>'To','ccRecipients'=>'Cc']as$key=>$name)$headers[]=['name'=>$name,'value'=>implode(', ',array_map(self::address(...),$row[$key]??[]))];
        foreach($row['internetMessageHeaders']??[]as$header)if(in_array(strtolower((string)($header['name']??'')),['reply-to','references'],true))$headers[]=$header;
        if(isset($row['replyTo'])&&$row['replyTo']){
            if(count($row['replyTo'])!==1||self::address($row['replyTo'][0])==='')throw new DomainException('A mensagem não tem um destinatário de resposta inequívoco. Confira no Outlook.');
            $headers[]=['name'=>'Reply-To','value'=>self::address($row['replyTo'][0])];
        }
        return ['id'=>$id,'threadId'=>(string)($row['conversationId']??''),'internalDate'=>(string)($time*1000),'labelIds'=>$labels,'hasAttachment'=>(bool)($row['hasAttachments']??false),'payload'=>['mimeType'=>'multipart/mixed','headers'=>$headers,'parts'=>[]]];
    }
    public function listMessages(string $accessToken,string $query='',string $page='',string $label=''):array
    {
        if(strlen($query)>500)throw new InvalidArgumentException('Busca Outlook inválida.');
        $path=$label!==''?'/mailFolders/'.self::id($label).'/messages':'/messages';
        $params=['$top'=>10,'$select'=>self::FIELDS];
        if(preg_match('/^after:([0-9]{1,12})$/D',$query,$m))$params+=['$filter'=>'receivedDateTime ge '.gmdate('Y-m-d\TH:i:s\Z',(int)$m[1]),'$orderby'=>'receivedDateTime desc'];
        elseif($query!=='')$params['$search']=json_encode($query,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        else $params['$orderby']='receivedDateTime desc';
        $response=$page!==''?$this->page($accessToken,$page,$path):$this->get($accessToken,$path,$params);$ids=[];
        foreach($response['value']??[]as$row){if(($row['isDraft']??false)===true)continue;$normal=$this->normalized($row);$ids[]=['id'=>$normal['id']];}
        $result=['messages'=>$ids];if(!empty($response['@odata.nextLink']))$result['nextPageToken']=self::next((string)$response['@odata.nextLink'],$path);return $result;
    }
    public function message(string $accessToken,string $id,bool $full=false):array
    {
        $path='/messages/'.self::id($id);$row=!$full&&isset($this->messages[$id])?$this->messages[$id]:$this->get($accessToken,$path,['$select'=>self::FIELDS.($full?',body,internetMessageHeaders,replyTo':'')]);
        if(($row['id']??'')!==$id)throw new MailProviderException('provider');$result=$this->normalized($row);
        if(!$full)return $result;
        $body=(string)($row['body']['content']??'');if(strlen($body)>1048576)throw new DomainException('Conteúdo acima do limite de leitura. Abra no Outlook.');
        $result['payload']['parts'][]=['partId'=>'0','mimeType'=>strtolower((string)($row['body']['contentType']??'html'))==='text'?'text/plain':'text/html','body'=>['data'=>rtrim(strtr(base64_encode($body),'+/','-_'),'=')]];
        if($result['hasAttachment']){
            $attachments=$this->get($accessToken,$path.'/attachments',['$top'=>100,'$select'=>'id,name,size,contentType,isInline']);
            if(!empty($attachments['@odata.nextLink']))throw new DomainException('Muitos anexos. Abra esta mensagem no Outlook.');
            foreach($attachments['value']??[]as$i=>$file){if(($file['@odata.type']??'')!=='#microsoft.graph.fileAttachment'||!empty($file['isInline']))continue;self::id((string)($file['id']??''));$result['payload']['parts'][]=['partId'=>(string)($i+1),'mimeType'=>(string)($file['contentType']??'application/octet-stream'),'filename'=>(string)($file['name']??'anexo'),'body'=>['size'=>(int)($file['size']??0),'attachmentId'=>$file['id']]];}
        }
        return $result;
    }
    public function history(string $accessToken,string $historyId,string $page=''):array{throw new DomainException('Outlook usa atualização por consulta nesta integração.');}
    public function attachment(string $accessToken,string $messageId,string $attachmentId):array
    {
        $path='/messages/'.self::id($messageId).'/attachments/'.self::id($attachmentId);
        $file=$this->get($accessToken,$path,['$select'=>'id,size']);if(($file['@odata.type']??'')!=='#microsoft.graph.fileAttachment'||($file['id']??'')!==$attachmentId)throw new MailProviderException('provider');
        if((int)($file['size']??PHP_INT_MAX)>2097152)throw new DomainException('Este anexo excede 2 MB. Abra-o no Outlook.');
        $bytes=$this->http->bytes(self::BASE.$path.'/$value',['Authorization: Bearer '.$accessToken,'Prefer: IdType="ImmutableId"']);return ['data'=>rtrim(strtr(base64_encode($bytes),'+/','-_'),'=')];
    }
    public function send(string $accessToken,string $raw,?string $threadId=null):array
    {
        $this->http->request('POST',self::BASE.'/sendMail',['Authorization: Bearer '.$accessToken,'Content-Type: text/plain','Prefer: IdType="ImmutableId"'],base64_encode($raw),true);
        // Graph returns 202 without a message ID. Acceptance is not delivery.
        return ['accepted'=>true];
    }
    public function findSent(string $token,string $messageId):?string
    {
        if(!preg_match('/^[a-zA-Z0-9@._-]{1,250}$/D',$messageId))throw new InvalidArgumentException('Referência de envio inválida.');
        $response=$this->get($token,'/mailFolders/sentitems/messages',['$top'=>2,'$select'=>'id,internetMessageId,isDraft','$filter'=>"internetMessageId eq '<".$messageId.">'" ]);
        $rows=$response['value']??[];if(count($rows)!==1||!empty($response['@odata.nextLink'])||($rows[0]['isDraft']??true)!==false||($rows[0]['internetMessageId']??'')!=='<'.$messageId.'>')return null;
        self::id((string)($rows[0]['id']??''));return $rows[0]['id'];
    }
}
