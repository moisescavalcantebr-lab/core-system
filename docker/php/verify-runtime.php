<?php
declare(strict_types=1);

if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 3 || version_compare(PHP_VERSION, '8.3.36', '<')) {
    throw new RuntimeException('Core requires security-corrected PHP 8.3.36 or later in series 8.3.');
}
foreach (['curl', 'dom', 'fileinfo', 'mbstring', 'sodium', 'pdo_mysql', 'mysqli', 'gd', 'openssl', 'session', 'Zend OPcache'] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException('Missing required extension: ' . $extension);
    }
}
$gd = gd_info();
foreach (['JPEG Support', 'PNG Support', 'WebP Support'] as $feature) {
    if (empty($gd[$feature])) {
        throw new RuntimeException('Missing GD feature: ' . $feature);
    }
}
echo 'PASS: PHP ', PHP_VERSION, ', required extensions and GD formats.', PHP_EOL;
