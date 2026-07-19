<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

cryptoDcaRequireAdmin();
cryptoDcaEnsureSchema($pdo);
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'strategy')) ?: 'strategy';
$description = trim((string)($_POST['description'] ?? ''));
$amount = cryptoDcaDecimal((string)($_POST['default_entry_amount'] ?? '50'));
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

if ($name === '') {
    die('Nome obrigatorio.');
}

$slug = cryptoDcaSlug($name);

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE crypto_wallets SET name = ?, slug = ?, description = ?, type = ?, default_entry_amount = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $slug, $description ?: null, $type, $amount > 0 ? $amount : 50, $status, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO crypto_wallets (name, slug, description, type, default_entry_amount, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $slug, $description ?: null, $type, $amount > 0 ? $amount : 50, $status]);
}

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/wallets.php');
exit;
