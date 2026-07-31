<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$baseId = (int)($_POST['base_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM bases WHERE id = ? LIMIT 1");
$stmt->execute([$baseId]);
$base = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$base) {
    flash('error', 'Base nao encontrada.');
    redirect('/web/admin/bases/index.php');
    exit;
}

try {
    $pageId = base_showcase_create_page($pdo, $base);
    flash('success', 'Pagina da base criada. Revise os blocos e publique antes de ativar a vitrine.');
    redirect('/web/admin/pages/edit.php?id=' . $pageId);
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('/web/admin/bases/vitrine.php?id=' . $baseId);
}
