<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap/bootstrap.php';
if (!coreIsLocal() || !in_array((string)$config['db']['host'], ['db','localhost','127.0.0.1'], true)) {
    throw new RuntimeException('Preparacao permitida somente no Docker/local, sem acesso ao servidor.');
}
function cryptoPrepareCopy(string $source, string $target): void {
    if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) throw new RuntimeException('Falha ao preparar pasta.');
    foreach (scandir($source) ?: [] as $name) {
        if (in_array($name, ['.','..','_notes'], true)) continue;
        if (is_dir($source . '/' . $name)) cryptoPrepareCopy($source . '/' . $name, $target . '/' . $name);
        elseif (!copy($source . '/' . $name, $target . '/' . $name)) throw new RuntimeException('Falha ao copiar arquivo.');
    }
}
$basePath = BASES_PATH . '/cripto';
$baseSchemaPath = $basePath . '/app/database/schema.sql';
foreach (['crypto_dca_manager','crypto_binance'] as $slug) {
    $source = ROOT_PATH . '/modules/' . $slug;
    $manifest = json_decode((string)file_get_contents($source . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest['attach_to'] ?? [] as $parent) {
        if (!is_file($basePath . '/modules/' . $parent . '/module.json')) throw new RuntimeException('Modulo pai ausente.');
    }
    cryptoPrepareCopy($source, $basePath . '/modules/' . $slug);
    foreach ($manifest['copy'] as $copy) cryptoPrepareCopy($source . '/' . $copy['from'], $basePath . '/' . $copy['to']);
    $schema = (string)file_get_contents($baseSchemaPath);
    if (!str_contains($schema, '-- MODULE: ' . $slug)) {
        file_put_contents($baseSchemaPath, "\n\n-- MODULE: " . $slug . "\n" . file_get_contents($source . '/' . $manifest['database']['schema']), FILE_APPEND);
    }
}
$baseJsonPath = $basePath . '/base.json';
$baseJson = json_decode((string)file_get_contents($baseJsonPath), true, 512, JSON_THROW_ON_ERROR);
if (($baseJson['schema']['hash'] ?? '') !== md5_file($baseSchemaPath)) {
    $baseJson['schema']['hash'] = md5_file($baseSchemaPath);
    $baseJson['schema']['updated_at'] = date('Y-m-d H:i:s');
}
file_put_contents($baseJsonPath, json_encode($baseJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
$stmt = $pdo->query("SELECT id, status FROM bases WHERE slug = 'cripto' LIMIT 1");
$base = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$base || (int)$base['status'] !== 1) throw new RuntimeException('Base Cripto precisa estar registrada e ativa no Core local.');
$free = $pdo->query("SELECT COUNT(*) FROM plans WHERE billing_cycle='free' AND status=1")->fetchColumn();
if (!(int)$free) throw new RuntimeException('Configure um plano gratuito para criar o projeto local.');
echo "PASS: base Cripto local preparada com DCA Strategy e Binance - leitura.\n";
echo "Crie o projeto em https://localhost:8443/web/admin/projects/create.php e selecione Base Cripto.\n";
