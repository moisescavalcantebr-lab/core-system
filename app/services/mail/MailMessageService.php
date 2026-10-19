<?php
declare(strict_types=1);

final class MailMessageService
{
    public function __construct(public readonly PDO $pdo, public readonly int $owner, public readonly MailTokenCipher $cipher, private readonly ?int $boundAccount = null)
    { new MailAccountService($pdo,$owner); $pdo->exec("SET time_zone = '+00:00'"); }

    public function account(int $id, bool $active = true): array
    {
        if($this->boundAccount!==null && $id!==$this->boundAccount)throw new OutOfBoundsException('Conta indisponível neste contexto.');
        $s=$this->pdo->prepare('SELECT a.*,p.code AS provider_code FROM core_mail_accounts a JOIN core_mail_providers p ON p.id=a.provider_id WHERE a.id=? AND a.owner_user_id=?');$s->execute([$id,$this->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new OutOfBoundsException('Conta indisponível.');
        if(!in_array($row['provider_code'],['gmail','zoho','outlook'],true))throw new DomainException('Integração deste provedor ainda não disponível.');
        if($active && !(int)$row['is_active'])throw new DomainException('O cadastro está desativado.');
        return $row;
    }
    public function transaction(int $account, callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare("SELECT id FROM core_users WHERE id=? AND status=1 AND role IN ('ADMIN','SUPER_ADMIN') FOR UPDATE");$s->execute([$this->owner]);if(!$s->fetchColumn())throw new OutOfBoundsException('Recurso indisponível.');
            $s=$this->pdo->prepare('SELECT id FROM core_mail_accounts WHERE id=? AND owner_user_id=? FOR UPDATE');$s->execute([$account,$this->owner]);if(!$s->fetchColumn())throw new OutOfBoundsException('Conta indisponível.');
            $result=$fn();$this->pdo->commit();return $result;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function connection(int $account): array
    {
        $this->account($account);$s=$this->pdo->prepare("SELECT * FROM core_mail_connections WHERE account_id=? AND owner_user_id=? AND status='connected' AND token_envelope IS NOT NULL");$s->execute([$account,$this->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new DomainException('Conecte esta conta para continuar.');return $row;
    }
    public function message(int $id): array
    {
        $s=$this->pdo->prepare('SELECT * FROM core_mail_messages WHERE id=? AND owner_user_id=?');$s->execute([$id,$this->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new OutOfBoundsException('Mensagem indisponível.');$this->account((int)$row['account_id']);return $row;
    }
    public function folders(int $account): array
    {
        $this->account($account);$s=$this->pdo->prepare('SELECT id,name,remote_id FROM core_mail_folders WHERE account_id=? AND owner_user_id=? ORDER BY name');$s->execute([$account,$this->owner]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listing(int $account, string $search = '', int $page = 1, int $folder = 0, string $mark = 'all'): array
    {
        $this->account($account);if(mb_strlen($search)>200||$page<1||$page>10000||$folder<0)throw new InvalidArgumentException('Filtro inválido.');
        $localColumn=mailCenterMarkColumn($mark);
        $trash=$this->standardFolderIds('trash',$account);
        $sql='SELECT m.*,COALESCE(lm.is_favorite,0) AS is_favorite,COALESCE(lm.is_flagged,0) AS is_flagged,COALESCE(lm.is_pinned,0) AS is_pinned,mc.color AS account_color,mc.name AS account_function FROM core_mail_messages m JOIN core_mail_accounts a ON a.id=m.account_id AND a.owner_user_id=m.owner_user_id LEFT JOIN core_mail_categories mc ON mc.id=a.category_id AND mc.owner_user_id=a.owner_user_id LEFT JOIN core_mail_message_marks lm ON lm.message_id=m.id AND lm.account_id=m.account_id AND lm.owner_user_id=m.owner_user_id WHERE m.account_id=? AND m.owner_user_id=? AND m.remote_missing=0';$args=[$account,$this->owner];
        if($search!==''){$sql.=' AND (m.subject LIKE ? OR m.sender LIKE ?)';$term='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$sql=str_replace('LIKE ?','LIKE ? ESCAPE \'!\'',$sql);array_push($args,$term,$term);}
        if($folder){$sql.=' AND EXISTS (SELECT 1 FROM core_mail_message_folders mf WHERE mf.message_id=m.id AND mf.folder_id=? AND mf.owner_user_id=m.owner_user_id)';$args[]=$folder;}
        if(!$folder||!in_array($folder,$trash,true))$sql.=self::excludeTrash($trash);
        if($localColumn!==null)$sql.=' AND lm.'.$localColumn.'=1';
        $sql.=' ORDER BY COALESCE(lm.is_pinned,0) DESC,m.received_at DESC,m.id DESC LIMIT 26 OFFSET '.(($page-1)*25);$s=$this->pdo->prepare($sql);$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);return ['rows'=>array_slice($rows,0,25),'more'=>count($rows)>25];
    }
    public static function clean(string $text, int $max): string
    {return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u',' ',mb_convert_encoding($text,'UTF-8','UTF-8'))??'',0,$max);}

    public function nextMessage(int $id,string $search='',int $folder=0,string $mark='all'): ?int
    {
        $current=$this->message($id);$account=(int)$current['account_id'];
        if(mb_strlen($search)>200||$folder<0)throw new InvalidArgumentException('Filtro inválido.');
        $column=mailCenterMarkColumn($mark);
        $s=$this->pdo->prepare('SELECT COALESCE(MAX(is_pinned),0) FROM core_mail_message_marks WHERE message_id=? AND account_id=? AND owner_user_id=?');$s->execute([$id,$account,$this->owner]);$pinned=(int)$s->fetchColumn();
        $sql='SELECT m.id FROM core_mail_messages m LEFT JOIN core_mail_message_marks lm ON lm.message_id=m.id AND lm.account_id=m.account_id AND lm.owner_user_id=m.owner_user_id WHERE m.account_id=? AND m.owner_user_id=? AND m.remote_missing=0 AND (COALESCE(lm.is_pinned,0)<? OR (COALESCE(lm.is_pinned,0)=? AND (m.received_at<? OR (m.received_at=? AND m.id<?))))';
        $args=[$account,$this->owner,$pinned,$pinned,$current['received_at'],$current['received_at'],$id];
        if($search!==''){$sql.=" AND (m.subject LIKE ? ESCAPE '!' OR m.sender LIKE ? ESCAPE '!')";$term='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($args,$term,$term);}
        if($folder){$sql.=' AND EXISTS(SELECT 1 FROM core_mail_message_folders mf WHERE mf.message_id=m.id AND mf.account_id=m.account_id AND mf.owner_user_id=m.owner_user_id AND mf.folder_id=?)';$args[]=$folder;}
        $trash=$this->standardFolderIds('trash',$account);
        if(!$folder||!in_array($folder,$trash,true))$sql.=self::excludeTrash($trash);
        if($column!==null)$sql.=' AND lm.'.$column.'=1';
        $sql.=' ORDER BY COALESCE(lm.is_pinned,0) DESC,m.received_at DESC,m.id DESC LIMIT 1';
        $s=$this->pdo->prepare($sql);$s->execute($args);$next=$s->fetchColumn();return $next===false?null:(int)$next;
    }

    public static function decodeHeader(string $text): string
    {
        // Providers also return decoded UTF-8. Decode only RFC 2047 encoded words.
        $previous=mb_internal_encoding();mb_internal_encoding('UTF-8');
        try{return preg_replace_callback('/=\?[^?\s]+\?[bq]\?[^?]*\?=(?:[ \t\r\n]+=\?[^?\s]+\?[bq]\?[^?]*\?=)*/i',static fn($m)=>mb_decode_mimeheader($m[0]),$text)??$text;}
        finally{mb_internal_encoding($previous);}
    }

    private static function metadataHeaders(array $remote): array
    {
        $headers=[];foreach($remote['payload']['headers']??[]as$h){$name=strtolower((string)($h['name']??''));if(in_array($name,['subject','from','to','cc'],true))$headers[$name]=self::clean(self::decodeHeader((string)($h['value']??'')),1024);}return $headers;
    }

    public function refreshHeaders(int $id,array $remote): array
    {
        $message=$this->message($id);
        if(($remote['id']??null)!==$message['remote_id'])throw new MailProviderException('provider');
        $headers=self::metadataHeaders($remote);
        // Metadata only: preserve local marks, folder links, sync cursors and send state.
        $s=$this->pdo->prepare('UPDATE core_mail_messages SET subject=?,sender=?,recipients=? WHERE id=? AND account_id=? AND owner_user_id=?');
        $s->execute([mb_substr($headers['subject']??'',0,512),mb_substr($headers['from']??'',0,512),json_encode(['to'=>$headers['to']??'','cc'=>$headers['cc']??''],JSON_THROW_ON_ERROR),$id,$message['account_id'],$this->owner]);
        return $this->message($id);
    }



    private static function standardFolder(string $provider,string $remote,string $name): ?string
    {return mailCenterFolderKind($provider,$remote,$name);}

    private function standardFolderIds(string $kind,?int $account=null): array
    {
        $sql='SELECT f.id,f.remote_id,f.name,p.code FROM core_mail_folders f JOIN core_mail_accounts a ON a.id=f.account_id AND a.owner_user_id=f.owner_user_id JOIN core_mail_providers p ON p.id=a.provider_id WHERE f.owner_user_id=?';$args=[$this->owner];
        if($account!==null){$sql.=' AND f.account_id=?';$args[]=$account;}
        $s=$this->pdo->prepare($sql);$s->execute($args);$ids=[];
        foreach($s->fetchAll(PDO::FETCH_ASSOC)as$row)if(self::standardFolder($row['code'],$row['remote_id'],$row['name'])===$kind)$ids[]=(int)$row['id'];
        return $ids;
    }

    private static function excludeTrash(array $ids): string
    {
        if(!$ids)return '';
        // IDs come from owner-scoped database rows, never from request input.
        return ' AND NOT EXISTS (SELECT 1 FROM core_mail_message_folders mt WHERE mt.message_id=m.id AND mt.account_id=m.account_id AND mt.owner_user_id=m.owner_user_id AND mt.folder_id IN ('.implode(',',array_map('intval',$ids)).'))';
    }
    public function unifiedListing(string $search='', int $page=1, string $folder='all', string $mark='all'): array
    {
        if($this->boundAccount!==null)throw new DomainException('Use o contexto unificado para esta consulta.');
        if(mb_strlen($search)>200||$page<1||$page>10000)throw new InvalidArgumentException('Filtro inválido.');
        $localColumn=mailCenterMarkColumn($mark);
        if($folder!=='all'&&!isset(mailCenterUnifiedFolders()[$folder]))throw new InvalidArgumentException('Pasta unificada inválida.');
        $sql="SELECT m.*,COALESCE(lm.is_favorite,0) AS is_favorite,COALESCE(lm.is_flagged,0) AS is_flagged,COALESCE(lm.is_pinned,0) AS is_pinned,a.email AS account_email,a.label AS account_label,p.name AS provider_name,mc.color AS account_color,mc.name AS account_function FROM core_mail_messages m JOIN core_mail_accounts a ON a.id=m.account_id AND a.owner_user_id=m.owner_user_id JOIN core_mail_providers p ON p.id=a.provider_id JOIN core_mail_connections c ON c.account_id=a.id AND c.owner_user_id=a.owner_user_id LEFT JOIN core_mail_categories mc ON mc.id=a.category_id AND mc.owner_user_id=a.owner_user_id LEFT JOIN core_mail_message_marks lm ON lm.message_id=m.id AND lm.account_id=m.account_id AND lm.owner_user_id=m.owner_user_id WHERE m.owner_user_id=? AND a.is_active=1 AND a.connection_status='connected' AND c.status='connected' AND c.token_envelope IS NOT NULL AND m.remote_missing=0 AND p.code IN ('gmail','zoho','outlook')";$args=[$this->owner];
        if($search!==''){$sql.=" AND (m.subject LIKE ? ESCAPE '!' OR m.sender LIKE ? ESCAPE '!' OR a.email LIKE ? ESCAPE '!')";$term='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($args,$term,$term,$term);}
        if($folder!=='all'){
            $ids=$this->standardFolderIds($folder);
            if(!$ids)$sql.=' AND 1=0';
            else{$sql.=' AND EXISTS (SELECT 1 FROM core_mail_message_folders mf WHERE mf.message_id=m.id AND mf.owner_user_id=m.owner_user_id AND mf.account_id=m.account_id AND mf.folder_id IN ('.implode(',',array_fill(0,count($ids),'?')).'))';array_push($args,...$ids);}
        }
        if($folder!=='trash')$sql.=self::excludeTrash($this->standardFolderIds('trash'));
        if($localColumn!==null)$sql.=' AND lm.'.$localColumn.'=1';
        $sql.=' ORDER BY COALESCE(lm.is_pinned,0) DESC,m.received_at DESC,m.id DESC LIMIT 26 OFFSET '.(($page-1)*25);$s=$this->pdo->prepare($sql);$s->execute($args);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
        return ['rows'=>array_slice($rows,0,25),'more'=>count($rows)>25];
    }

    public function remember(int $account, array $remote): int
    {
        $this->account($account);$id=(string)($remote['id']??'');if(!preg_match('~^[a-zA-Z0-9_+=/-]{1,255}$~D',$id))throw new MailProviderException('provider');
        $headers=self::metadataHeaders($remote);
        $labels=array_slice(array_filter($remote['labelIds']??[],'is_string'),0,100);$seconds=(int)floor((float)($remote['internalDate']??0)/1000);if($seconds<1)throw new MailProviderException('provider');
        $s=$this->pdo->prepare('INSERT INTO core_mail_messages (owner_user_id,account_id,remote_id,remote_thread_id,subject,sender,recipients,received_at,is_unread,has_attachments,synced_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE subject=VALUES(subject),sender=VALUES(sender),recipients=VALUES(recipients),received_at=VALUES(received_at),is_unread=VALUES(is_unread),remote_missing=0,synced_at=UTC_TIMESTAMP()');
        $s->execute([$this->owner,$account,$id,self::clean((string)($remote['threadId']??''),255),mb_substr($headers['subject']??'',0,512),mb_substr($headers['from']??'',0,512),json_encode(['to'=>$headers['to']??'','cc'=>$headers['cc']??''],JSON_THROW_ON_ERROR),gmdate('Y-m-d H:i:s',$seconds),(int)in_array('UNREAD',$labels,true),(int)($remote['hasAttachment']??false)]);
        $s=$this->pdo->prepare('SELECT id FROM core_mail_messages WHERE account_id=? AND owner_user_id=? AND remote_id=?');$s->execute([$account,$this->owner,$id]);$local=(int)$s->fetchColumn();
        $this->pdo->prepare('DELETE FROM core_mail_message_folders WHERE message_id=? AND owner_user_id=?')->execute([$local,$this->owner]);
        foreach($labels as$label){$s=$this->pdo->prepare('INSERT INTO core_mail_message_folders(message_id,folder_id,account_id,owner_user_id) SELECT ?,id,account_id,owner_user_id FROM core_mail_folders WHERE account_id=? AND owner_user_id=? AND remote_id=?');$s->execute([$local,$account,$this->owner,$label]);}
        return $local;
    }
    public function missing(int $account, string $remote): void
    { $this->account($account);$this->pdo->prepare('UPDATE core_mail_messages SET remote_missing=1 WHERE account_id=? AND owner_user_id=? AND remote_id=?')->execute([$account,$this->owner,$remote]); }
    public function audit(int $account, string $action, string $result = 'success'): void
    {(new MailAuditService($this->pdo))->record($this->owner,$account,$action,$result);}
}
