<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);
financeEnsureCategoryAddonSchema($pdo);
$simpleAdminMode = financeSimpleAdminMode($pdo);
$usesParticipants = financeUsesParticipants($pdo) && !$simpleAdminMode;
$advancedCategories = financeAdvancedCategoriesEnabled();

$title = 'Novo Lancamento';
$isBalanceMode = !$simpleAdminMode && (string)($_GET['mode'] ?? '') === 'balance';
$entry = [
    'category_id' => '',
    'type' => 'income',
    'title' => '',
    'description' => '',
    'amount' => '',
    'party_type' => 'other',
    'party_module' => '',
    'party_id' => '',
    'party_name' => '',
    'due_date' => '',
    'paid_at' => '',
    'status' => 'pending',
    'source' => $isBalanceMode ? 'balance_deposit' : 'manual',
    'payment_method' => '',
    'receipt_path' => '',
    'tags' => '',
];

$categories = financeCategoryOptions($pdo, $advancedCategories);
$projectUsers = $usesParticipants
    ? $pdo->query("
        SELECT id, name, email, username, role
        FROM project_users
        WHERE status = 'active'
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$formAction = PROJECT_URL . '/admin/financeiro/store.php';
$submitLabel = $isBalanceMode ? 'Adicionar Saldo' : 'Salvar Lancamento';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';

