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
    http_response_code(400);
    exit('Página inválida.');
}

/* =========================
SERVICE
========================= */

$service = new PageService($pdo);

try {

    $newStatus = $service->toggleStatus($id);

    $message = $newStatus === 'published'
        ? 'Página publicada.'
        : 'Página despublicada.';

    flash('success', $message);

} catch (Throwable $e) {

    flash('error', $e->getMessage());
    http_response_code(500);
    exit('Erro');
}

/* =========================
RESPOSTA (AJAX FRIENDLY)
========================= */

echo 'ok';
exit;