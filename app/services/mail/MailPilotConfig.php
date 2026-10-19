<?php
declare(strict_types=1);

final class MailPilotConfig
{
    public static function load(array $config, string $root): array
    {
        $path = $config['mail_center']['secrets_file'] ?? '';
        $real = is_string($path) && $path !== '' ? realpath($path) : false;
        $root = rtrim(str_replace('\\', '/', (string)realpath($root)), '/') . '/';
        if (!$real || !is_file($real) || !is_readable($real) || str_starts_with(strtolower(str_replace('\\', '/', $real)), strtolower($root)) || filesize($real) > 32768) {
            throw new DomainException('A configuração privada do Gmail ainda não está disponível.');
        }
        $data = json_decode((string)file_get_contents($real), true, 16, JSON_THROW_ON_ERROR);
        $google = $data['google'] ?? [];
        $canonical = rtrim((string)($config['app_url'] ?? ''), '/');
        $expected = $canonical . '/web/admin/central_contas/oauth_callback.php';
        if (!filter_var($canonical, FILTER_VALIDATE_URL) || parse_url($canonical, PHP_URL_SCHEME) !== 'https'
            || parse_url($canonical, PHP_URL_USER) !== null || parse_url($canonical, PHP_URL_QUERY) !== null || parse_url($canonical, PHP_URL_FRAGMENT) !== null
            || !empty(parse_url($canonical, PHP_URL_PATH)) || ($google['redirect_uri'] ?? '') !== $expected
            || !is_string($google['client_id'] ?? null) || !preg_match('/^[a-zA-Z0-9._-]+\.apps\.googleusercontent\.com$/D', $google['client_id'])
            || !is_string($google['client_secret'] ?? null) || strlen($google['client_secret']) < 8
            || !filter_var($google['pilot_email'] ?? '', FILTER_VALIDATE_EMAIL)
            || !preg_match('/@(gmail\.com|googlemail\.com)$/iD', $google['pilot_email'])
            || !is_string($data['active_key'] ?? null) || !is_array($data['keys'] ?? null)) {
            throw new DomainException('Confira a configuração privada e o endereço HTTPS da Central.');
        }
        new MailTokenCipher($data['keys'], $data['active_key']);
        $data['google']['pilot_email'] = strtolower($google['pilot_email']);
        return $data;
    }

    public static function forProvider(array $data, array $config, string $provider): array
    {
        if($provider==='gmail')return $data['google'];
        if($provider==='outlook'){
            if(!is_array($data['outlook']??null))throw new DomainException('O Outlook aguarda configuração privada do aplicativo Microsoft.');
            $outlook=MailOutlookConfig::validate($data['outlook'],rtrim((string)$config['app_url'],'/'));
            $outlook['pilot_email']=strtolower($outlook['pilot_email']);return $outlook;
        }
        if($provider!=='zoho'||!is_array($data['zoho']??null))throw new DomainException('O Zoho aguarda configuração privada do cliente OAuth.');
        $zoho=MailZohoConfig::validate($data['zoho'],rtrim((string)$config['app_url'],'/'));
        $zoho['pilot_email']=strtolower($zoho['pilot_email']);
        return $zoho;
    }

    public static function requireHttps(array $config, array $server): void
    {
        $secure = in_array(strtolower((string)($server['HTTPS'] ?? '')), ['on', '1'], true);
        $trusted = $config['mail_center']['trusted_proxy_ips'] ?? [];
        if (!$secure && is_array($trusted) && in_array($server['REMOTE_ADDR'] ?? '', $trusted, true)) {
            $secure = strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        }
        $canonical = parse_url((string)($config['app_url'] ?? ''));
        $host = strtolower((string)($server['HTTP_HOST'] ?? ''));
        $expectedHost = strtolower((string)($canonical['host'] ?? '')) . (isset($canonical['port']) ? ':' . $canonical['port'] : '');
        if (!$secure || ($canonical['scheme'] ?? '') !== 'https' || $host !== $expectedHost || session_get_cookie_params()['secure'] !== true) {
            throw new DomainException('Use o endereço HTTPS configurado para conectar ou operar caixas reais.');
        }
    }
}
