<?php
declare(strict_types=1);

final class MailSyncService
{
    public function __construct(private MailMessageService $store, private MailOAuthService $oauth, private MailProviderInterface $api) {}
    public function run(int $account): array
    {
        $this->store->account($account);$connection=$this->store->connection($account);$lease=bin2hex(random_bytes(16));
        $state=$this->store->transaction($account,function()use($account,$lease):array{
            $this->store->pdo->prepare('INSERT INTO core_mail_sync_states(owner_user_id,account_id) VALUES(?,?) ON DUPLICATE KEY UPDATE account_id=account_id')->execute([$this->store->owner,$account]);
            $s=$this->store->pdo->prepare("UPDATE core_mail_sync_states SET lease_token=?,lease_until=UTC_TIMESTAMP()+INTERVAL 90 SECOND,last_attempt_at=UTC_TIMESTAMP() WHERE account_id=? AND owner_user_id=? AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP()) AND (retry_after IS NULL OR retry_after<=UTC_TIMESTAMP()) AND (last_attempt_at IS NULL OR last_attempt_at<UTC_TIMESTAMP()-INTERVAL 5 SECOND)");$s->execute([$lease,$account,$this->store->owner]);
            if($s->rowCount()!==1)throw new DomainException('A atualização está em andamento ou aguardando o provedor.');
            $s=$this->store->pdo->prepare('SELECT * FROM core_mail_sync_states WHERE account_id=? AND owner_user_id=?');$s->execute([$account,$this->store->owner]);return $s->fetch(PDO::FETCH_ASSOC);
        });
        $context='mail:'.$this->store->owner.':'.$account.':sync';
        try{
            $token=$this->oauth->token($account);$labels=$this->api->folders($token);
            foreach($labels['labels']??[]as$label){
                if(!is_string($label['id']??null)||strlen($label['id'])>255)continue;
                $s=$this->store->pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)');$s->execute([$this->store->owner,$account,$label['id'],MailMessageService::clean((string)($label['name']??$label['id']),255)]);
            }
            $poll=($this->api->capabilities()['sync']??'history')==='poll';
            $cursor=$state['cursor_envelope']?$this->store->cipher->open($state['cursor_envelope'],$context):[];
            if(!$cursor){$profile=$this->api->profile($token);$cursor=['mode'=>$poll?'poll':'bootstrap','history'=>(string)($profile['historyId']??''),'page'=>'','queue'=>[],'deleted'=>[],'cutoff'=>time()-30*86400,'loaded'=>false];}
            if(empty($cursor['loaded'])){
                $response=$cursor['mode']!=='history'?$this->api->listMessages($token,'after:'.$cursor['cutoff'],(string)$cursor['page']):$this->api->history($token,(string)$cursor['history'],(string)$cursor['page']);
                $ids=[];$deleted=[];
                if($cursor['mode']!=='history'){foreach($response['messages']??[]as$message)$ids[]=(string)$message['id'];}
                else{
                    foreach($response['history']??[]as$event){
                        foreach($event['messages']??[]as$message)$ids[]=(string)$message['id'];
                        foreach(['messagesAdded','labelsAdded','labelsRemoved']as$type)foreach($event[$type]??[]as$item)$ids[]=(string)$item['message']['id'];
                        foreach($event['messagesDeleted']??[]as$item)$deleted[]=(string)$item['message']['id'];
                    }
                }
                if(count($ids)>1000)throw new MailProviderException('cursor');
                $cursor['queue']=array_values(array_unique($ids));$cursor['deleted']=array_values(array_unique($deleted));$cursor['following']=(string)($response['nextPageToken']??'');$cursor['latest']=(string)($response['historyId']??$cursor['history']);$cursor['loaded']=true;
            }
            foreach($cursor['deleted']as$id)$this->store->missing($account,$id);$cursor['deleted']=[];
            $processed=0;$deadline=microtime(true)+18;
            while($cursor['queue']&&$processed<10&&microtime(true)<$deadline){
                $remote=$cursor['queue'][0];
                try{$message=$this->api->message($token,$remote);$date=(int)floor((float)($message['internalDate']??0)/1000);
                    if($date>=$cursor['cutoff'])$this->store->transaction($account,function()use($connection,$account,$message):void{if((int)$this->store->connection($account)['generation']!==(int)$connection['generation'])throw new MailProviderException('auth');$this->store->remember($account,$message);});
                }catch(MailProviderException $e){if($e->kind==='missing')$this->store->missing($account,$remote);else throw $e;}
                array_shift($cursor['queue']);$processed++;
            }
            $complete=false;
            if(!$cursor['queue']){
                $cursor['page']=$cursor['following'];$cursor['loaded']=false;
                if($cursor['page']===''){$cursor['mode']=$poll?'poll':'history';$cursor['history']=$cursor['latest'];if($poll)$cursor['cutoff']=time()-30*86400;$complete=true;}
            }
            if((int)$this->store->connection($account)['generation']!==(int)$connection['generation'])throw new MailProviderException('auth');
            $s=$this->store->pdo->prepare('UPDATE core_mail_sync_states SET cursor_envelope=?,lease_token=NULL,lease_until=NULL,failure_code=NULL,retry_after=NULL,last_synced_at=IF(?,UTC_TIMESTAMP(),last_synced_at) WHERE account_id=? AND owner_user_id=? AND lease_token=?');$s->execute([$this->store->cipher->seal($cursor,$context),(int)$complete,$account,$this->store->owner,$lease]);
            if($s->rowCount()!==1)throw new DomainException('A atualização foi interrompida. Tente novamente.');
            $this->store->audit($account,'sync.completed');return ['processed'=>$processed,'complete'=>$complete];
        }catch(Throwable $e){
            $kind=$e instanceof MailProviderException?$e->kind:'local';$pause=$e instanceof MailProviderException&&$e->kind==='quota'?max(60,$e->retryAfter):5;
            $s=$this->store->pdo->prepare('UPDATE core_mail_sync_states SET lease_token=NULL,lease_until=NULL,failure_code=?,retry_after=UTC_TIMESTAMP()+INTERVAL ? SECOND,cursor_envelope=IF(?,NULL,cursor_envelope) WHERE account_id=? AND owner_user_id=? AND lease_token=?');$s->execute([$kind,$pause,(int)($kind==='cursor'),$account,$this->store->owner,$lease]);
            $this->store->audit($account,'sync.failed','failure');throw $e;
        }
    }
}
