<?php
declare(strict_types=1);

/** Read-only, resumable verification of one cached Zoho Trash message per batch. */
final class MailTrashReconciler
{
    public function __construct(private MailMessageService $store, private ZohoMailProvider $api) {}

    public function run(int $account, string $token, array $folders, array $state, int $generation, float $deadline): array
    {
        $this->store->account($account);
        $ids=[];$trash=[];
        foreach($folders['labels']??[] as $folder){
            $id=$folder['id']??null;
            if(!is_string($id)||!preg_match('/^[0-9]{1,40}$/D',$id))throw new MailProviderException('provider');
            $ids[]=$id;
            if(strtolower((string)($folder['type']??''))==='trash')$trash[]=$id;
        }
        $ids=array_values(array_unique($ids));sort($ids,SORT_STRING);
        // An incomplete/ambiguous inventory is never evidence of deletion.
        if(count($trash)!==1||count($ids)>1000)return [];
        $fingerprint=hash('sha256',json_encode([$ids,$trash,$generation],JSON_THROW_ON_ERROR));
        if(($state['inventory']??'')!==$fingerprint||($state['started']??0)<time()-3600)$state=['after'=>(int)($state['after']??0),'inventory'=>$fingerprint,'started'=>time()];
        $query=$this->store->pdo->prepare('SELECT m.id,m.remote_id FROM core_mail_messages m WHERE m.owner_user_id=? AND m.account_id=? AND m.remote_missing=0 AND m.id>? AND EXISTS (SELECT 1 FROM core_mail_message_folders mf JOIN core_mail_folders f ON f.id=mf.folder_id AND f.owner_user_id=mf.owner_user_id AND f.account_id=mf.account_id WHERE mf.message_id=m.id AND mf.owner_user_id=m.owner_user_id AND mf.account_id=m.account_id AND f.remote_id=?) ORDER BY m.id LIMIT 1');
        $query->execute([$this->store->owner,$account,(int)($state['after']??0),$trash[0]]);$row=$query->fetch(PDO::FETCH_ASSOC);
        if(!$row){$state['after']=0;unset($state['message'],$state['next']);return $state;}
        if(($state['message']??null)!==(int)$row['id']){$state['message']=(int)$row['id'];$state['next']=0;$state['started']=time();}
        // First check Trash; only explicit missing responses allow other probes.
        $order=[$trash[0],...array_values(array_diff($ids,$trash))];
        for($calls=0;$calls<3&&microtime(true)<$deadline;$calls++){
            $offset=(int)($state['next']??0);
            if($offset<0||$offset>=count($order))throw new MailProviderException('cursor');
            $message=null;
            try{$message=$this->api->messageInFolder($token,(string)$row['remote_id'],$order[$offset]);}
            catch(MailProviderException $e){if($e->kind!=='missing')throw $e;}
            if($message!==null||$offset===count($order)-1){
                $this->store->transaction($account,function()use($account,$generation,$query,$row,$trash,$message):void{
                    if((int)$this->store->connection($account)['generation']!==$generation)throw new MailProviderException('auth');
                    // A local move/restore between batches supersedes this probe.
                    $query->execute([$this->store->owner,$account,(int)$row['id']-1,$trash[0]]);$current=$query->fetch(PDO::FETCH_ASSOC);
                    if(!$current||(int)$current['id']!==(int)$row['id'])return;
                    if($message!==null)$this->store->remember($account,$message);
                    else $this->store->missing($account,(string)$row['remote_id']);
                });
                $state['after']=(int)$row['id'];unset($state['message'],$state['next']);return $state;
            }
            $state['next']=$offset+1;
        }
        return $state;
    }
}
