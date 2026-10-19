<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!isset($database,$pdo,$cipher,$accounts,$providers))exit(1);
final class MailPriorityFixtureApi implements MailProviderInterface
{
    public array $fetched=[];public int $recentCalls=0;public bool $poll=false;public bool $fail=false;
    private PilotApi $inner;
    public function __construct(){$this->inner=new PilotApi();}
    public function capabilities():array{return ['read'=>true,'send'=>true,'sync'=>$this->poll?'poll':'history'];}
    public function profile(string $accessToken):array{return ['emailAddress'=>'priority@gmail.com','historyId'=>'100'];}
    public function folders(string $accessToken):array{return $this->inner->folders($accessToken);}
    public function listMessages(string $accessToken,string $query='',string $page='',string $label=''):array{$this->recentCalls++;if($this->fail)throw new MailProviderException('network');return ['messages'=>[['id'=>'priority_new'],['id'=>'priority_cached']]];}
    public function message(string $accessToken,string $id,bool $full=false):array{$this->fetched[]=$id;$message=$this->inner->message($accessToken,$id,$full);if($id==='priority_old')$message['internalDate']=(string)((time()-100*86400)*1000);return $message;}
    public function history(string $accessToken,string $historyId,string $page=''):array{throw new RuntimeException('Loaded historical page was refetched or skipped');}
    public function attachment(string $accessToken,string $messageId,string $attachmentId):array{return [];}
    public function send(string $accessToken,string $raw,?string $threadId=null):array{throw new RuntimeException('Sync attempted send');}
}
$priorityStart=$checks;$pdo->exec('UPDATE core_mail_audit_events SET created_at=UTC_TIMESTAMP()-INTERVAL 1 HOUR');
$priorityAccount=$accounts->save(['provider_id'=>$providers['gmail'],'email'=>'priority@gmail.com','label'=>'Priority fixture']);
$priorityStore=new MailMessageService($pdo,1,$cipher,$priorityAccount);$priorityApi=new MailPriorityFixtureApi();
$priorityOauth=new RemoteFixtureOAuth($priorityStore,$priorityApi,[],new PilotHttp());$prioritySync=new MailSyncService($priorityStore,$priorityOauth,$priorityApi);
$pdo->prepare('INSERT INTO core_mail_connections(owner_user_id,account_id,remote_identity,scopes,token_envelope) VALUES(1,?,?,?,?)')->execute([$priorityAccount,'priority@gmail.com','[]','synthetic']);
foreach($priorityApi->folders('synthetic')['labels']as$f)$pdo->prepare('INSERT INTO core_mail_folders(owner_user_id,account_id,remote_id,name) VALUES(1,?,?,?)')->execute([$priorityAccount,$f['id'],$f['name']]);
$priorityStore->remember($priorityAccount,$priorityApi->message('synthetic','priority_cached'));
$priorityContext='mail:1:'.$priorityAccount.':sync';
$priorityBase=['mode'=>'history','history'=>'100','page'=>'historical-page','following'=>'following-page','queue'=>['priority_old'],'deleted'=>[],'loaded'=>true,'latest'=>'200','cutoff'=>time()-30*86400];
$priorityState=static function(array $cursor)use($pdo,$priorityAccount,$priorityContext,$cipher):void{$pdo->prepare('INSERT INTO core_mail_sync_states(owner_user_id,account_id,cursor_envelope) VALUES(1,?,?) ON DUPLICATE KEY UPDATE cursor_envelope=VALUES(cursor_envelope),last_attempt_at=NULL,retry_after=NULL,lease_token=NULL,lease_until=NULL')->execute([$priorityAccount,$cipher->seal($cursor,$priorityContext)]);};
$priorityRead=static function()use($pdo,$priorityAccount,$priorityContext,$cipher):array{$s=$pdo->prepare('SELECT cursor_envelope FROM core_mail_sync_states WHERE owner_user_id=1 AND account_id=?');$s->execute([$priorityAccount]);return $cipher->open($s->fetchColumn(),$priorityContext);};
$priorityState($priorityBase);$priorityApi->fetched=[];$result=$prioritySync->run($priorityAccount);
pilotExpect($priorityApi->fetched===['priority_new','priority_old'],'Recent missing mail did not precede historical backlog, or cached mail was refetched');
pilotExpect(count($priorityStore->listing($priorityAccount)['rows'])===2,'Recent mail absent before draining historical pages');
$cursorAfter=$priorityRead();pilotExpect(!$result['complete']&&$cursorAfter['page']==='following-page'&&$cursorAfter['history']==='100','Priority import skipped historical checkpoint');
pilotExpect($result['processed']===2&&$priorityApi->recentCalls===1,'Priority pass exceeded bounded work or made repeated queries');
// Duplicated IDs in the historical queue must not cause two metadata fetches.
$pdo->prepare('DELETE FROM core_mail_messages WHERE account_id=? AND remote_id=?')->execute([$priorityAccount,'priority_new']);
$priorityState([...$priorityBase,'queue'=>['priority_new','priority_old']]);$priorityApi->fetched=[];$prioritySync->run($priorityAccount);pilotExpect($priorityApi->fetched===['priority_new','priority_old'],'Priority duplicated historical queue entries');
// A failed probe cannot advance the historical cursor or erase cached metadata.
$priorityState($priorityBase);$priorityApi->fail=true;pilotReject(fn()=>$prioritySync->run($priorityAccount),'Failed priority probe was ignored');$priorityApi->fail=false;
pilotExpect($priorityRead()===$priorityBase,'Failed probe lost historical checkpoint');
pilotExpect(count($priorityStore->listing($priorityAccount)['rows'])===2,'Failed probe changed cached metadata');
// Polling providers retain their existing bounded polling behavior.
$priorityApi->poll=true;$priorityState([...$priorityBase,'mode'=>'poll']);$callsBefore=$priorityApi->recentCalls;$priorityApi->fetched=[];$prioritySync->run($priorityAccount);
pilotExpect($priorityApi->recentCalls===$callsBefore&&$priorityApi->fetched===['priority_old'],'Priority pass changed polling-provider behavior');
echo 'Prioridade de sincronização: '.($checks-$priorityStart).' verificações sintéticas adicionais aprovadas.'.PHP_EOL;
