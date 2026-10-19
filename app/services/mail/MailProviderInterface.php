<?php
declare(strict_types=1);

interface MailProviderInterface
{
    public function capabilities(): array;
    public function profile(string $accessToken): array;
    public function folders(string $accessToken): array;
    public function listMessages(string $accessToken, string $query = '', string $page = '', string $label = ''): array;
    public function message(string $accessToken, string $id, bool $full = false): array;
    public function history(string $accessToken, string $historyId, string $page = ''): array;
    public function attachment(string $accessToken, string $messageId, string $attachmentId): array;
    public function send(string $accessToken, string $raw, ?string $threadId = null): array;
}
