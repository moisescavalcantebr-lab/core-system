<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/base_manifest.php';

requireAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = :id");
$stmt->execute(['id' => $id]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    die('Base não encontrada.');
}

/*
|--------------------------------------------------------------------------
| default nunca pode ser alterada
|--------------------------------------------------------------------------
*/

if ($base['slug'] === 'base') {
    die('A base não pode ser modificada.');
}

$currentStage = base_normalize_stage($base['base_stage'] ?? null, (int)($base['is_protected'] ?? 0));
$publishBase = $currentStage !== 'published';
$newStage = $publishBase ? 'published' : 'laboratory';
$newStatus = $publishBase ? 1 : 0;
$basePath = BASES_PATH . '/' . $base['slug'];
$schemaPath = $basePath . '/app/database/schema.sql';

if ($publishBase && !is_file($schemaPath)) {
    flash('error', 'Schema da base nao encontrado. Corrija a pasta da base antes de publicar.');
    header("Location: /web/admin/bases/index.php");
    exit;
}

$pdo->prepare("
    UPDATE bases 
    SET base_stage = :base_stage,
        is_protected = :status
    WHERE id = :id
")->execute([
    'base_stage' => $newStage,
    'status' => $newStatus,
    'id'     => $id
]);

$base['base_stage'] = $newStage;
$base['is_protected'] = $newStatus;

try {
    if ($publishBase) {
        base_ensure_plan_prices($pdo, (int)$base['id']);
    }

    base_write_manifest($base, $basePath);
} catch (Throwable $e) {
    flash('warning', 'Status da base alterado, mas o manifesto nao foi atualizado: ' . $e->getMessage());
}

flash('success', $publishBase ? 'Base publicada, schema atualizado e travada para deploy.' : 'Base reaberta no laboratorio.');

header("Location: /web/admin/bases/index.php");
exit;
