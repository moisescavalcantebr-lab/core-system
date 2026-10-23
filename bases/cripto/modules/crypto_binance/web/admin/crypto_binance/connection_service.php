<?php
declare(strict_types=1);

/** One encrypted, read-only Spot connection per project database. No credentials in metadata. */
final class CryptoBinanceConnectionStore
{
    public function __construct(private PDO $pdo, private string $keyFile = '/run/crypto-binance/master.key') {}

    private function protectionKey(): string
    {
        $path = realpath($this->keyFile);
        $webRoot = realpath('/var/www/html');
        if ($path === false || !is_file($path) || !is_readable($path)
            || ($webRoot !== false && ($path === $webRoot || str_starts_with($path, $webRoot . DIRECTORY_SEPARATOR)))) {
            throw new DomainException('Protecao da conexao indisponivel. Confira a chave privada do ambiente local.');
        }
        $encoded = @file_get_contents($path, false, null, 0, 256);
        $key = is_string($encoded) ? base64_decode(trim($encoded), true) : false;
        if (is_string($encoded)) sodium_memzero($encoded);
        if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new DomainException('Protecao da conexao indisponivel. Confira a chave privada do ambiente local.');
        }
        return $key;
    }

    public function protectionAvailable(): bool
    {
        try { $key = $this->protectionKey(); sodium_memzero($key); return true; }
        catch (DomainException $e) { return false; }
    }

    private function context(): string
    {
        return 'crypto-binance:connection:v1:' . hash('sha256', (string)$this->pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function metadata(): ?array
    {
        $row = $this->pdo->query('SELECT updated_at, actor_id, generation FROM crypto_binance_connections WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(#[SensitiveParameter] string $apiKey, #[SensitiveParameter] string $secret, int $actor): void
    {
        $apiKey = trim($apiKey); $secret = trim($secret);
        if (!preg_match('/^[A-Za-z0-9]{16,256}$/D', $apiKey) || !preg_match('/^[A-Za-z0-9]{16,256}$/D', $secret) || $actor < 1) {
            throw new InvalidArgumentException('Informe a chave e o segredo HMAC completos.');
        }
        $key = $this->protectionKey(); $plain = '';
        try {
            $plain = json_encode(['api_key'=>$apiKey, 'api_secret'=>$secret], JSON_THROW_ON_ERROR);
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $this->context(), $nonce, $key);
            $envelope = json_encode(['v'=>1, 'key'=>substr(hash('sha256', $key),0,16), 'nonce'=>base64_encode($nonce), 'data'=>base64_encode($cipher)], JSON_THROW_ON_ERROR);
            $stmt = $this->pdo->prepare('INSERT INTO crypto_binance_connections(id, credential_envelope, actor_id) VALUES(1,?,?) ON DUPLICATE KEY UPDATE credential_envelope=VALUES(credential_envelope),actor_id=VALUES(actor_id),generation=generation+1,updated_at=UTC_TIMESTAMP()');
            $stmt->execute([$envelope, $actor]);
        } finally { sodium_memzero($plain); sodium_memzero($key); sodium_memzero($apiKey); sodium_memzero($secret); }
    }

    public function credentials(): array
    {
        $envelope = $this->pdo->query('SELECT credential_envelope FROM crypto_binance_connections WHERE id=1')->fetchColumn();
        if (!is_string($envelope)) throw new DomainException('Salve uma conexao de leitura antes de atualizar a conta.');
        $key = $this->protectionKey(); $plain = '';
        try {
            $value = json_decode($envelope, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($value) || ($value['v'] ?? null) !== 1 || !hash_equals(substr(hash('sha256',$key),0,16), (string)($value['key'] ?? ''))) throw new RuntimeException();
            $nonce = base64_decode($value['nonce'] ?? '', true);
            $cipher = base64_decode($value['data'] ?? '', true);
            if (!is_string($nonce) || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || !is_string($cipher) || strlen($cipher) > 4096) throw new RuntimeException();
            $decoded = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $this->context(), $nonce, $key);
            if ($decoded === false) throw new RuntimeException();
            $plain = $decoded; unset($decoded);
            $credentials = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($credentials) || !is_string($credentials['api_key'] ?? null) || !is_string($credentials['api_secret'] ?? null)
                || !preg_match('/^[A-Za-z0-9]{16,256}$/D',$credentials['api_key']) || !preg_match('/^[A-Za-z0-9]{16,256}$/D',$credentials['api_secret'])) throw new RuntimeException();
            return $credentials;
        } catch (Throwable $e) {
            throw new DomainException('Nao foi possivel abrir a conexao protegida. Restaure a chave privada do ambiente ou salve novamente as credenciais.');
        } finally { sodium_memzero($plain); sodium_memzero($key); }
    }

    public function read(array $symbols, CryptoBinanceReader $reader): array
    {
        $credentials = $this->credentials();
        try { return $reader->read($symbols, $credentials['api_key'], $credentials['api_secret']); }
        finally { sodium_memzero($credentials['api_key']); sodium_memzero($credentials['api_secret']); }
    }

    public function disconnect(): void
    {
        $this->pdo->exec('DELETE FROM crypto_binance_connections WHERE id=1');
    }
}

function cryptoBinanceConnectionLock(PDO $pdo): string
{
    return 'crypto_binance_' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
}
