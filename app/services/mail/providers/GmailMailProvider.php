<?php
declare(strict_types=1);

final class GmailMailProvider implements MailProviderInterface, MailMutableProviderInterface
{
    public function __construct(private MailHttpClient $http) {}
    public function capabilities(): array { return ['read'=>true,'send'=>true,'search'=>true,'attachments'=>true,'remote_modify'=>true]; }
    private static function id(string $value): string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,512}$/D',$value)) { throw new InvalidArgumentException('Identificador remoto inválido.'); }
        return rawurlencode($value);
    }
    private function get(string $token, string $path, array $query = []): array
    {
        return $this->http->request('GET','https://gmail.googleapis.com/gmail/v1/users/me/'.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):''),['Authorization: Bearer '.$token]);
    }
    public function profile(string $accessToken): array { return $this->get($accessToken,'profile'); }
    public function folders(string $accessToken): array { return $this->get($accessToken,'labels'); }
    public function listMessages(string $accessToken, string $query = '', string $page = '', string $label = ''): array
    {
        if (strlen($query)>500 || strlen($page)>4096 || strlen($label)>255) { throw new InvalidArgumentException('Busca inválida.'); }
        return $this->get($accessToken,'messages',array_filter(['maxResults'=>10,'q'=>$query,'pageToken'=>$page,'labelIds'=>$label],static fn($v)=>$v!==''));
    }
    public function message(string $accessToken, string $id, bool $full = false): array { return $this->get($accessToken,'messages/'.self::id($id),['format'=>$full?'full':'metadata']); }
    public function history(string $accessToken, string $historyId, string $page = ''): array
    {
        if (!preg_match('/^\d{1,40}$/D',$historyId) || strlen($page)>4096) { throw new InvalidArgumentException('Histórico inválido.'); }
        try { return $this->get($accessToken,'history',array_filter(['startHistoryId'=>$historyId,'maxResults'=>10,'pageToken'=>$page],static fn($v)=>$v!=='')); }
        catch(MailProviderException $e){ if($e->kind==='missing')throw new MailProviderException('cursor'); throw $e; }
    }
    public function attachment(string $accessToken, string $messageId, string $attachmentId): array { return $this->get($accessToken,'messages/'.self::id($messageId).'/attachments/'.self::id($attachmentId)); }
    public function send(string $accessToken, string $raw, ?string $threadId = null): array
    {
        $body=['raw'=>rtrim(strtr(base64_encode($raw),'+/','-_'),'=')];
        if ($threadId !== null) { self::id($threadId); $body['threadId']=$threadId; }
        return $this->http->request('POST','https://gmail.googleapis.com/gmail/v1/users/me/messages/send',['Authorization: Bearer '.$accessToken,'Content-Type: application/json'],json_encode($body,JSON_THROW_ON_ERROR),true);
    }

    public function updateMessage(string $token,string $id,string $action,?string $destination=null,?string $source=null): array
    {
        $path='messages/'.self::id($id);$headers=['Authorization: Bearer '.$token,'Content-Type: application/json'];
        if(!in_array($action,['read','unread','move','trash'],true))throw new InvalidArgumentException('Ação inválida.');
        if($action==='trash')$this->http->request('POST','https://gmail.googleapis.com/gmail/v1/users/me/'.$path.'/trash',$headers,null,true);
        else{
            $add=[];$remove=[];
            if($action==='read')$remove=['UNREAD'];elseif($action==='unread')$add=['UNREAD'];
            else{
                if($destination===null||!($destination==='INBOX'||str_starts_with($destination,'Label_')))throw new InvalidArgumentException('Destino Gmail inválido.');
                self::id($destination);if($source!==null)self::id($source);
                $current=$this->message($token,$id);
                if(in_array('TRASH',$current['labelIds']??[],true))$this->http->request('POST','https://gmail.googleapis.com/gmail/v1/users/me/'.$path.'/untrash',$headers,null,true);
                $add=[$destination];
                // In all-mail views, moving removes Inbox only; other labels remain.
                $origin=$source==='TRASH'?'INBOX':($source??(in_array('SPAM',$current['labelIds']??[],true)?'SPAM':'INBOX'));
                if($origin!==$destination&&(in_array($origin,['INBOX','SPAM'],true)||str_starts_with($origin,'Label_')))$remove=[$origin];
            }
            $this->http->request('POST','https://gmail.googleapis.com/gmail/v1/users/me/'.$path.'/modify',$headers,json_encode(['addLabelIds'=>$add,'removeLabelIds'=>$remove],JSON_THROW_ON_ERROR),true);
        }
        try{return $this->message($token,$id);}catch(Throwable $e){throw new MailProviderException('unknown');}
    }
}
