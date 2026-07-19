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
$description = trim((string)($_POST['description'] ?? ''));
$risk = array_key_exists((string)($_POST['risk_level'] ?? ''), cryptoDcaRiskOptions()) ? (string)$_POST['risk_level'] : 'medium';
$status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

if ($name === '') {
    die('Nome obrigatorio.');
}

$slug = cryptoDcaSlug($name);

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE crypto_groups SET name = ?, slug = ?, description = ?, risk_level = ?, status = ? WHERE id = ?");
    $stmt->execute([$name, $slug, $description ?: null, $risk, $status, $id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO crypto_groups (name, slug, description, risk_level, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $slug, $description ?: null, $risk, $status]);
}

header('Location: ' . PROJECT_URL . '/admin/crypto_dca_manager/groups.php');
exit;
