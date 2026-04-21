<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';

require APP_PATH . '/helpers/auth.php';

requireAdmin();

global $pdo;

/* =========================
INPUT
========================= */

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    flash('error', 'Página inválida.');
    header('Location: /public/admin/pages/index.php');
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

    $service->delete($id);

    flash('success', 'Página removida.');

} catch (Throwable $e) {

    flash('error', $e->getMessage());
}

/* =========================
REDIRECT PADRÃO
========================= */

header("Location: {$baseUrl}/public/admin/pages/index.php");
exit;