<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/fields.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM classification_tables WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    http_response_code(404);
    exit('Classificacao nao encontrada');
}

$title = 'Editar Classificacao';
$formAction = PROJECT_URL . '/admin/classificacao/update.php?id=' . $id;
$submitLabel = 'Atualizar Classificacao';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';
