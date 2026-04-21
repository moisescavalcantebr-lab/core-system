<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';

require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/flash.php';

requireAdmin();

global $pdo;

/* =========================
INPUT
========================= */

$id    = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$slug  = trim($_POST['slug'] ?? '');
$model = trim($_POST['model_slug'] ?? '');

if (!$id) {
    flash('error', 'Página inválida.');
    header('Location: /public/admin/pages/index.php');
    exit;
}

/* validação mínima */

if ($title === '' || $slug === '') {
    flash('error', 'Título e slug são obrigatórios.');
    header('Location: /public/admin/pages/edit.php?id=' . $id);
    exit;
}

/* =========================
BASE URL
========================= */

$baseUrl = '';

if (defined('PROJECT_PATH')) {
    $baseUrl = '/projects/' . basename(PROJECT_PATH);
}

/* =========================
SERVICE
========================= */

$service = new PageService($pdo);

try {

    $service->update($id, $title, $slug, $model);

    flash('success', 'Página atualizada com sucesso.');

} catch (Throwable $e) {

    flash('error', $e->getMessage());
}

/* =========================
REDIRECT PADRÃO
========================= */

header("Location: {$baseUrl}/public/admin/pages/edit.php?id={$id}");
exit;