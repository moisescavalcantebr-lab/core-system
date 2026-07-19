<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$pixKey = trim((string)($_POST['pix_key'] ?? ''));
$pixKeyType = (string)($_POST['pix_key_type'] ?? 'random');
$pixReceiverName = trim((string)($_POST['pix_receiver_name'] ?? ''));

if (!in_array($pixKeyType, ['random', 'email', 'phone', 'document'], true)) {
    $pixKeyType = 'random';
}

financeSetSetting($pdo, 'pix_key', $pixKey);
financeSetSetting($pdo, 'pix_key_type', $pixKeyType);
financeSetSetting($pdo, 'pix_receiver_name', $pixReceiverName);

flash('success', 'Configurações financeiras salvas.');
redirect(PROJECT_URL . '/admin/financeiro/settings.php');
