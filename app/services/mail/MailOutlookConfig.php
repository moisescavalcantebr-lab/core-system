<?php
declare(strict_types=1);

final class MailOutlookConfig
{
    public const SCOPES=['offline_access','https://graph.microsoft.com/User.Read','https://graph.microsoft.com/Mail.Read','https://graph.microsoft.com/Mail.Send'];
    public static function validate(array $settings,string $canonical): array
    {
        if(($settings['redirect_uri']??'')!==$canonical.'/web/admin/central_contas/oauth_outlook_callback.php'
            ||!filter_var($canonical,FILTER_VALIDATE_URL)||parse_url($canonical,PHP_URL_SCHEME)!=='https'
            ||!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD',(string)($settings['client_id']??''))
            ||!is_string($settings['client_secret']??null)||strlen($settings['client_secret'])<8||strlen($settings['client_secret'])>1024
            ||!filter_var($settings['pilot_email']??'',FILTER_VALIDATE_EMAIL))throw new DomainException('Confira o aplicativo Microsoft, a caixa e o endereço de retorno HTTPS.');
        return [...$settings,'provider'=>'outlook'];
    }
}
