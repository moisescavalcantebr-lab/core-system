<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$path = '/keys/master.key';
if (!is_file($path)) {
    $handle = fopen($path, 'xb');
    if ($handle === false) throw new RuntimeException('Cannot create protection key.');
    try {
        $encoded = base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
        if (fwrite($handle, $encoded . "\n") !== strlen($encoded) + 1) throw new RuntimeException('Cannot write protection key.');
        sodium_memzero($encoded);
    } finally { fclose($handle); }
}
$key = base64_decode(trim((string)file_get_contents($path)), true);
if ($key === false || strlen($key) !== 32) throw new RuntimeException('Existing key is invalid; do not replace automatically.');
sodium_memzero($key);
foreach ([['/keys',0700],[$path,0600]] as [$file,$mode]) {
    if (!chmod($file,$mode) || !chown($file,33) || !chgrp($file,33)) throw new RuntimeException('Cannot protect key permissions.');
}
echo "PASS: protection key ready, contents hidden.\n";
