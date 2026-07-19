<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/base_manifest.php';

requireAdmin();
csrf_verify();

$slug = strtolower(trim((string)($_POST['slug'] ?? '')));

if ($slug === '' || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
    flash('error', 'Slug da base invalido.');
    redirect('/web/admin/bases/index.php');
}

$basesRoot = realpath(BASES_PATH);
$basePath = BASES_PATH . '/' . $slug;
$baseRealPath = realpath($basePath);

if (
    $basesRoot === false ||
    $baseRealPath === false ||
    !is_dir($baseRealPath) ||
    !str_starts_with($baseRealPath, $basesRoot . DIRECTORY_SEPARATOR)
) {
    flash('error', 'Pasta da base nao encontrada.');
    redirect('/web/admin/bases/index.php');
}

$stmt = $pdo->prepare('SELECT id FROM bases WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);

if ($stmt->fetchColumn()) {
    flash('warning', 'Esta base ja esta registrada.');
    redirect('/web/admin/bases/index.php');
}

$manifest = [];
$manifestPath = $baseRealPath . '/base.json';

if (is_file($manifestPath)) {
    $decoded = json_decode((string)file_get_contents($manifestPath), true);
    if (is_array($decoded)) {
        $manifest = $decoded;
    }
}

$manifestSlug = strtolower(trim((string)($manifest['slug'] ?? $slug)));
if ($manifestSlug !== '' && $manifestSlug !== $slug) {
    flash('error', 'O slug do manifesto nao confere com a pasta da base.');
    redirect('/web/admin/bases/index.php');
}

$fallbackName = ucwords(str_replace('-', ' ', $slug));
$base = [
    'name' => trim((string)($manifest['name'] ?? $fallbackName)) ?: $fallbackName,
    'slug' => $slug,
    'description' => trim((string)($manifest['description'] ?? 'Base registrada a partir da pasta /bases/' . $slug . '.')),
    'allows_users' => (int)($manifest['allows_users'] ?? 1),
    'max_admins' => max(1, (int)($manifest['max_admins'] ?? 1)),
    'status' => 1,
    'is_protected' => 0,
];

try {
    $insert = $pdo->prepare('
        INSERT INTO bases (name, slug, description, allows_users, max_admins, status, is_protected, created_at)
        VALUES (:name, :slug, :description, :allows_users, :max_admins, :status, :is_protected, NOW())
    ');

    $insert->execute([
        'name' => $base['name'],
        'slug' => $base['slug'],
        'description' => $base['description'],
        'allows_users' => $base['allows_users'],
        'max_admins' => $base['max_admins'],
        'status' => $base['status'],
        'is_protected' => $base['is_protected'],
    ]);

    $baseId = (int)$pdo->lastInsertId();
    if ($baseId > 0) {
        base_ensure_plan_prices($pdo, $baseId);
    }

    base_write_manifest($base, $baseRealPath);

    flash('success', 'Base registrada como pronta e desbloqueada.');
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel registrar a base: ' . $e->getMessage());
}

redirect('/web/admin/bases/index.php');
