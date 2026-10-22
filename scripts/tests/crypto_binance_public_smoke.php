<?php
declare(strict_types=1);
// Optional public smoke test: never uses account credentials.
require dirname(__DIR__,2).'/modules/crypto_binance/web/admin/crypto_binance/service.php';
$snapshot=(new CryptoBinanceReader())->read(['BTCUSDT']);
echo json_encode(['mode'=>$snapshot['mode'],'status'=>$snapshot['status'],'checks'=>$snapshot['checks'],'markets'=>count($snapshot['markets'])], JSON_PRETTY_PRINT).PHP_EOL;
