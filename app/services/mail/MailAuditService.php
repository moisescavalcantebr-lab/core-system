<?php
declare(strict_types=1);

final class MailAuditService
{
    public function __construct(private PDO $pdo) {}

    public function record(int $owner, ?int $account, string $action, string $result = 'success'): void
    {
        if (!in_array($action, ['message.marked','preferences.updated','account.created', 'account.updated', 'account.enabled', 'account.disabled', 'category.created', 'category.updated', 'oauth.started', 'oauth.connected', 'oauth.disconnected', 'oauth.refresh_failed', 'sync.completed', 'sync.failed', 'draft.saved', 'send.accepted', 'send.failed', 'send.unknown', 'send.confirmed'], true) || !in_array($result,['success','failure','unknown'],true)) {
            throw new InvalidArgumentException('Ação de auditoria inválida.');
        }
        $stmt = $this->pdo->prepare("INSERT INTO core_mail_audit_events (owner_user_id, account_id, action, result, correlation_id, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())");
        $stmt->execute([$owner, $account, $action, $result, bin2hex(random_bytes(16))]);
    }
}
