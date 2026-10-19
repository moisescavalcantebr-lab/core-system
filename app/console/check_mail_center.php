<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/services/mail/MailSchemaMigration.php';
require dirname(__DIR__) . '/helpers/mail_center.php';
try {
    $config = require dirname(__DIR__, 2) . '/env/env.production.php';
    $db = $config['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo (new MailSchemaMigration($pdo))->migrate(false) . PHP_EOL;
    if (!mailCenterReady($pdo)) { throw new RuntimeException('schema'); }
    $owner = $config['mail_center']['owner_user_id'] ?? 0;
    if (($config['mail_center']['enabled'] ?? false) === true && mailCenterOwnerId($pdo, $config, ['core_user' => ['id' => $owner]]) === null) {
        throw new RuntimeException('owner');
    }
    $providers = $pdo->query('SELECT code FROM core_mail_providers ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if ($providers !== ['gmail','outlook','zoho']) { throw new RuntimeException('providers'); }
    echo 'OK: fundação, provedores e configuração.' . PHP_EOL;
    if(in_array('--organization',$argv,true))echo (new MailSchemaMigration($pdo))->migrateOrganization(false).PHP_EOL;
    if(in_array('--pilot',$argv,true)){
        require_once dirname(__DIR__).'/helpers/mail_pilot.php';
        echo (new MailSchemaMigration($pdo))->migratePilot(false).PHP_EOL;
        if(!defined('ROOT_PATH'))define('ROOT_PATH',dirname(__DIR__,2));if(!defined('APP_PATH'))define('APP_PATH',ROOT_PATH.'/app');
        $ready=mailPilotReady($pdo,$config);echo $ready?'Configuração do piloto disponível; confirmar HTTPS, consentimento e teste real.':'Código do piloto disponível; configuração privada/HTTPS/logs ainda pendente.';echo PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Central indisponível: confira migração e proprietário. Nenhum dado alterado.' . PHP_EOL);
    exit(1);
}
