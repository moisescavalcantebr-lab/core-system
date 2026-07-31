<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAuth();
financeEnsureEntryMetaSchema($pdo);

if (!financeUsesParticipants($pdo)) {
    flash('info', 'Saldo por participante nao esta disponivel no modo financeiro pessoal.');
    redirect(PROJECT_URL . '/admin/financeiro/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$user = projectUser();
$userId = (int)($user['id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$pixSettings = financeCorePixSettings();

if ($userId <= 0 || $amount <= 0) {
    flash('error', 'Valor inválido.');
    redirect(PROJECT_URL . '/admin/financeiro/meu_saldo.php');
}

if (empty($pixSettings['upgrade_pix_key'])) {
    flash('error', 'Pix ainda não configurado no Core.');
    redirect(PROJECT_URL . '/admin/financeiro/meu_saldo.php');
}

try {
    $receiptPath = financeReceiptUpload($_FILES['receipt'] ?? [], 'wallet_request');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect(PROJECT_URL . '/admin/financeiro/meu_saldo.php');
}

if (!$receiptPath) {
    flash('error', 'Envie o comprovante.');
    redirect(PROJECT_URL . '/admin/financeiro/meu_saldo.php');
}

$stmt = $pdo->prepare("
    INSERT INTO finance_wallet_requests (user_id, amount, receipt_path, status)
    VALUES (?, ?, ?, 'pending')
");
$stmt->execute([$userId, $amount, $receiptPath]);

flash('success', 'Solicitação enviada. Aguarde a análise do administrador.');
redirect(PROJECT_URL . '/admin/financeiro/meu_saldo.php');
