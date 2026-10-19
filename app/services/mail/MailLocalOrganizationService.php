<?php
declare(strict_types=1);

final class MailLocalOrganizationService
{
    public function __construct(private PDO $pdo,private int $owner){new MailAccountService($pdo,$owner);}
    public function marks(int $message): array
    {
        $s=$this->pdo->prepare('SELECT m.id,m.account_id,COALESCE(l.is_favorite,0) AS is_favorite,COALESCE(l.is_flagged,0) AS is_flagged,COALESCE(l.is_pinned,0) AS is_pinned FROM core_mail_messages m JOIN core_mail_accounts a ON a.id=m.account_id AND a.owner_user_id=m.owner_user_id LEFT JOIN core_mail_message_marks l ON l.message_id=m.id AND l.account_id=m.account_id AND l.owner_user_id=m.owner_user_id WHERE m.id=? AND m.owner_user_id=? AND a.is_active=1');$s->execute([$message,$this->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)throw new OutOfBoundsException('Mensagem indisponível.');return $row;
    }
    public function setMark(int $message,string $field,int $value): array
    {
        $column=match($field){'favorite'=>'is_favorite','flagged'=>'is_flagged','pinned'=>'is_pinned',default=>throw new InvalidArgumentException('Marcação inválida.')};
        if(!in_array($value,[0,1],true))throw new InvalidArgumentException('Valor de marcação inválido.');
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare("SELECT id FROM core_users WHERE id=? AND status=1 AND role IN ('ADMIN','SUPER_ADMIN') FOR UPDATE");$s->execute([$this->owner]);if(!$s->fetchColumn())throw new OutOfBoundsException('Recurso indisponível.');
            $row=$this->marks($message);
            $s=$this->pdo->prepare('INSERT INTO core_mail_message_marks(message_id,account_id,owner_user_id,'.$column.') VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE '.$column.'=VALUES('.$column.')');$s->execute([$message,$row['account_id'],$this->owner,$value]);
            (new MailAuditService($this->pdo))->record($this->owner,(int)$row['account_id'],'message.marked');
            $this->pdo->commit();return $this->marks($message);
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function preferences(): array
    {
        $s=$this->pdo->prepare('SELECT highlight_color,reading_mode FROM core_mail_preferences WHERE owner_user_id=?');$s->execute([$this->owner]);$row=$s->fetch(PDO::FETCH_ASSOC);return $row?:['highlight_color'=>'#f59e0b','reading_mode'=>'panel'];
    }
    public function purgeOldUnmarkedMetadata(): int
    {
        new MailAccountService($this->pdo,$this->owner);
        $s=$this->pdo->prepare("DELETE FROM core_mail_messages WHERE owner_user_id=? AND received_at<UTC_TIMESTAMP()-INTERVAL 90 DAY AND NOT EXISTS(SELECT 1 FROM core_mail_drafts d WHERE d.reply_message_id=core_mail_messages.id) AND NOT EXISTS(SELECT 1 FROM core_mail_message_marks l WHERE l.message_id=core_mail_messages.id AND l.account_id=core_mail_messages.account_id AND l.owner_user_id=core_mail_messages.owner_user_id AND (l.is_favorite=1 OR l.is_flagged=1 OR l.is_pinned=1))");$s->execute([$this->owner]);return $s->rowCount();
    }
    public function savePreferences(mixed $color,mixed $readingMode=null): void
    {
        if(!is_string($color)||!preg_match('/^#[0-9a-fA-F]{6}$/D',$color))throw new InvalidArgumentException('Escolha uma cor válida.');
        if($readingMode!==null&&(!is_string($readingMode)||!in_array($readingMode,['panel','full'],true)))throw new InvalidArgumentException('Escolha um modo de leitura válido.');
        new MailAccountService($this->pdo,$this->owner);
        $s=$this->pdo->prepare('INSERT INTO core_mail_preferences(owner_user_id,highlight_color) VALUES(?,?) ON DUPLICATE KEY UPDATE highlight_color=VALUES(highlight_color)');$s->execute([$this->owner,strtolower($color)]);
        if($readingMode!==null){$s=$this->pdo->prepare('UPDATE core_mail_preferences SET reading_mode=? WHERE owner_user_id=?');$s->execute([$readingMode,$this->owner]);}
        (new MailAuditService($this->pdo))->record($this->owner,null,'preferences.updated');
    }
}
