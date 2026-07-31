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

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM finance_entries WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    http_response_code(404);
    exit('Lancamento nao encontrado');
}

$title = 'Editar Lancamento';
$entry['tags'] = $advancedCategories ? implode(', ', financeTagNames($pdo, (int)$entry['id'])) : '';
$categories = financeCategoryOptions($pdo, $advancedCategories);
$projectUsers = $usesParticipants
    ? $pdo->query("
        SELECT id, name, email, username, role
        FROM project_users
        WHERE status = 'active'
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC)
    : [];

$formAction = PROJECT_URL . '/admin/financeiro/update.php?id=' . $id;
$submitLabel = 'Atualizar Lancamento';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();

require APP_PATH . '/views/layout_admin.php';

