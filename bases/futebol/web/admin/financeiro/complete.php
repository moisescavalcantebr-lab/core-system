<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
financeEnsureEntryMetaSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$currentUser = projectUser();

if ($id <= 0) {
    flash('error', 'Lancamento invalido.');
    redirect(PROJECT_URL . '/admin/financeiro/index.php');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE finance_entries
    SET status = 'paid',
        paid_at = COALESCE(paid_at, CURDATE()),
        updated_by_user_id = ?
    WHERE id = ?
      AND status = 'pending'
");
$stmt->execute([(int)$currentUser['id'], $id]);

if ($stmt->rowCount() > 0) {
    flash('success', 'Lancamento concluido com a data de hoje.');
} else {
    flash('info', 'Lancamento ja estava finalizado ou nao foi encontrado.');
}

redirect(PROJECT_URL . '/admin/financeiro/index.php');
