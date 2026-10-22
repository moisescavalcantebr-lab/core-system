<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
require $root . '/app/bootstrap/bootstrap.php';
require $root . '/app/services/projects/ProjectProvisioner.php';
require $root . '/modules/crypto_binance/web/admin/crypto_binance/service.php';
if (!coreIsLocal() || !in_array($config['db']['host'], ['db','localhost','127.0.0.1'], true)) throw new RuntimeException('Teste somente local.');
function cbAssert(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
if (($argv[1] ?? '') === '--cleanup') {
    $file = $root . '/_deploy/crypto-local-test.json';
    $fixture = json_decode((string)file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
    $slug = $fixture['slug']; $database = $fixture['db_name'];
    cbAssert((bool)preg_match('/^cripto-check-[a-f0-9]{10}$/D', $slug) && $database === ProjectProvisioner::projectDatabaseName($slug), 'Alvo de limpeza invalido.');
    $pdo->prepare('DELETE FROM project_logs WHERE project_id=?')->execute([$fixture['project_id']]);
    $pdo->prepare('DELETE FROM projects WHERE id=? AND slug=?')->execute([$fixture['project_id'], $slug]);
    $db = $config['project_db'];
    $server = new PDO('mysql:host='.$db['host'].';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $server->exec('DROP DATABASE `'.$database.'`');
    $folder = realpath($root . '/projects/' . $slug);
    cbAssert($folder !== false && str_starts_with($folder, realpath($root . '/projects') . DIRECTORY_SEPARATOR), 'Pasta fora do diretorio de testes.');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($folder); unlink($file);
    echo "PASS: projeto e banco temporarios removidos.\n";
    exit;
}
cbAssert(!is_file($root.'/_deploy/crypto-local-test.json'), 'Existe teste anterior; limpe antes de iniciar outro.');
foreach (['BTCUSDT, BTCUSDC','btcusdt;btcusdt'] as $symbols) cbAssert(count(CryptoBinanceReader::symbols($symbols)) > 0, 'Simbolos validos');
foreach (['', '../secret', 'BTC/USDT', 'BTCUSDT,ETHUSDT,BNBUSDT,SOLUSDT'] as $symbols) {
    try { CryptoBinanceReader::symbols($symbols); throw new LogicException('Aceitou simbolos invalidos'); }
    catch (InvalidArgumentException $e) {}
}
$key = str_repeat('a', 32); $secret = str_repeat('b', 32); $calls = [];
$transport = static function (string $url, array $headers) use ($key, $secret, &$calls): array {
    $parts = parse_url($url); parse_str($parts['query'] ?? '', $query); $path = $parts['path'];
    cbAssert($parts['scheme'] === 'https' && $parts['host'] === 'api.binance.com', 'Host indevido');
    cbAssert(in_array($path, ['/api/v3/time','/api/v3/exchangeInfo','/api/v3/ticker/price','/api/v3/account','/api/v3/myTrades','/api/v3/openOrders'], true), 'Endpoint indevido');
    $calls[] = $path;
    if ($headers) {
        cbAssert($headers === ['X-MBX-APIKEY: '.$key], 'Cabecalho incorreto');
        $unsigned = explode('&signature=', $parts['query'])[0];
        cbAssert(hash_equals(hash_hmac('sha256',$unsigned,$secret), $query['signature']), 'Assinatura invalida');
    }
    $symbol = $query['symbol'] ?? 'BTCUSDT';
    $body = match ($path) {
        '/api/v3/time' => ['serverTime'=>1800000000000],
        '/api/v3/exchangeInfo' => ['symbols'=>[['symbol'=>$symbol,'baseAsset'=>'BTC','quoteAsset'=>'USDT','status'=>'TRADING']]],
        '/api/v3/ticker/price' => ['symbol'=>$symbol,'price'=>'60000.00000000'],
        '/api/v3/account' => ['balances'=>[['asset'=>'USDT','free'=>'10.00000000','locked'=>'0.00000000'],['asset'=>'BTC','free'=>'0.00000000','locked'=>'0.00000000']]],
        '/api/v3/openOrders' => [],
        '/api/v3/myTrades' => [['symbol'=>$symbol,'id'=>'999999999999999999','orderId'=>'123','price'=>'59000.12345678','qty'=>'0.00000001','quoteQty'=>'0.00059000','commission'=>'0.00000000001','commissionAsset'=>'BTC','time'=>1799999999999,'isBuyer'=>true]],
    };
    return [200, json_encode($body, JSON_THROW_ON_ERROR)];
};
$sample = (new CryptoBinanceReader($transport))->read(['BTCUSDT'], $key, $secret);
cbAssert($sample['status'] === 'partial' && $sample['checks']['dca_bots']['state'] === 'not_tested', 'Cobertura DCA indevida');
cbAssert(count($sample['balances']) === 1 && $sample['trades'][0]['price'] === '59000.12345678', 'Precisao ou saldos incorretos');
cbAssert(!str_contains(json_encode($sample),$key) && !str_contains(json_encode($sample),$secret), 'Segredo persistido');
$public = (new CryptoBinanceReader($transport))->read(['BTCUSDT']);
cbAssert($public['mode'] === 'public' && $public['checks']['balances']['state'] === 'not_tested', 'Consulta publica indevida');
$failed = (new CryptoBinanceReader(static fn()=>[451, '{"msg":"SECRET-DO-NOT-DISPLAY"}']))->read(['BTCUSDT']);
cbAssert($failed['status'] === 'failed' && !str_contains(json_encode($failed), 'SECRET-DO-NOT-DISPLAY'), 'Falha ou erro exposto');
$reader = new CryptoBinanceReader($transport);
try { (new ReflectionMethod($reader, 'request'))->invoke($reader, '/api/v3/order'); throw new LogicException('Endpoint de trade aceito'); }
catch (LogicException $e) { cbAssert($e->getMessage()==='Endpoint nao permitido.', 'Falha no bloqueio de trade'); }
$baseId = (int)$pdo->query("SELECT id FROM bases WHERE slug='cripto'")->fetchColumn();
$slug = 'cripto-check-'.bin2hex(random_bytes(5));
$fixture = ProjectProvisioner::createFreeProject($pdo, ['name'=>'Teste temporario Cripto','slug'=>$slug,'owner_name'=>'Teste local','owner_email'=>'cripto-check@example.invalid','base_id'=>$baseId]);
file_put_contents($root.'/_deploy/crypto-local-test.json', json_encode($fixture));
$db = require $root.'/projects/'.$slug.'/app/config/database.php';
$projectPdo = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$projectPath = $root.'/projects/'.$slug;
foreach (['crypto_dca_manager','crypto_binance'] as $module) {
    $manifest = json_decode((string)file_get_contents($root.'/modules/'.$module.'/module.json'), true);
    foreach ($manifest['database']['tables'] as $table) $projectPdo->query('SELECT * FROM `'.$table.'` LIMIT 0');
    foreach ($manifest['copy'] as $copy) {
        $source = $root.'/modules/'.$module.'/'.$copy['from'];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(),strlen($source)+1);
            cbAssert(hash_file('sha256',$file->getPathname())===hash_file('sha256',$projectPath.'/'.$copy['to'].'/'.$relative), 'Copia divergente: '.$relative);
        }
    }
}
$adminId=1; // Audit actor fixture; HTTP first access will create the actual administrator.
cryptoBinanceSaveRead($projectPdo, $sample, $adminId); cryptoBinanceSaveRead($projectPdo, $sample, $adminId);
cbAssert((int)$projectPdo->query('SELECT COUNT(*) FROM crypto_binance_trades')->fetchColumn()===1, 'Trade duplicado');
cbAssert(str_contains($projectPdo->query('SELECT payload FROM crypto_binance_trades')->fetchColumn(), '999999999999999999'), 'ID grande perdido');
cryptoBinanceSaveRead($projectPdo, CryptoBinanceReader::demo(), $adminId);
cbAssert((int)$projectPdo->query('SELECT COUNT(*) FROM crypto_binance_trades')->fetchColumn()===1, 'Demo misturada a dados reais');
cbAssert((int)$projectPdo->query('SELECT COUNT(*) FROM crypto_assets')->fetchColumn()===0, 'Ativos manuais alterados');
$projectPdo->exec((string)file_get_contents($root.'/modules/crypto_binance/database/schema.sql'));
cbAssert((int)$projectPdo->query('SELECT COUNT(*) FROM crypto_binance_trades')->fetchColumn()===1, 'Schema apagou dados');
$before=(int)$projectPdo->query('SELECT COUNT(*) FROM crypto_binance_reads')->fetchColumn();
$bad=$sample;$bad['trades'][0]['id']=str_repeat('x',100);
try {cryptoBinanceSaveRead($projectPdo,$bad,$adminId);throw new LogicException('Falha esperada');} catch(PDOException $e){}
cbAssert((int)$projectPdo->query('SELECT COUNT(*) FROM crypto_binance_reads')->fetchColumn()===$before, 'Transacao parcial');
$session=bin2hex(random_bytes(24));$csrf=bin2hex(random_bytes(32));
session_write_close(); session_id($session); session_start(); $session=session_id(); $coreAdmin=$pdo->query("SELECT id,role FROM core_users WHERE role IN ('ADMIN','SUPER_ADMIN') AND status=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
cbAssert((bool)$coreAdmin,'Administrador local necessario para teste do primeiro acesso');
$_SESSION=['core_user'=>$coreAdmin,'_csrf'=>$csrf]; session_write_close();
$sessionFile = (session_save_path() ?: sys_get_temp_dir()) . '/sess_' . $session;
if (function_exists('posix_geteuid') && posix_geteuid() === 0) { chown($sessionFile, 'www-data'); chgrp($sessionFile, 'www-data'); }
$fixture += ['session'=>$session,'csrf'=>$csrf];file_put_contents($root.'/_deploy/crypto-local-test.json',json_encode($fixture));
echo "PASS: provisao real local, schemas, copias, assinatura GET, erros, precisao, importacao idempotente, demonstracao isolada e rollback.\n";
echo "Fixture local pronta para teste HTTP; executar --cleanup ao concluir.\n";
