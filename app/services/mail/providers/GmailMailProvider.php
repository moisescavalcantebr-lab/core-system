<?php
declare(strict_types=1);

final class GmailMailProvider implements MailProviderInterface
{
    public function __construct(private MailHttpClient $http) {}
    public function capabilities(): array { return ['read'=>true,'send'=>true,'search'=>true,'attachments'=>true,'remote_modify'=>false]; }
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
}
