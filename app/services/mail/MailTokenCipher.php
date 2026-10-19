<?php
declare(strict_types=1);

final class MailTokenCipher
{
    private array $keys = [];

    public function __construct(array $keys, private string $active)
    {
        foreach ($keys as $id => $encoded) {
            $key = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $id) || $key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new DomainException('Chave de proteção inválida.');
            }
            $this->keys[$id] = $key;
        }
        if (!isset($this->keys[$active])) { throw new DomainException('Chave de proteção indisponível.'); }
    }

    public function seal(array $value, string $context): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(json_encode($value, JSON_THROW_ON_ERROR), $context, $nonce, $this->keys[$this->active]);
        return json_encode(['v'=>1,'key'=>$this->active,'nonce'=>base64_encode($nonce),'data'=>base64_encode($cipher)], JSON_THROW_ON_ERROR);
    }

    public function open(string $envelope, string $context): array
    {
        try {
            $value = json_decode($envelope, true, 8, JSON_THROW_ON_ERROR);
            $key = $this->keys[$value['key'] ?? ''] ?? null;
            $nonce = base64_decode($value['nonce'] ?? '', true);
            $cipher = base64_decode($value['data'] ?? '', true);
            if (($value['v'] ?? null) !== 1 || !$key || $nonce === false || strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES || $cipher === false) { throw new RuntimeException(); }
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, $context, $nonce, $key);
            if ($plain === false) { throw new RuntimeException(); }
            $decoded = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) { throw new RuntimeException(); }
            return $decoded;
        } catch (Throwable $e) { throw new DomainException('Não foi possível validar os dados protegidos.'); }
    }
}
