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
$currentUser = projectUser();

$stmt = $pdo->prepare("
    SELECT i.*, c.name AS card_name
    FROM finance_credit_card_invoices i
    INNER JOIN finance_credit_cards c ON c.id = i.card_id
    WHERE i.id = ?
    LIMIT 1
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    flash('error', 'Fatura não encontrada.');
    redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
}

if (!empty($invoice['finance_entry_id'])) {
    flash('warning', 'Esta fatura já foi lançada no financeiro.');
    redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
}

creditCardRefreshInvoiceTotal($pdo, $invoiceId);
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC) ?: $invoice;
$amount = (float)($invoice['total_amount'] ?? 0);

if ($amount <= 0) {
    flash('error', 'Fatura sem valor para lançar.');
    redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
}

$pdo->beginTransaction();

try {
    $title = 'Fatura ' . (string)$invoice['card_name'] . ' - ' . date('m/Y', strtotime((string)$invoice['reference_month']));
    $stmt = $pdo->prepare("
        INSERT INTO finance_entries
            (category_id, type, title, description, amount, party_type, party_module, party_id, party_name, due_date, paid_at, status, source, payment_method, receipt_path, created_by_user_id, updated_by_user_id)
        VALUES (NULL, 'expense', ?, ?, ?, 'other', 'cartao_credito', ?, ?, ?, NULL, 'pending', 'credit_card_invoice', 'credit_card', NULL, ?, ?)
    ");
    $stmt->execute([
        $title,
        'Fatura lançada pela addon Cartão de Crédito.',
        $amount,
        $invoiceId,
        (string)$invoice['card_name'],
        (string)$invoice['due_date'],
        (int)$currentUser['id'],
        (int)$currentUser['id'],
    ]);
    $entryId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        UPDATE finance_credit_card_invoices
        SET status = 'launched', finance_entry_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$entryId, $invoiceId]);

    $stmt = $pdo->prepare("
        UPDATE finance_credit_card_installments
        SET finance_entry_id = ?
        WHERE invoice_id = ?
          AND status <> 'canceled'
    ");
    $stmt->execute([$entryId, $invoiceId]);

    $pdo->commit();
    flash('success', 'Fatura lançada no financeiro como pendente.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', $e->getMessage());
}

redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
