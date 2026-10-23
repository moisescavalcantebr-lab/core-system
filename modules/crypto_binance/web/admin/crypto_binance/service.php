<?php
declare(strict_types=1);

/** Transport is deliberately limited to GET on these exact official endpoints. */
final class CryptoBinanceReader
{
    private const PUBLIC_PATHS = ['/api/v3/time', '/api/v3/exchangeInfo', '/api/v3/ticker/price'];
    private const PRIVATE_PATHS = ['/api/v3/account', '/api/v3/openOrders', '/api/v3/myTrades'];
    private float $started;
    private ?string $terminalCode = null;

    public function __construct(private $transport = null)
    {
        $this->started = microtime(true);
    }

    public static function symbols(string $input): array
    {
        $values = array_values(array_unique(preg_split('/[\s,;]+/', strtoupper(trim($input)), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        if (!$values || count($values) > 3) {
            throw new InvalidArgumentException('Informe de um a tres pares, como BTCUSDT e BTCUSDC.');
        }
        foreach ($values as $value) {
            if (!preg_match('/^[A-Z0-9]{5,24}$/D', $value)) {
                throw new InvalidArgumentException('Use o simbolo do par sem barras ou espacos: BTCUSDT.');
            }
        }
        return $values;
    }

    public static function errorLabel(string $code): string
    {
        return match ($code) {
            'network' => 'Nao foi possivel conectar a Binance. Tente novamente mais tarde.',
            'timeout' => 'Tempo da consulta esgotado. Os dados anteriores foram preservados.',
            'http_451', 'http_403' => 'A Binance recusou o acesso a partir desta conexao ou regiao.',
            'http_429', 'http_418' => 'Limite de consultas atingido. Aguarde antes de repetir.',
            'api_-2014', 'api_-2015', 'http_401' => 'Confira a chave de leitura, as permissoes e a restricao de IP na Binance.',
            'api_-1021' => 'A Binance recusou o horario da consulta. Atualize o relogio do computador e tente novamente.',
            'api_-1022' => 'A assinatura foi recusada. Confira a chave e o segredo HMAC.',
            'api_-1121', 'symbol' => 'Par indisponivel na Binance ou resposta incompatível.',
            'budget' => 'A consulta atingiu seu limite de tempo. A amostra ficou parcial.',
            default => 'Consulta indisponivel. Confira o codigo e tente novamente mais tarde.',
        };
    }

    private function request(string $path, array $params = [], #[SensitiveParameter] string $key = '', #[SensitiveParameter] string $secret = ''): array
    {
        $private = in_array($path, self::PRIVATE_PATHS, true);
        if (!$private && !in_array($path, self::PUBLIC_PATHS, true)) {
            throw new LogicException('Endpoint nao permitido.');
        }
        if ($this->terminalCode !== null) throw new RuntimeException($this->terminalCode);
        if (microtime(true) - $this->started > 40) {
            throw new RuntimeException('budget');
        }
        if ($private && ($key === '' || $secret === '')) {
            throw new LogicException('Credenciais ausentes.');
        }
        $headers = [];
        if ($private) {
            $params['recvWindow'] = 5000;
            $headers = ['X-MBX-APIKEY: ' . $key];
        }
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($private) {
            $query .= '&signature=' . hash_hmac('sha256', $query, $secret);
        }
        $url = 'https://api.binance.com' . $path . ($query !== '' ? '?' . $query : '');
        if ($this->transport !== null) {
            [$status, $body] = ($this->transport)($url, $headers);
        } else {
            $curl = curl_init($url);
            $body = '';
            curl_setopt_array($curl, [
                CURLOPT_HTTPGET => true, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_errno($curl);
            curl_close($curl);
            if ($ok === false) throw new RuntimeException($error === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network');
        }
        $data = json_decode($body, true, 64, JSON_BIGINT_AS_STRING);
        if ($status < 200 || $status >= 300) {
            if (in_array($status, [403, 451, 429, 418], true)) {
                $this->terminalCode = 'http_' . $status;
                throw new RuntimeException($this->terminalCode);
            }
            $code = is_array($data) && isset($data['code']) && is_numeric($data['code']) ? 'api_' . (int)$data['code'] : 'http_' . $status;
            throw new RuntimeException($code);
        }
        if (!is_array($data)) throw new RuntimeException('response');
        return $data;
    }

    /** The reader does not persist credentials, signed URLs, headers or raw errors. */
    public function read(array $symbols, #[SensitiveParameter] string $key = '', #[SensitiveParameter] string $secret = ''): array
    {
        $symbols = self::symbols(implode(',', $symbols));
        $private = $key !== '' || $secret !== '';
        if ($private && (!preg_match('/^[A-Za-z0-9]{16,256}$/D', $key) || !preg_match('/^[A-Za-z0-9]{16,256}$/D', $secret))) {
            throw new InvalidArgumentException('Informe uma chave e um segredo HMAC validos, ambos completos.');
        }
        $snapshot = [
            'mode' => $private ? 'account' : 'public',
            'source_id' => $private ? hash('sha256', $key) : 'public',
            'observed_at' => gmdate('c'), 'symbols' => $symbols,
            'markets' => [], 'balances' => [], 'orders' => [], 'trades' => [],
            'checks' => [], 'status' => 'failed',
        ];
        $clock = null;
        try {
            $response = $this->request('/api/v3/time');
            if (!isset($response['serverTime']) || !is_numeric($response['serverTime'])) throw new RuntimeException('response');
            $clock = (int)$response['serverTime'];
            $snapshot['window_start'] = gmdate('c', (int)(($clock - 86400000) / 1000));
            $snapshot['window_end'] = gmdate('c', (int)($clock / 1000));
            $snapshot['checks']['connection'] = ['state' => 'confirmed'];
        } catch (RuntimeException $e) {
            $snapshot['checks']['connection'] = ['state' => 'failed', 'code' => $e->getMessage()];
        }
        foreach ($symbols as $symbol) {
            try {
                $info = $this->request('/api/v3/exchangeInfo', ['symbol' => $symbol]);
                $item = $info['symbols'][0] ?? [];
                if (($item['symbol'] ?? '') !== $symbol || !isset($item['baseAsset'], $item['quoteAsset'], $item['status'])) throw new RuntimeException('symbol');
                $price = $this->request('/api/v3/ticker/price', ['symbol' => $symbol]);
                if (($price['symbol'] ?? '') !== $symbol || !isset($price['price']) || !is_numeric($price['price'])) throw new RuntimeException('response');
                $snapshot['markets'][] = [
                    'symbol' => $symbol, 'base' => (string)$item['baseAsset'], 'quote' => (string)$item['quoteAsset'],
                    'status' => (string)$item['status'], 'price' => (string)$price['price'],
                ];
                $snapshot['checks']['market_' . $symbol] = ['state' => 'confirmed'];
            } catch (RuntimeException $e) {
                $snapshot['checks']['market_' . $symbol] = ['state' => 'failed', 'code' => $e->getMessage()];
            }
        }
        if ($private && $clock !== null) {
            try {
                $accountClock = $this->request('/api/v3/time');
                if (!isset($accountClock['serverTime']) || !is_numeric($accountClock['serverTime'])) throw new RuntimeException('response');
                $response = $this->request('/api/v3/account', ['timestamp' => (int)$accountClock['serverTime']], $key, $secret);
                if (!isset($response['balances']) || !is_array($response['balances'])) throw new RuntimeException('response');
                foreach ($response['balances'] as $balance) {
                    if (!isset($balance['asset'], $balance['free'], $balance['locked'])) throw new RuntimeException('response');
                    if (preg_match('/[1-9]/', (string)$balance['free'] . (string)$balance['locked'])) {
                        $snapshot['balances'][] = array_intersect_key($balance, array_flip(['asset', 'free', 'locked']));
                    }
                }
                $snapshot['checks']['balances'] = ['state' => 'confirmed'];
            } catch (RuntimeException $e) {
                $snapshot['balances'] = [];
                $snapshot['checks']['balances'] = ['state' => 'failed', 'code' => $e->getMessage()];
            }
            foreach ($symbols as $symbol) {
                foreach (['orders' => '/api/v3/openOrders', 'trades' => '/api/v3/myTrades'] as $kind => $path) {
                    try {
                        // Refresh the signed timestamp instead of reusing a time before slow public calls.
                        $now = $this->request('/api/v3/time');
                        if (!isset($now['serverTime']) || !is_numeric($now['serverTime'])) throw new RuntimeException('response');
                        $params = ['symbol' => $symbol, 'timestamp' => (int)$now['serverTime']];
                        if ($kind === 'trades') $params += ['startTime' => $clock - 86400000, 'endTime' => $clock, 'limit' => 100];
                        $rows = $this->request($path, $params, $key, $secret);
                        if (!array_is_list($rows)) throw new RuntimeException('response');
                        $fields = $kind === 'trades'
                            ? ['symbol','id','orderId','price','qty','quoteQty','commission','commissionAsset','time','isBuyer']
                            : ['symbol','orderId','price','origQty','executedQty','status','type','side','time'];
                        $clean = [];
                        foreach ($rows as $row) {
                            if (!is_array($row) || ($row['symbol'] ?? '') !== $symbol || !isset($row[$kind === 'trades' ? 'id' : 'orderId'])) throw new RuntimeException('response');
                            $clean[] = array_intersect_key($row, array_flip($fields));
                        }
                        $snapshot[$kind] = array_merge($snapshot[$kind], array_slice($clean, 0, 100));
                        $snapshot['checks'][$kind . '_' . $symbol] = [
                            'state' => $kind === 'trades' || count($rows) > 100 ? 'partial' : 'confirmed',
                            'count' => min(count($rows), 100), 'limit_reached' => count($rows) >= 100,
                        ];
                    } catch (RuntimeException $e) {
                        $snapshot['checks'][$kind . '_' . $symbol] = ['state' => 'failed', 'code' => $e->getMessage()];
                    }
                }
            }
        } else {
            $snapshot['checks']['balances'] = ['state' => $private ? 'failed' : 'not_tested'];
        }
        // Neither generic Spot trades nor an empty balance establish bot/cycle coverage.
        $snapshot['checks']['dca_bots'] = ['state' => 'not_tested'];
        $snapshot['checks']['dca_cycles'] = ['state' => 'not_tested'];
        $snapshot['checks']['dca_reserves'] = ['state' => 'not_tested'];
        $states = array_column($snapshot['checks'], 'state');
        $hasData = count($snapshot['markets']) > 0 || ($snapshot['checks']['balances']['state'] ?? '') === 'confirmed';
        $snapshot['status'] = $hasData ? ($private || in_array('failed', $states, true) ? 'partial' : 'success') : 'failed';
        return $snapshot;
    }

    public static function demo(): array
    {
        return [
            'mode' => 'demo', 'source_id' => 'demo', 'status' => 'success',
            'observed_at' => gmdate('c'), 'symbols' => ['BTCUSDT'],
            'markets' => [['symbol'=>'BTCUSDT','base'=>'BTC','quote'=>'USDT','status'=>'TRADING','price'=>'60000.00000000']],
            'balances' => [['asset'=>'USDT','free'=>'100.00000000','locked'=>'25.00000000']],
            'orders' => [],
            'trades' => [['symbol'=>'BTCUSDT','id'=>'demo-1','orderId'=>'demo-order-1','price'=>'59000.00000000','qty'=>'0.00100000','quoteQty'=>'59.00000000','commission'=>'0.05900000','commissionAsset'=>'USDT','time'=>1700000000000,'isBuyer'=>true]],
            'checks' => array_fill_keys(['connection','market_BTCUSDT','balances','trades_BTCUSDT'], ['state'=>'demo']) + array_fill_keys(['dca_bots','dca_cycles','dca_reserves'], ['state'=>'not_tested']),
        ];
    }
}

function cryptoBinanceSaveRead(PDO $pdo, array $snapshot, int $actorId): int
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO crypto_binance_reads (mode, source_id, status, actor_id, snapshot) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$snapshot['mode'], $snapshot['source_id'], $snapshot['status'], $actorId, json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $id = (int)$pdo->lastInsertId();
        if ($snapshot['mode'] === 'account') {
            $upsert = $pdo->prepare('INSERT INTO crypto_binance_trades (source_id, symbol, external_id, payload, last_read_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_read_id = VALUES(last_read_id)');
            foreach ($snapshot['trades'] as $trade) {
                $upsert->execute([$snapshot['source_id'], $trade['symbol'], (string)$trade['id'], json_encode($trade, JSON_THROW_ON_ERROR), $id]);
            }
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
