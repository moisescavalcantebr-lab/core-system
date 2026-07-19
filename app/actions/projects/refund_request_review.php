<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$notes = trim((string)($_POST['review_notes'] ?? ''));

if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
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

    if ($request['status'] !== 'pending') {
        throw new RuntimeException('Solicitacao ja analisada.');
    }

    $reviewerId = (int)($_SESSION['core_user']['id'] ?? 0) ?: null;

    $stmt = $pdo->prepare("
        UPDATE plan_refund_requests
        SET status = ?, reviewed_by_user_id = ?, reviewed_at = NOW(), review_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$decision, $reviewerId, $notes !== '' ? $notes : null, $id]);

    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (?, ?, ?, ?)
    ")->execute([
        (int)$request['project_id'],
        $decision === 'approved' ? 'refund_approved' : 'refund_rejected',
        ($decision === 'approved' ? 'Reembolso aprovado para ' : 'Reembolso rejeitado para ') . $request['plan_name'],
        $decision === 'approved' ? 'info' : 'warning',
    ]);

    $pdo->commit();
    flash('success', $decision === 'approved' ? 'Reembolso aprovado.' : 'Reembolso rejeitado.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect('/web/admin/projects/refund_requests.php');
