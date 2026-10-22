<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
requireProjectRole(['ADMIN']);
if (!is_file(PROJECT_PATH . '/modules/crypto_binance/module.json') || !is_file(PROJECT_PATH . '/modules/crypto_dca_manager/module.json')) {
    http_response_code(404);
    exit('Addon indisponivel neste projeto.');
}
$coreEnvFile = dirname(PROJECT_PATH, 2) . '/env/env.production.php';
$localConfig = is_file($coreEnvFile) ? require $coreEnvFile : [];
$environment = strtolower(trim((string)($localConfig['app']['environment'] ?? getenv('CORE_ENV') ?: '')));
$host = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
$isLocal = $environment !== ''
    ? in_array($environment, ['local', 'development', 'laboratory', 'lab'], true)
    : in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
unset($localConfig);
if (!$isLocal) {
    http_response_code(403);
    exit('Esta versao da leitura Binance esta disponivel apenas no laboratorio local.');
}
require_once __DIR__ . '/service.php';
function cryptoBinanceEscape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cryptoBinanceState(string $state): string {
    return ['confirmed'=>'Consultado', 'partial'=>'Amostra parcial', 'failed'=>'Falhou', 'not_tested'=>'Nao validado', 'demo'=>'Demonstracao'][$state] ?? 'Nao validado';
}
function cryptoBinanceLabel(string $check): string {
    foreach (['market_'=>'Mercado: ', 'orders_'=>'Ordens abertas: ', 'trades_'=>'Negociacoes: '] as $prefix=>$label) {
        if (str_starts_with($check, $prefix)) return $label . substr($check, strlen($prefix));
    }
    return ['connection'=>'Conexao', 'balances'=>'Saldos Spot', 'dca_bots'=>'Identificacao dos bots DCA', 'dca_cycles'=>'Ciclos dos bots DCA', 'dca_reserves'=>'Reservas dos bots DCA'][$check] ?? $check;
}
try {
    $pdo->query('SELECT id FROM crypto_binance_reads LIMIT 0');
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) !== 1146) throw $e;
    $pdo->exec((string)file_get_contents(PROJECT_PATH . '/modules/crypto_binance/database/schema.sql'));
}
