<?php
declare(strict_types=1);

final class MailAttachmentService
{
    public const MAX_BYTES=2097152;
    private const TYPES=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','text/plain'=>'txt','text/csv'=>'csv'];
    private string $directory;
    public function __construct(private MailMessageService $store,private MailOAuthService $oauth,private MailProviderInterface $api,string $directory,string $root)
    {
        $real=realpath($directory);$root=rtrim(str_replace('\\','/',(string)realpath($root)),'/').'/';
        if(!$real||!is_dir($real)||!is_writable($real)||str_starts_with(strtolower(str_replace('\\','/',$real)).'/',strtolower($root)))throw new DomainException('Armazenamento privado de anexos indisponível.');
        $this->directory=$real;
    }
    public static function filename(string $name): string
    { $clean=trim(preg_replace('/[^\pL\pN ._-]/u','_',mb_convert_encoding(basename(str_replace('\\','/',$name)),'UTF-8','UTF-8'))??'');return mb_substr($clean!==''?$clean:'anexo',0,180); }
    public function remoteDescriptors(array $message,array $parts): array
    {
        foreach($parts as$part){$partId=(string)($part['partId']??'');if(!preg_match('/^[0-9.]{1,80}$/D',$partId))continue;
            $s=$this->store->pdo->prepare('INSERT INTO core_mail_attachments(owner_user_id,account_id,message_id,part_id,remote_attachment_id,filename,mime_type,byte_size) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),mime_type=VALUES(mime_type),byte_size=VALUES(byte_size)');
            $s->execute([$this->store->owner,$message['account_id'],$message['id'],$partId,$part['body']['attachmentId']??null,self::filename((string)$part['filename']),MailMessageService::clean((string)($part['mimeType']??'application/octet-stream'),120),min(2147483647,max(0,(int)($part['body']['size']??0)))]);
        }
        $this->store->pdo->prepare('UPDATE core_mail_messages SET has_attachments=? WHERE id=? AND owner_user_id=?')->execute([(int)(bool)$parts,$message['id'],$this->store->owner]);
        $s=$this->store->pdo->prepare('SELECT id,filename,mime_type,byte_size FROM core_mail_attachments WHERE message_id=? AND owner_user_id=? ORDER BY id');$s->execute([$message['id'],$this->store->owner]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function upload(int $draft,array $file): int
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('Upload inválido.');
        if(filesize($file['tmp_name'])>self::MAX_BYTES)throw new InvalidArgumentException('Cada anexo deve ter até 2 MB.');
        return $this->storeBytes($draft,(string)($file['name']??'anexo'),(string)file_get_contents($file['tmp_name']));
    }
    public function storeBytes(int $draft,string $name,string $bytes): int
    {
        if(strlen($bytes)>self::MAX_BYTES||$bytes==='')throw new InvalidArgumentException('Anexo vazio ou acima de 2 MB.');
        $type=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);if(!isset(self::TYPES[$type]))throw new InvalidArgumentException('Use PDF, imagens JPG/PNG/WebP ou texto simples/CSV.');
        $name=self::filename($name);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if(!in_array($extension,[$type==='image/jpeg'?'jpeg':self::TYPES[$type],self::TYPES[$type]],true))throw new InvalidArgumentException('A extensão do anexo não corresponde ao conteúdo.');
        $s=$this->store->pdo->prepare("SELECT * FROM core_mail_drafts WHERE id=? AND owner_user_id=? AND status='draft'");$s->execute([$draft,$this->store->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new OutOfBoundsException('Rascunho indisponível.');
        $token=bin2hex(random_bytes(16));$path=$this->directory.'/'.$token.'.enc';
        try{return $this->store->transaction((int)$row['account_id'],function()use($draft,$row,$name,$type,$bytes,$token,$path):int{
            $s=$this->store->pdo->prepare("SELECT status FROM core_mail_drafts WHERE id=? AND owner_user_id=? FOR UPDATE");$s->execute([$draft,$this->store->owner]);if($s->fetchColumn()!=='draft')throw new DomainException('O rascunho já foi enviado para processamento.');
            $s=$this->store->pdo->prepare('SELECT COALESCE(SUM(byte_size),0) FROM core_mail_attachments WHERE draft_id=? AND owner_user_id=?');$s->execute([$draft,$this->store->owner]);if((int)$s->fetchColumn()+strlen($bytes)>6*1024*1024)throw new InvalidArgumentException('O total de anexos deve ser de até 6 MB.');
            $encrypted=$this->store->cipher->seal(['bytes'=>base64_encode($bytes)],'mail:'.$this->store->owner.':'.$row['account_id'].':file:'.$token);
            if(file_put_contents($path,$encrypted,LOCK_EX)===false)throw new DomainException('Não foi possível guardar o anexo.');chmod($path,0600);
            $s=$this->store->pdo->prepare('INSERT INTO core_mail_attachments(owner_user_id,account_id,draft_id,file_token,filename,mime_type,byte_size,expires_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP()+INTERVAL 24 HOUR)');$s->execute([$this->store->owner,$row['account_id'],$draft,$token,$name,$type,strlen($bytes)]);
            $attachmentId=(int)$this->store->pdo->lastInsertId();$this->store->pdo->prepare('UPDATE core_mail_drafts SET revision=revision+1,submission_key=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?')->execute([bin2hex(random_bytes(32)),$draft,$this->store->owner]);return $attachmentId;
        });}catch(Throwable $e){if(is_file($path))unlink($path);throw $e;}
    }
    public function forDraft(int $draft): array
    {$s=$this->store->pdo->prepare('SELECT * FROM core_mail_attachments WHERE draft_id=? AND owner_user_id=? ORDER BY id');$s->execute([$draft,$this->store->owner]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function remove(int $id,int $revision): int
    {
        $s=$this->store->pdo->prepare('SELECT * FROM core_mail_attachments WHERE id=? AND owner_user_id=? AND draft_id IS NOT NULL');$s->execute([$id,$this->store->owner]);$file=$s->fetch(PDO::FETCH_ASSOC);if(!$file)throw new OutOfBoundsException('Anexo indisponível.');
        $draft=(int)$file['draft_id'];$this->store->transaction((int)$file['account_id'],function()use($id,$draft,$revision):void{
            $s=$this->store->pdo->prepare("SELECT 1 FROM core_mail_drafts WHERE id=? AND owner_user_id=? AND status='draft' AND revision=? FOR UPDATE");$s->execute([$draft,$this->store->owner,$revision]);if(!$s->fetchColumn())throw new DomainException('O rascunho mudou ou já foi submetido.');
            $this->store->pdo->prepare('DELETE FROM core_mail_attachments WHERE id=? AND owner_user_id=?')->execute([$id,$this->store->owner]);$this->store->pdo->prepare('UPDATE core_mail_drafts SET revision=revision+1,submission_key=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?')->execute([bin2hex(random_bytes(32)),$draft,$this->store->owner]);
        });
        if(preg_match('/^[a-f0-9]{32}$/D',(string)$file['file_token'])){$path=$this->directory.'/'.$file['file_token'].'.enc';if(is_file($path)&&!is_link($path))unlink($path);}return $draft;
    }
    public function localBytes(array $attachment): string
    {
        if((int)$attachment['owner_user_id']!==$this->store->owner||!preg_match('/^[a-f0-9]{32}$/D',(string)$attachment['file_token']))throw new OutOfBoundsException('Anexo indisponível.');
        $path=$this->directory.'/'.$attachment['file_token'].'.enc';if(!is_file($path)||is_link($path)||filesize($path)>5*1024*1024||strtotime($attachment['expires_at'].' UTC')<time())throw new DomainException('Anexo expirado. Anexe o arquivo novamente.');
        $value=$this->store->cipher->open((string)file_get_contents($path),'mail:'.$this->store->owner.':'.$attachment['account_id'].':file:'.$attachment['file_token']);$bytes=base64_decode($value['bytes']??'',true);
        if($bytes===false||strlen($bytes)!==(int)$attachment['byte_size'])throw new DomainException('Não foi possível validar o anexo.');return $bytes;
    }
    public function download(int $id): array
    {
        $s=$this->store->pdo->prepare('SELECT * FROM core_mail_attachments WHERE id=? AND owner_user_id=?');$s->execute([$id,$this->store->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new OutOfBoundsException('Anexo indisponível.');$this->store->account((int)$row['account_id']);
        if((int)$row['byte_size']>self::MAX_BYTES)throw new DomainException('Este anexo excede 2 MB. Abra-o diretamente no provedor original.');
        if($row['draft_id'])$bytes=$this->localBytes($row);
        else{
            $message=$this->store->message((int)$row['message_id']);$token=$this->oauth->token((int)$row['account_id']);
            if($row['remote_attachment_id'])$remote=$this->api->attachment($token,$message['remote_id'],$row['remote_attachment_id']);
            else{$parts=MailHtmlRenderer::parts($this->api->message($token,$message['remote_id'],true)['payload']??[]);$remote=[];foreach($parts['attachments']as$part)if(($part['partId']??'')===$row['part_id'])$remote=$part['body']??[];}
            $bytes=MailHtmlRenderer::decode((string)($remote['data']??''),self::MAX_BYTES);
        }
        return ['filename'=>self::filename($row['filename']),'type'=>'application/octet-stream','bytes'=>$bytes];
    }
}
