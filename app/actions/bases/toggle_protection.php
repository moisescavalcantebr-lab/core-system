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

/*
|--------------------------------------------------------------------------
| Alternar proteção
|--------------------------------------------------------------------------
*/

$newStatus = $base['is_protected'] ? 0 : 1;

$pdo->prepare("
    UPDATE bases 
    SET is_protected = :status
    WHERE id = :id
")->execute([
    'status' => $newStatus,
    'id'     => $id
]);

$base['is_protected'] = $newStatus;

try {
    base_write_manifest($base, BASES_PATH . '/' . $base['slug']);
} catch (Throwable $e) {
    flash('warning', 'Protecao alterada, mas o manifesto da base nao foi atualizado: ' . $e->getMessage());
}

header("Location: /web/admin/bases/index.php");
exit;
