<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

divulgacaoRequireAdmin();
divulgacaoEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM divulgacao_pages WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    flash('error', 'Pagina nao encontrada.');
    redirect(PROJECT_URL . '/admin/divulgacao/index.php');
}

$title = 'Editar pagina';
$templates = divulgacaoTemplates();
$action = PROJECT_URL . '/admin/divulgacao/update.php?id=' . $id;

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
