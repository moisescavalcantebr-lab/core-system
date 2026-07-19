<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
csrf_verify();
creditCardEnsureSchema($pdo);

$name = trim((string)($_POST['name'] ?? ''));

if ($name === '') {
    flash('error', 'Informe o nome do cartão.');
    redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
}

$stmt = $pdo->prepare("
    INSERT INTO finance_credit_cards (name, brand, last_digits, limit_amount, closing_day, due_day, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $name,
    trim((string)($_POST['brand'] ?? '')) ?: null,
    substr(preg_replace('/\D+/', '', (string)($_POST['last_digits'] ?? '')) ?? '', -4) ?: null,
    creditCardDecimal((string)($_POST['limit_amount'] ?? '0')),
    max(1, min(28, (int)($_POST['closing_day'] ?? 1))),
    max(1, min(28, (int)($_POST['due_day'] ?? 10))),
    trim((string)($_POST['notes'] ?? '')) ?: null,
]);

flash('success', 'Cartão cadastrado.');
redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
