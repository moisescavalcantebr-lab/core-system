<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
csrf_verify();
creditCardEnsureSchema($pdo);

$cardId = (int)($_POST['card_id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$purchaseDate = trim((string)($_POST['purchase_date'] ?? ''));
$amountTotal = creditCardDecimal((string)($_POST['amount_total'] ?? '0'));
$installmentsTotal = max(1, min(60, (int)($_POST['installments_total'] ?? 1)));
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

if ($cardId <= 0 || $title === '' || $purchaseDate === '' || $amountTotal <= 0) {
    flash('error', 'Preencha cartão, descrição, data e valor.');
    redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM finance_credit_cards WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$cardId]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$card) {
    flash('error', 'Cartão inválido.');
    redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
}

$date = DateTimeImmutable::createFromFormat('Y-m-d', $purchaseDate) ?: new DateTimeImmutable();
$closingDay = (int)($card['closing_day'] ?? 1);
$dueDay = (int)($card['due_day'] ?? 10);

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("
        INSERT INTO finance_credit_card_purchases
            (card_id, category_id, title, merchant, description, purchase_date, amount_total, installments_total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $cardId,
        $categoryId,
        $title,
        trim((string)($_POST['merchant'] ?? '')) ?: null,
        trim((string)($_POST['description'] ?? '')) ?: null,
        $date->format('Y-m-d'),
        $amountTotal,
        $installmentsTotal,
    ]);

    $purchaseId = (int)$pdo->lastInsertId();
    $baseAmount = floor(($amountTotal / $installmentsTotal) * 100) / 100;
    $lastAmount = round($amountTotal - ($baseAmount * ($installmentsTotal - 1)), 2);
    $invoiceIds = [];

    for ($i = 1; $i <= $installmentsTotal; $i++) {
        $installmentReference = creditCardInvoiceReference($date, $closingDay)->modify('+' . ($i - 1) . ' month');
        $invoiceId = creditCardEnsureInvoice($pdo, $cardId, $installmentReference, $closingDay, $dueDay);
        $invoiceIds[$invoiceId] = true;
        $amount = $i === $installmentsTotal ? $lastAmount : $baseAmount;

        $stmt = $pdo->prepare("
            INSERT INTO finance_credit_card_installments
                (purchase_id, invoice_id, installment_number, amount, due_date, status)
            VALUES (?, ?, ?, ?, ?, 'invoiced')
        ");
        $stmt->execute([
            $purchaseId,
            $invoiceId,
            $i,
            $amount,
            creditCardDateForDay($installmentReference, $dueDay),
        ]);
    }

    foreach (array_keys($invoiceIds) as $invoiceId) {
        creditCardRefreshInvoiceTotal($pdo, (int)$invoiceId);
    }

    $pdo->commit();
    flash('success', 'Compra registrada no cartão.');
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', $e->getMessage());
}

redirect(PROJECT_URL . '/admin/cartao_credito/index.php');
