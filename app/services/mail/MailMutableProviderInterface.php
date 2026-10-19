<?php
declare(strict_types=1);

interface MailMutableProviderInterface
{
    /** Return freshly fetched metadata after the provider acknowledges the operation. */
    public function updateMessage(string $token,string $id,string $action,?string $destination=null,?string $source=null): array;
    /** Only for a message freshly confirmed to be in Trash; never retry blindly. */
    public function deleteMessagePermanently(string $token,string $id): void;
}
