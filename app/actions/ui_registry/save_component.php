<?php
declare(strict_types=1);

require '../../views/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/* =========================
INPUT
========================= */

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo 'Dados inválidos';
    exit;
}

/* =========================
SERVICE
========================= */

$service = new UiRegistryService();

echo $service->saveComponent($data);