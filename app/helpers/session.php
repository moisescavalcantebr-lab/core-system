<?php
declare(strict_types=1);

/** Shared session policy. Do not trust client-supplied proxy headers. */
function coreConfigureSession(array $config): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $secure = array_key_exists('session_secure', $config['app'] ?? [])
        ? $config['app']['session_secure'] === true
        : strtolower((string)parse_url((string)($config['app_url'] ?? ''), PHP_URL_SCHEME)) === 'https';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
}
