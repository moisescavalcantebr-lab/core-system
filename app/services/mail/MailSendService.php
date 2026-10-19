<?php
declare(strict_types=1);

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

final class MailSendService
{
    public function __construct(private MailMessageService $store,private MailOAuthService $oauth,private MailProviderInterface $api,private MailAttachmentService $attachments) {}
    public static function recipients(string $raw): array
    {
        if(strlen($raw)>4096||preg_match('/[\r\n\x00-\x1F\x7F]/',$raw))throw new InvalidArgumentException('Separe os destinatários por vírgula, sem quebras de linha.');
        $result=[];foreach(explode(',',$raw)as$value){$value=trim($value);if($value==='')continue;if(!filter_var($value,FILTER_VALIDATE_EMAIL)||strlen($value)>254)throw new InvalidArgumentException('Informe apenas endereços de e-mail válidos nos destinatários.');$result[strtolower($value)]=$value;}
        if(count($result)>20)throw new InvalidArgumentException('Use no máximo 20 destinatários.');return array_values($result);
    }
    public function draft(int $id): array
    {
        $s=$this->store->pdo->prepare('SELECT * FROM core_mail_drafts WHERE id=? AND owner_user_id=?');$s->execute([$id,$this->store->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new OutOfBoundsException('Rascunho indisponível.');$this->store->account((int)$row['account_id']);$row['content']=$this->store->cipher->open($row['content_envelope'],'mail:'.$this->store->owner.':'.$row['account_id'].':draft:'.$id);return $row;
    }
    public function drafts(int $account): array
    {$this->store->account($account);$s=$this->store->pdo->prepare("SELECT id,updated_at FROM core_mail_drafts WHERE account_id=? AND owner_user_id=? AND status='draft' ORDER BY updated_at DESC LIMIT 30");$s->execute([$account,$this->store->owner]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function reply(int $id): array
    {
        $message=$this->store->message($id);$remote=$this->api->message($this->oauth->token((int)$message['account_id']),$message['remote_id'],true);$headers=[];
        foreach($remote['payload']['headers']??[]as$header)$headers[strtolower((string)($header['name']??''))]=(string)($header['value']??'');
        $from=$headers['reply-to']??$headers['from']??'';
        if(preg_match('/<([^<>]+)>/',$from,$m))$from=$m[1];
        if(!filter_var(trim($from),FILTER_VALIDATE_EMAIL))throw new DomainException('Não foi possível identificar um destinatário seguro para a resposta.');
        $account=$this->store->account((int)$message['account_id']);
        if(strtolower(trim($from))===strtolower($account['email']))throw new DomainException('Esta mensagem foi enviada por você. Use uma nova composição para escrever ao destinatário.');
        if($this->api instanceof ZohoMailProvider){
            if($this->api->ownsAddress(trim($from)))throw new DomainException('O destinatário da resposta pertence à sua própria caixa Zoho. Use uma nova composição para escolher outro destinatário.');
            $subject=MailMessageService::clean($message['subject'],255);if(!preg_match('/^re:/i',$subject))$subject='Re: '.$subject;
            return ['account_id'=>(int)$message['account_id'],'reply_message_id'=>$id,'to'=>[trim($from)],'cc'=>[],'bcc'=>[],'subject'=>mb_substr($subject,0,255),'body'=>'','reply_headers'=>['remote_id'=>$message['remote_id']]];
        }
        $messageId=trim($headers['message-id']??'');if(!preg_match('/^<[^<>\s\x00-\x1F]{1,250}>$/D',$messageId))throw new DomainException('A mensagem não tem uma referência segura para resposta.');
        $subject=MailMessageService::clean(MailMessageService::decodeHeader($headers['subject']??$message['subject']),255);if(!preg_match('/^re:/i',$subject))$subject='Re: '.$subject;
        $references=trim($headers['references']??'');$refs=[];if(strlen($references)<=2048&&preg_match_all('/<[^<>\s\x00-\x1F]{1,250}>/',$references,$matches))$refs=array_slice($matches[0],-8);$refs[]=$messageId;
        return ['account_id'=>(int)$message['account_id'],'reply_message_id'=>$id,'to'=>[trim($from)],'cc'=>[],'bcc'=>[],'subject'=>mb_substr($subject,0,255),'body'=>'','reply_headers'=>['in_reply_to'=>$messageId,'references'=>array_values(array_unique($refs)),'thread_id'=>$message['remote_thread_id']]];
    }
    public function saveDraft(array $data): int
    {
        $allowed=['id','revision','account_id','reply_message_id','to','cc','bcc','subject','body'];if(array_diff(array_keys($data),$allowed))throw new InvalidArgumentException('Campos de composição inválidos.');
        $id=mailCenterId($data['id']??0,true);$account=mailCenterId($data['account_id']??null);$this->store->account($account);$this->store->connection($account);
        foreach(['to','cc','bcc','subject','body']as$field)if(!is_string($data[$field]??''))throw new InvalidArgumentException('Campo inválido.');
        $content=['to'=>self::recipients($data['to']??''),'cc'=>self::recipients($data['cc']??''),'bcc'=>self::recipients($data['bcc']??''),'subject'=>trim($data['subject']??''),'body'=>$data['body']??''];
        if(array_sum(array_map('count',[$content['to'],$content['cc'],$content['bcc']]))>20||mb_strlen($content['subject'])>255||preg_match('/[\r\n\x00-\x1F\x7F]/',$content['subject'])||strlen($content['body'])>65536||!mb_check_encoding($content['body'],'UTF-8'))throw new InvalidArgumentException('Confira assunto, destinatários e conteúdo (até 64 KB).');
        $reply=mailCenterId($data['reply_message_id']??0,true);
        if($id){$old=$this->draft($id);if((int)$old['account_id']!==$account||(int)($old['reply_message_id']??0)!==$reply)throw new DomainException('A conta e a origem da resposta não podem ser trocadas no rascunho.');$replyData=$old['content'];}
        else $replyData=$reply?$this->reply($reply):[];
        if($reply){if((int)($replyData['account_id']??$account)!==$account||$content['subject']!==$replyData['subject'])throw new DomainException('Mantenha a conta e o assunto original ao responder.');$content['reply_headers']=$replyData['reply_headers'];}
        return $this->store->transaction($account,function()use($data,$id,$account,$reply,$content):int{
            $this->store->connection($account);$s=$this->store->pdo->prepare('SELECT id FROM core_mail_identities WHERE account_id=? AND owner_user_id=? AND can_send=1 ORDER BY id LIMIT 1');$s->execute([$account,$this->store->owner]);$identity=(int)$s->fetchColumn();if(!$identity)throw new DomainException('Identidade de envio indisponível.');
            $key=bin2hex(random_bytes(32));
            if($id){$current=$this->draft($id);if($current['status']!=='draft'||(int)$current['revision']!==mailCenterId($data['revision']??null))throw new DomainException('O rascunho mudou. Atualize a página.');}
            else{$s=$this->store->pdo->prepare("INSERT INTO core_mail_drafts(owner_user_id,account_id,identity_id,reply_message_id,content_envelope,submission_key,created_at,updated_at) VALUES(?,?,?,?,'pending',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$this->store->owner,$account,$identity,$reply?:null,$key]);$id=(int)$this->store->pdo->lastInsertId();}
            $encrypted=$this->store->cipher->seal($content,'mail:'.$this->store->owner.':'.$account.':draft:'.$id);
            $s=$this->store->pdo->prepare('UPDATE core_mail_drafts SET content_envelope=?,submission_key=?,revision=revision+?,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?');$s->execute([$encrypted,$key,isset($current)?1:0,$id,$this->store->owner]);$this->store->audit($account,'draft.saved');return $id;
        });
    }
    public function outbox(int $id): array
    {$s=$this->store->pdo->prepare('SELECT id,account_id,draft_id,status,created_at,updated_at,remote_message_id,failure_code,mime_message_id,lease_until FROM core_mail_outbox WHERE id=? AND owner_user_id=?');$s->execute([$id,$this->store->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new OutOfBoundsException('Envio indisponível.');$this->store->account((int)$row['account_id'],false);return $row;}
    public function sent(int $account): array
    {$this->store->account($account);$s=$this->store->pdo->prepare('SELECT id,status,created_at FROM core_mail_outbox WHERE account_id=? AND owner_user_id=? ORDER BY id DESC LIMIT 30');$s->execute([$account,$this->store->owner]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    public function submit(int $draftId,int $revision,string $key,bool $confirmed): array
    {
        if(!$confirmed||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new InvalidArgumentException('Confirme a conta remetente e o envio.');
        $draft=$this->draft($draftId);$account=(int)$draft['account_id'];
        $s=$this->store->pdo->prepare('SELECT id FROM core_mail_outbox WHERE owner_user_id=? AND idempotency_key=?');$s->execute([$this->store->owner,$key]);$existing=$s->fetchColumn();if($existing){$row=$this->outbox((int)$existing);if((int)$row['draft_id']!==$draftId)throw new DomainException('Identificador de envio inválido.');return $row;}
        $token=$this->oauth->token($account);$connection=$this->store->connection($account);
        $id=$this->store->transaction($account,function()use($draftId,$revision,$key,$account,$connection):int{
            $s=$this->store->pdo->prepare('SELECT id,draft_id FROM core_mail_outbox WHERE owner_user_id=? AND idempotency_key=?');$s->execute([$this->store->owner,$key]);$duplicate=$s->fetch(PDO::FETCH_ASSOC);if($duplicate){if((int)$duplicate['draft_id']!==$draftId)throw new DomainException('Identificador de envio inválido.');return (int)$duplicate['id'];}
            $draft=$this->draft($draftId);if((int)$draft['revision']!==$revision||$draft['status']!=='draft'||!hash_equals($draft['submission_key'],$key))throw new DomainException('O rascunho mudou ou já foi submetido. Atualize a página.');
            if((int)$this->store->connection($account)['generation']!==(int)$connection['generation'])throw new MailProviderException('auth');
            $s=$this->store->pdo->prepare('SELECT COUNT(*) FROM core_mail_outbox WHERE owner_user_id=? AND created_at>=UTC_TIMESTAMP()-INTERVAL 1 MINUTE');$s->execute([$this->store->owner]);if((int)$s->fetchColumn()>=5)throw new DomainException('Limite de 5 envios por minuto. Aguarde.');
            $s=$this->store->pdo->prepare('SELECT COUNT(*) FROM core_mail_outbox WHERE owner_user_id=? AND created_at>=UTC_TIMESTAMP()-INTERVAL 1 DAY');$s->execute([$this->store->owner]);if((int)$s->fetchColumn()>=50)throw new DomainException('O piloto permite até 50 envios por dia.');
            $s=$this->store->pdo->prepare('SELECT * FROM core_mail_identities WHERE id=? AND account_id=? AND owner_user_id=? AND can_send=1');$s->execute([$draft['identity_id'],$account,$this->store->owner]);$identity=$s->fetch(PDO::FETCH_ASSOC);if(!$identity||!$draft['content']['to'])throw new DomainException('Confira remetente e destinatário principal.');
            $messageId='mail-center-'.bin2hex(random_bytes(16)).'@'.explode('@',$identity['email'])[1];
            $snapshot=$draft['content']+['from'=>$identity['email'],'from_name'=>$identity['display_name'],'attachments'=>$this->attachments->forDraft($draftId)];
            foreach($snapshot['attachments']as$attachment)$this->attachments->localBytes($attachment);
            $s=$this->store->pdo->prepare("INSERT INTO core_mail_outbox(owner_user_id,account_id,identity_id,draft_id,idempotency_key,snapshot_envelope,mime_message_id,status,created_at,updated_at) VALUES(?,?,?,?,?,'pending',?,'queued',UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$this->store->owner,$account,$identity['id'],$draftId,$key,$messageId]);$id=(int)$this->store->pdo->lastInsertId();
            $envelope=$this->store->cipher->seal($snapshot,'mail:'.$this->store->owner.':'.$account.':outbox:'.$id);
            $this->store->pdo->prepare('UPDATE core_mail_outbox SET snapshot_envelope=? WHERE id=?')->execute([$envelope,$id]);
            $this->store->pdo->prepare("UPDATE core_mail_drafts SET status='submitted' WHERE id=? AND owner_user_id=?")->execute([$draftId,$this->store->owner]);return $id;
        });
        return $this->deliver($id,$token);
    }
    private function deliver(int $id,string $token): array
    {
        $row=$this->outbox($id);$account=(int)$row['account_id'];
        $s=$this->store->pdo->prepare("UPDATE core_mail_outbox SET status='sending',attempts=attempts+1,lease_until=UTC_TIMESTAMP()+INTERVAL 90 SECOND,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=? AND status='queued'");$s->execute([$id,$this->store->owner]);if($s->rowCount()!==1)return $this->outbox($id);
        $started=false;
        try{
            $this->store->connection($account);$s=$this->store->pdo->prepare('SELECT snapshot_envelope FROM core_mail_outbox WHERE id=? AND owner_user_id=?');$s->execute([$id,$this->store->owner]);$snapshot=$this->store->cipher->open($s->fetchColumn(),'mail:'.$this->store->owner.':'.$account.':outbox:'.$id);
            $email=(new Email())->from(new Address($snapshot['from'],$snapshot['from_name']))->to(...$snapshot['to'])->subject($snapshot['subject'])->text($snapshot['body']);
            if($snapshot['cc'])$email->cc(...$snapshot['cc']);if($snapshot['bcc'])$email->bcc(...$snapshot['bcc']);
            $email->getHeaders()->addIdHeader('Message-ID',$row['mime_message_id']);
            if(isset($snapshot['reply_headers']['in_reply_to'])){$headers=$snapshot['reply_headers'];$email->getHeaders()->addIdHeader('In-Reply-To',trim($headers['in_reply_to'],'<>'));$email->getHeaders()->addIdHeader('References',array_map(static fn($v)=>trim($v,'<>'),$headers['references']));}
            foreach($snapshot['attachments']as$attachment)$email->attach($this->attachments->localBytes($attachment),$attachment['filename'],$attachment['mime_type']);
            $raw=$email->toString();if(strlen($raw)>10*1024*1024)throw new DomainException('A mensagem completa excede o limite do piloto.');
            if($this->api instanceof ZohoMailProvider){foreach($snapshot['attachments']as&$attachment)$attachment['bytes']=$this->attachments->localBytes($attachment);unset($attachment);}
            $started=true;$result=$this->api instanceof ZohoMailProvider?$this->api->sendSnapshot($token,$snapshot):$this->api->send($token,$raw,$snapshot['reply_headers']['thread_id']??null);$remote=(string)($result['id']??'');
            $outlookAccepted=$this->api instanceof OutlookMailProvider&&($result['accepted']??false)===true;
            if(!$outlookAccepted&&!preg_match('/^[a-zA-Z0-9_-]{1,255}$/D',$remote))throw new MailProviderException('unknown');
            $this->store->pdo->prepare("UPDATE core_mail_outbox SET status='accepted',remote_message_id=?,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?")->execute([$remote,$id,$this->store->owner]);$this->store->audit($account,'send.accepted');
            try{$this->reconcile($id);}catch(Throwable $ignored){}return $this->outbox($id);
        }catch(Throwable $e){
            $unknown=$started&&(!($e instanceof MailProviderException)||in_array($e->kind,['unknown','network'],true));$state=$unknown?'unknown':'failed';
            $this->store->transaction($account,function()use($state,$e,$id,$unknown,$account,$row):void{
                $this->store->pdo->prepare('UPDATE core_mail_outbox SET status=?,failure_code=?,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?')->execute([$state,$e instanceof MailProviderException?$e->kind:'local',$id,$this->store->owner]);
                if(!$unknown)$this->store->pdo->prepare("UPDATE core_mail_drafts SET status='draft',revision=revision+1,submission_key=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?")->execute([bin2hex(random_bytes(32)),$row['draft_id'],$this->store->owner]);
                $this->store->audit($account,$unknown?'send.unknown':'send.failed',$unknown?'unknown':'failure');
            });return $this->outbox($id);
        }
    }
    public function reconcile(int $id): array
    {
        $row=$this->outbox($id);if(!in_array($row['status'],['accepted','unknown','sending'],true))return $row;
        if($row['status']==='sending'&&strtotime($row['lease_until'].' UTC')>time())throw new DomainException('O envio ainda está em andamento.');
        $account=(int)$row['account_id'];$token=$this->oauth->token($account);$remote=$row['remote_message_id'];
        if($this->api instanceof OutlookMailProvider){
            $found=$this->api->findSent($token,$row['mime_message_id']);if($found===null)return $row;
            $this->store->pdo->prepare("UPDATE core_mail_outbox SET status='confirmed',remote_message_id=?,failure_code=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?")->execute([$found,$id,$this->store->owner]);$this->store->audit($account,'send.confirmed');return $this->outbox($id);
        }
        if($this->api instanceof ZohoMailProvider){
            if(!$remote||!$this->api->confirmedSent($token,$remote))return $row;
            $this->store->pdo->prepare("UPDATE core_mail_outbox SET status='confirmed',failure_code=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?")->execute([$id,$this->store->owner]);$this->store->audit($account,'send.confirmed');return $this->outbox($id);
        }
        if(!$remote){$results=$this->api->listMessages($token,'in:sent rfc822msgid:'.$row['mime_message_id']);$messages=$results['messages']??[];if(count($messages)!==1)return $row;$remote=(string)$messages[0]['id'];}
        $message=$this->api->message($token,$remote);if(!in_array('SENT',$message['labelIds']??[],true))return $row;
        $this->store->pdo->prepare("UPDATE core_mail_outbox SET status='confirmed',remote_message_id=?,failure_code=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND owner_user_id=?")->execute([$remote,$id,$this->store->owner]);$this->store->audit($account,'send.confirmed');return $this->outbox($id);
    }
}
