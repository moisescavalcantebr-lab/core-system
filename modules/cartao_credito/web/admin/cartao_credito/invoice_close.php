<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
csrf_verify();
creditCardEnsureSchema($pdo);

$invoiceId = (int)($_GET['id'] ?? 0);

if ($invoiceId > 0) {
    creditCardRefreshInvoiceTotal($pdo, $invoiceId);
    $stmt = $pdo->prepare("UPDATE finance_credit_card_invoices SET status = 'closed' WHERE id = ? AND status = 'open'");
    $stmt->execute([$invoiceId]);
    flash('success', 'Fatura fechada.');
}

redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
