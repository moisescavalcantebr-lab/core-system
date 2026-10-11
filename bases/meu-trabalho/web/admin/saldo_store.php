<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();
requireProjectAdmin();
csrf_verify();

require_once APP_PATH . '/helpers/core_bridge.php';

function projectWalletParseAmount(string $value): float
{
    $value = trim($value);
    $value = str_replace(['R$', ' '], '', $value);

    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (str_contains($value, ',')) {
        $value = str_replace(',', '.', $value);
    }

    return round((float)$value, 2);
}

function projectWalletUploadReceipt(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Envie o comprovante.');
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Formato de comprovante invalido.');
    }

    $dir = STORAGE_PATH . '/wallet_receipts';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = 'saldo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $dir . '/' . $filename;

    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        throw new RuntimeException('Nao foi possivel salvar o comprovante.');
    }

    return PROJECT_URL . '/storage/wallet_receipts/' . $filename;
}

$corePdo = projectCorePdo();
$coreProjectId = (int)($project['id'] ?? 0);
$amount = projectWalletParseAmount((string)($_POST['amount'] ?? '0'));

if (!$corePdo || $coreProjectId <= 0) {
    flash('error', 'Core indisponivel para solicitar credito.');
    redirect(PROJECT_URL . '/admin/saldo.php');
}

if ($amount <= 0) {
    flash('error', 'Informe um valor valido.');
    redirect(PROJECT_URL . '/admin/saldo.php');
}

try {
    $receiptPath = projectWalletUploadReceipt($_FILES['receipt'] ?? []);

    $user = projectUser();
    $stmt = $corePdo->prepare("
        INSERT INTO project_wallet_requests
        (project_id, requested_by_name, requested_by_email, amount, payment_method, receipt_path, notes, status)
        VALUES (?, ?, ?, ?, 'pix', ?, ?, 'pending')
    ");
    $stmt->execute([
        $coreProjectId,
        (string)($user['name'] ?? ($project['owner_name'] ?? '')),
        (string)($user['email'] ?? ($project['owner_email'] ?? '')),
        $amount,
        $receiptPath,
        'Solicitacao de credito enviada pelo painel do projeto.',
    ]);

    flash('success', 'Solicitacao de credito enviada.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect(PROJECT_URL . '/admin/saldo.php');
