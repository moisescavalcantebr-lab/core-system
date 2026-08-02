<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);
financeEnsureCategoryAddonSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$currentUser = projectUser();
$id = (int)($_GET['id'] ?? 0);
$title = trim((string)($_POST['title'] ?? ''));
$requestedType = (string)($_POST['type'] ?? 'income');
$simpleAdminMode = financeSimpleAdminMode($pdo);
$isBalanceDeposit = !$simpleAdminMode && $requestedType === 'balance_deposit';
$usesParticipants = financeUsesParticipants($pdo) && !$simpleAdminMode;
$advancedCategories = financeAdvancedCategoriesEnabled();

if ($id <= 0 || ($title === '' && !$isBalanceDeposit)) {
    exit('Dados invalidos.');
}

$title = $isBalanceDeposit ? 'Adicao de saldo' : $title;
$stmt = $pdo->prepare("SELECT source, receipt_path FROM finance_entries WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$existingEntry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existingEntry) {
    exit('Lancamento nao encontrado.');
}

$status = in_array($_POST['status'] ?? '', ['pending', 'paid', 'canceled'], true) ? $_POST['status'] : 'pending';
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
$type = $requestedType === 'expense' ? 'expense' : 'income';

if ($categoryId) {
    $stmt = $pdo->prepare("SELECT type, parent_id FROM finance_categories WHERE id = ? LIMIT 1");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $categoryId = null;
    }

    $categoryType = (string)($category['type'] ?? '');

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
$source = (string)($existingEntry['source'] ?? 'manual');
$source = $source !== '' ? $source : 'manual';
$paymentMethod = array_key_exists((string)($_POST['payment_method'] ?? ''), financePaymentMethodOptions())
    ? (string)$_POST['payment_method']
    : null;
$receiptPath = $existingEntry['receipt_path'] ?? null;
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
} elseif ($source === 'balance_deposit') {
    $source = 'manual';
}

try {
    $receiptPath = financeReceiptUpload($_FILES['receipt'] ?? [], 'entry') ?? $receiptPath;
} catch (Throwable $e) {
    exit($e->getMessage());
}

$stmt = $pdo->prepare("
    UPDATE finance_entries
    SET category_id = ?,
        type = ?,
        title = ?,
        description = ?,
        amount = ?,
        party_type = ?,
        party_module = ?,
        party_id = ?,
        party_name = ?,
        due_date = ?,
        paid_at = ?,
        status = ?,
        source = ?,
        payment_method = ?,
        receipt_path = ?,
        updated_by_user_id = ?
    WHERE id = ?
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
    $id,
]);

financeSyncEntryTags($pdo, $id, (string)($_POST['tags'] ?? ''));

header('Location: ' . PROJECT_URL . '/admin/financeiro/index.php');
exit;

