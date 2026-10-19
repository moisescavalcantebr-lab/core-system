<?php
declare(strict_types=1);

final class MailRemoteMessageService
{
    public function __construct(private MailMessageService $store,private MailOAuthService $oauth,private MailProviderInterface $api,private bool $enabled=false) {}

    public static function scopes(string $provider): array
    {
        return match($provider){'gmail'=>['https://www.googleapis.com/auth/gmail.modify'],'zoho'=>['ZohoMail.messages.UPDATE','ZohoMail.messages.DELETE'],default=>[]};
    }

    public function available(int $account): bool
    {
        if(!$this->enabled||!($this->api instanceof MailMutableProviderInterface))return false;
        $box=$this->store->account($account);$required=self::scopes($box['provider_code']);
        if(!$required)return false;
        try{$granted=json_decode($this->store->connection($account)['scopes'],true,32,JSON_THROW_ON_ERROR);return is_array($granted)&&!array_diff($required,$granted);}catch(DomainException $e){return false;}
    }

    public static function destinationAllowed(string $provider,string $id,string $name): bool
    {
        if($provider==='gmail')return $id==='INBOX'||str_starts_with($id,'Label_');
        return $provider==='zoho'&&in_array(mailCenterFolderKind($provider,$id,$name),[null,'inbox'],true);
    }

    public function destinations(int $account): array
    {
        $box=$this->store->account($account);
        return array_values(array_filter($this->store->folders($account),static fn($f)=>self::destinationAllowed($box['provider_code'],$f['remote_id'],$f['name'])));
    }

    public function apply(int $id,string $action,int $destination=0,int $source=0,bool $confirmed=false): void
    {
        if(!in_array($action,['read','unread','move','trash'],true)||$destination<0||$source<0)throw new InvalidArgumentException('Ação inválida.');
        $message=$this->store->message($id);$account=(int)$message['account_id'];$box=$this->store->account($account);
        if(!$this->available($account))throw new DomainException('Atualize as permissões desta conta em Configurações antes de organizar mensagens.');
        if($action==='trash'&&!$confirmed)throw new DomainException('Confirme o envio da mensagem para a lixeira.');
        if(($action==='move')!==($destination>0)||($action!=='move'&&$source))throw new InvalidArgumentException('Destino inválido.');
        $localFolders=$this->store->folders($account);$byId=array_column($localFolders,null,'id');
        if($destination&&(!isset($byId[$destination])||!self::destinationAllowed($box['provider_code'],$byId[$destination]['remote_id'],$byId[$destination]['name'])))throw new OutOfBoundsException('Pasta de destino indisponível nesta conta.');
        if($source&&!isset($byId[$source]))throw new OutOfBoundsException('Pasta de origem indisponível nesta conta.');
        $connection=$this->store->connection($account);$lease=bin2hex(random_bytes(16));
        // Use the same lease as mailbox sync, preventing stale batches from overwriting actions.
        $this->store->transaction($account,function()use($account,$lease):void{
            $this->store->pdo->prepare('INSERT INTO core_mail_sync_states(owner_user_id,account_id) VALUES(?,?) ON DUPLICATE KEY UPDATE account_id=account_id')->execute([$this->store->owner,$account]);
            $s=$this->store->pdo->prepare('UPDATE core_mail_sync_states SET lease_token=?,lease_until=UTC_TIMESTAMP()+INTERVAL 90 SECOND WHERE owner_user_id=? AND account_id=? AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP()) AND (retry_after IS NULL OR retry_after<=UTC_TIMESTAMP())');$s->execute([$lease,$this->store->owner,$account]);
            if($s->rowCount()!==1)throw new DomainException('Aguarde a atualização desta caixa terminar antes de organizar.');
        });
        $attempted=false;
        try{
            $token=$this->oauth->token($account);
            // Refresh folder existence remotely; a cached folder can have been removed elsewhere.
            $remoteFolders=$this->api->folders($token);$remoteById=[];
            foreach($remoteFolders['labels']??[]as$f)if(is_string($f['id']??null))$remoteById[$f['id']]=$f;
            $dest=$destination?$byId[$destination]['remote_id']:null;$origin=$source?$byId[$source]['remote_id']:null;
            if($dest!==null&&(!isset($remoteById[$dest])||!self::destinationAllowed($box['provider_code'],$dest,(string)$remoteById[$dest]['name'])))throw new DomainException('A pasta de destino mudou no provedor. Atualize a caixa.');
            if($origin!==null&&!isset($remoteById[$origin]))throw new DomainException('A pasta de origem mudou no provedor. Atualize a caixa.');
            $fresh=$this->api->message($token,$message['remote_id']);
            if(($fresh['id']??null)!==$message['remote_id'])throw new MailProviderException('provider');
            if($origin!==null&&!in_array($origin,$fresh['labelIds']??[],true))throw new DomainException('A mensagem mudou de pasta. Atualize a caixa antes de mover.');
            // Never issue a second trash operation on an already trashed message.
            foreach($remoteById as$remoteId=>$f)if(mailCenterFolderKind($box['provider_code'],(string)$remoteId,(string)($f['name']??''))==='trash'&&in_array((string)$remoteId,$fresh['labelIds']??[],true)&&$action==='trash')throw new DomainException('Esta mensagem já está na lixeira.');
            $this->store->transaction($account,function()use($account,$connection,$lease,$token,$message,$action,$dest,$origin,$box,$remoteById,&$attempted):void{
                $this->assertContext($account,$connection,$lease);$attempted=true;
                $updated=$this->api->updateMessage($token,$message['remote_id'],$action,$dest,$origin);
                if(($updated['id']??null)!==$message['remote_id'])throw new MailProviderException('unknown');
                $labels=$updated['labelIds']??null;if(!is_array($labels))throw new MailProviderException('unknown');
                if(($action==='read'&&in_array('UNREAD',$labels,true))||($action==='unread'&&!in_array('UNREAD',$labels,true))||($action==='move'&&!in_array($dest,$labels,true)))throw new MailProviderException('unknown');
                if($action==='trash'){
                    $inTrash=false;foreach($remoteById as$remoteId=>$f)if(mailCenterFolderKind($box['provider_code'],(string)$remoteId,(string)($f['name']??''))==='trash'&&in_array((string)$remoteId,$labels,true))$inTrash=true;
                    if(!$inTrash)throw new MailProviderException('unknown');
                }
                $this->assertContext($account,$connection,$lease);$this->store->remember($account,$updated);$this->store->audit($account,'message.remote_updated');
            });
        }catch(Throwable $e){
            if($e instanceof MailProviderException&&$e->kind==='quota'){
                $pause=min(3600,max(60,$e->retryAfter));$s=$this->store->pdo->prepare("UPDATE core_mail_sync_states SET retry_after=UTC_TIMESTAMP()+INTERVAL ? SECOND,failure_code='quota' WHERE owner_user_id=? AND account_id=? AND lease_token=?");$s->execute([$pause,$this->store->owner,$account,$lease]);
            }
            // Some actions have multiple remote steps; after attempting a write,
            // do not turn any later failure into a blindly retryable operation.
            $uncertain=$attempted;
            $this->store->audit($account,'message.remote_updated',$uncertain?'unknown':'failure');
            if($uncertain)throw new DomainException('A ação pode ter sido aplicada. Atualize a caixa e confira no provedor antes de repetir.');
            throw $e;
        }finally{
            $this->store->pdo->prepare('UPDATE core_mail_sync_states SET lease_token=NULL,lease_until=NULL WHERE owner_user_id=? AND account_id=? AND lease_token=?')->execute([$this->store->owner,$account,$lease]);
        }
    }

    private function assertContext(int $account,array $connection,string $lease): void
    {
        if((int)$this->store->connection($account)['generation']!==(int)$connection['generation'])throw new MailProviderException('auth');
        $s=$this->store->pdo->prepare('SELECT COUNT(*) FROM core_mail_sync_states WHERE owner_user_id=? AND account_id=? AND lease_token=? AND lease_until>UTC_TIMESTAMP()');$s->execute([$this->store->owner,$account,$lease]);
        if(!(int)$s->fetchColumn())throw new DomainException('A operação perdeu o contexto de atualização.');
    }
}
