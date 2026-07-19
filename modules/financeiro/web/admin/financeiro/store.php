<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);
financeEnsureCategoryAddonSchema($pdo);

function financeEntryBaseDate(?string $preferredDate, ?string $fallbackDate): DateTimeImmutable
{
    $date = $preferredDate ?: $fallbackDate ?: date('Y-m-d');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsed ?: new DateTimeImmutable(date('Y-m-d'));
}

function financeEntryMonthDate(DateTimeImmutable $baseDate, int $offset): string
{
    return $baseDate->modify('+' . $offset . ' months')->format('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$currentUser = projectUser();
$title = trim((string)($_POST['title'] ?? ''));
$requestedType = (string)($_POST['type'] ?? 'income');
$simpleAdminMode = financeSimpleAdminMode($pdo);
$isBalanceDeposit = !$simpleAdminMode && $requestedType === 'balance_deposit';
$usesParticipants = financeUsesParticipants($pdo) && !$simpleAdminMode;
$advancedCategories = financeAdvancedCategoriesEnabled();

if ($title === '' && !$isBalanceDeposit) {
    exit('Titulo obrigatorio.');
}

$title = $isBalanceDeposit ? 'Adicao de saldo' : $title;
$status = in_array($_POST['status'] ?? '', ['pending', 'paid', 'canceled'], true) ? $_POST['status'] : 'pending';
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$type = $requestedType === 'expense' ? 'expense' : 'income';
$categoryFormModel = 'simple';

if ($categoryId) {
    $stmt = $pdo->prepare("SELECT type, parent_id, form_model FROM finance_categories WHERE id = ? LIMIT 1");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category || (!$advancedCategories && !empty($category['parent_id']))) {
        $categoryId = null;
        $categoryFormModel = 'simple';
    }

    $categoryType = (string)($category['type'] ?? '');
    $categoryFormModel = financeNormalizeFormModel((string)($category['form_model'] ?? 'simple'));

    if ($categoryType === 'income' || $categoryType === 'expense') {
        $type = $categoryType;
    }
}

$dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$paidAt = !empty($_POST['paid_at']) ? $_POST['paid_at'] : null;

if ($status === 'paid' && $paidAt === null) {
    $paidAt = date('Y-m-d');
} elseif ($status !== 'paid') {
    $paidAt = null;
}

$allowedPartyTypes = $usesParticipants
    ? ['user', 'admin', 'supplier', 'customer', 'member', 'other']
    : ['admin', 'supplier', 'customer', 'member', 'other'];
$partyType = in_array($_POST['party_type'] ?? '', $allowedPartyTypes, true)
    ? $_POST['party_type']
    : 'other';
$partyModule = null;
$partyId = $partyType === 'user' && !empty($_POST['party_id']) ? (int)$_POST['party_id'] : null;
$partyName = $partyType === 'user' ? null : (trim((string)($_POST['party_name'] ?? '')) ?: null);
$source = 'manual';
$paymentMethod = array_key_exists((string)($_POST['payment_method'] ?? ''), financePaymentMethodOptions())
    ? (string)$_POST['payment_method']
    : null;
$receiptPath = null;
$amount = (float)($_POST['amount'] ?? 0);

if ($amount <= 0) {
    exit('Valor invalido.');
}

if ($isBalanceDeposit) {
    $categoryId = null;
    $type = 'income';
    $status = 'paid';
    $source = 'balance_deposit';
    $partyType = 'other';
    $partyId = null;
    $partyName = null;
    $dueDate = null;
    $paidAt = $paidAt ?: date('Y-m-d');
}

try {
    $receiptPath = financeReceiptUpload($_FILES['receipt'] ?? [], 'entry');
} catch (Throwable $e) {
    exit($e->getMessage());
}

$stmt = $pdo->prepare("
    INSERT INTO finance_entries (category_id, type, title, description, amount, party_type, party_module, party_id, party_name, due_date, paid_at, status, source, payment_method, receipt_path, created_by_user_id, updated_by_user_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $categoryId,
    $type,
    $title,
    trim((string)($_POST['description'] ?? '')) ?: null,
    $amount,
    $partyType,
    $partyModule,
    $partyId,
    $partyName,
    $dueDate,
    $paidAt,
    $status,
    $source,
    $paymentMethod,
    $receiptPath,
    (int)$currentUser['id'],
    (int)$currentUser['id'],
]);

$entryId = (int)$pdo->lastInsertId();
financeSyncEntryTags($pdo, $entryId, (string)($_POST['tags'] ?? ''));

$tags = (string)($_POST['tags'] ?? '');
$description = trim((string)($_POST['description'] ?? '')) ?: null;
$createdBy = (int)$currentUser['id'];
$updatedBy = (int)$currentUser['id'];

if (!$isBalanceDeposit && $categoryFormModel === 'installment') {
    $installmentsTotal = max(1, min(120, (int)($_POST['installments_total'] ?? 1)));

    if ($installmentsTotal > 1) {
        $pdo->prepare("DELETE FROM finance_entry_tags WHERE entry_id = ?")->execute([$entryId]);
        $pdo->prepare("DELETE FROM finance_entries WHERE id = ?")->execute([$entryId]);

        $baseDate = financeEntryBaseDate((string)($_POST['installment_first_due_date'] ?? ''), $dueDate);
        $totalCents = (int)round($amount * 100);
        $baseCents = intdiv($totalCents, $installmentsTotal);
        $remainingCents = $totalCents % $installmentsTotal;

        for ($i = 1; $i <= $installmentsTotal; $i++) {
            $installmentAmount = ($baseCents + ($i <= $remainingCents ? 1 : 0)) / 100;
            $stmt->execute([
                $categoryId,
                $type,
                $title . ' (' . $i . '/' . $installmentsTotal . ')',
                $description,
                $installmentAmount,
                $partyType,
                $partyModule,
                $partyId,
                $partyName,
                financeEntryMonthDate($baseDate, $i - 1),
                null,
                'pending',
                'installment',
                $paymentMethod,
                $receiptPath,
                $createdBy,
                $updatedBy,
            ]);

            financeSyncEntryTags($pdo, (int)$pdo->lastInsertId(), $tags);
        }
    }
} elseif (!$isBalanceDeposit && $categoryFormModel === 'recurring') {
    $recurrenceCount = max(1, min(120, (int)($_POST['recurrence_count'] ?? 1)));

    if ($recurrenceCount > 1) {
        $pdo->prepare("DELETE FROM finance_entry_tags WHERE entry_id = ?")->execute([$entryId]);
        $pdo->prepare("DELETE FROM finance_entries WHERE id = ?")->execute([$entryId]);

        $baseDate = financeEntryBaseDate((string)($_POST['recurrence_first_due_date'] ?? ''), $dueDate);

        for ($i = 1; $i <= $recurrenceCount; $i++) {
            $entryDate = financeEntryMonthDate($baseDate, $i - 1);
            $stmt->execute([
                $categoryId,
                $type,
                $title . ' - ' . date('m/Y', strtotime($entryDate)),
                $description,
                $amount,
                $partyType,
                $partyModule,
                $partyId,
                $partyName,
                $entryDate,
                null,
                'pending',
                'recurring',
                $paymentMethod,
                $receiptPath,
                $createdBy,
                $updatedBy,
            ]);

            financeSyncEntryTags($pdo, (int)$pdo->lastInsertId(), $tags);
        }
    }
}

header('Location: ' . PROJECT_URL . '/admin/financeiro/index.php');
exit;

