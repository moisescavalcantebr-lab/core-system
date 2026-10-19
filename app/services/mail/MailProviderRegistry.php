<?php
declare(strict_types=1);
final class MailProviderRegistry
{
    public static function forCode(string $code, ?MailHttpClient $http = null, array $settings = [], ?Closure $folderLookup = null): MailProviderInterface
    {
        $http??=new MailHttpClient();
        return match($code){
            'gmail'=>new GmailMailProvider($http),
            'outlook'=>new OutlookMailProvider($http),
            'zoho'=>new ZohoMailProvider($http,(string)($settings['region']??''),(string)($settings['pilot_email']??''),$folderLookup),
            default=>throw new DomainException('Provedor ainda não disponível.'),
        };
    }
}
