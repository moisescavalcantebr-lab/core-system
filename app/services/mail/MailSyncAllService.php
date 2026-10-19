<?php
declare(strict_types=1);

/** One bounded batch per connected mailbox; no sending or automatic retries. */
final class MailSyncAllService
{
    public function __construct(private PDO $pdo,private int $owner,private Closure $syncAccount) {}

    public function run(): array
    {
        new MailAccountService($this->pdo,$this->owner);
        $lock='mail_sync_all:'.$this->owner;
        $s=$this->pdo->prepare('SELECT GET_LOCK(?,0)');$s->execute([$lock]);
        if((int)$s->fetchColumn()!==1)throw new DomainException('A sincronização de todas as caixas já está em andamento.');
        try{
            $s=$this->pdo->prepare("SELECT a.id FROM core_mail_accounts a JOIN core_mail_providers p ON p.id=a.provider_id JOIN core_mail_connections c ON c.account_id=a.id AND c.owner_user_id=a.owner_user_id WHERE a.owner_user_id=? AND a.is_active=1 AND a.connection_status='connected' AND c.status='connected' AND c.token_envelope IS NOT NULL AND p.is_enabled=1 AND p.code IN ('gmail','zoho','outlook') ORDER BY a.display_order,a.id");$s->execute([$this->owner]);
            $accounts=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
            $result=['total'=>count($accounts),'updated'=>0,'pending'=>0,'failed'=>0,'processed'=>0];
            foreach($accounts as$account){
                try{
                    $batch=($this->syncAccount)($account);
                    $result['updated']++;$result['processed']+=(int)$batch['processed'];
                    if(!$batch['complete'])$result['pending']++;
                }catch(Throwable $error){
                    // The per-account synchronizer retains its safe failure,
                    // cooldown and lease state. Never echo provider responses.
                    $result['failed']++;
                }
            }
            return $result;
        }finally{$s=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
}
