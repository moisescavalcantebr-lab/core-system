<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$values = [
    'upgrade_pix_type' => trim((string)($_POST['upgrade_pix_type'] ?? '')),
    'upgrade_pix_key' => trim((string)($_POST['upgrade_pix_key'] ?? '')),
    'upgrade_pix_holder' => trim((string)($_POST['upgrade_pix_holder'] ?? '')),
    'upgrade_pix_notes' => trim((string)($_POST['upgrade_pix_notes'] ?? '')),
];

$stmt = $pdo->prepare("
    INSERT INTO core_settings (setting_key, setting_value)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");

foreach ($values as $key => $value) {
    $stmt->execute([$key, $value]);
}

flash('success', 'Configuração de Pix da carteira salva.');
redirect('/web/admin/plans/payment.php');
