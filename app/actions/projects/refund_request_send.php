<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$notes = trim((string)($_POST['sent_notes'] ?? ''));

if ($id <= 0) {
    flash('error', 'Solicitacao invalida.');
    redirect('/web/admin/projects/refund_requests.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT rf.*, pl.name AS plan_name
        FROM plan_refund_requests rf
        INNER JOIN plans pl ON pl.id = rf.plan_id
        WHERE rf.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        throw new RuntimeException('Solicitacao nao encontrada.');
    }

    if ($request['status'] !== 'approved') {
        throw new RuntimeException('Apenas reembolsos aprovados podem ser marcados como enviados.');
    }

    if (!empty($request['sent_at'])) {
        throw new RuntimeException('Reembolso ja marcado como enviado.');
    }

    $receiptPath = null;
    if (!empty($_FILES['sent_receipt']['tmp_name']) && is_uploaded_file($_FILES['sent_receipt']['tmp_name'])) {
        $extension = strtolower(pathinfo((string)$_FILES['sent_receipt']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('Formato de comprovante invalido.');
        }

        $storageRoot = ROOT_PATH . '/storage/refund_receipts';
        if (!is_dir($storageRoot)) {
            mkdir($storageRoot, 0775, true);
        }

        $fileName = 'refund_' . (int)$request['project_id'] . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $storageRoot . '/' . $fileName;

        if (!move_uploaded_file($_FILES['sent_receipt']['tmp_name'], $destination)) {
            throw new RuntimeException('Nao foi possivel salvar o comprovante.');
        }

        $receiptPath = 'storage/refund_receipts/' . $fileName;
    }

    $senderId = (int)($_SESSION['core_user']['id'] ?? 0) ?: null;

    $stmt = $pdo->prepare("
        UPDATE plan_refund_requests
        SET sent_by_user_id = ?, sent_at = NOW(), sent_receipt_path = ?, sent_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$senderId, $receiptPath, $notes !== '' ? $notes : null, $id]);

    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (?, 'refund_sent', ?, 'info')
    ")->execute([
        (int)$request['project_id'],
        'Reembolso enviado para ' . $request['plan_name'],
    ]);

    $pdo->commit();
    flash('success', 'Reembolso marcado como enviado.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect('/web/admin/projects/refund_requests.php');
