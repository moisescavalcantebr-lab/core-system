<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/base_manifest.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = ? LIMIT 1");
$stmt->execute([$baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    flash('error', 'Base nao encontrada.');
    redirect('/web/admin/bases/index.php');
}

if ((int)$base['is_protected'] === 1) {
    flash('error', 'Desbloqueie a base antes de atualizar o schema.');
    redirect('/web/admin/bases/index.php');
}

$basePath = BASES_PATH . '/' . $base['slug'];
$schemaPath = $basePath . '/app/database/schema.sql';

if (!is_file($schemaPath)) {
    flash('error', 'Schema da base nao encontrado.');
    redirect('/web/admin/bases/index.php');
}

try {
    base_write_manifest($base, $basePath);
    base_ensure_plan_prices($pdo, (int)$base['id']);
    flash('success', 'Schema da base registrado. Agora voce pode bloquear a base para proteger esta versao.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('/web/admin/bases/index.php');
