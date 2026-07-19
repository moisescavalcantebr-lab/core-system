<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$decision = (string)($_POST['decision'] ?? '');

if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    flash('error', 'Solicitacao invalida.');
    redirect('/web/admin/financeiro/saldo.php');
}

$adminId = (int)($_SESSION['core_user']['id'] ?? 0);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT wr.*, p.name AS project_name
        FROM project_wallet_requests wr
        INNER JOIN projects p ON p.id = wr.project_id
        WHERE wr.id = ?
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

    if ($decision === 'approved') {
        $pdo->prepare("
            UPDATE project_wallet_requests
            SET status = 'approved',
                reviewed_by_user_id = ?,
                reviewed_at = NOW(),
                review_notes = ?
            WHERE id = ?
        ")->execute([
            $adminId > 0 ? $adminId : null,
            'Credito aprovado na carteira pelo Core.',
            $id,
        ]);

        $pdo->prepare("
            INSERT INTO project_wallet_movements
            (project_id, movement_type, source, amount, description, reference_table, reference_id, status, created_by_user_id)
            VALUES (?, 'credit', 'balance_request', ?, ?, 'project_wallet_requests', ?, 'applied', ?)
        ")->execute([
            (int)$request['project_id'],
            (float)$request['amount'],
            'Credito aprovado para a carteira do projeto.',
            $id,
            $adminId > 0 ? $adminId : null,
        ]);

        $pdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'wallet_credit_approved', ?, 'info')
        ")->execute([
            (int)$request['project_id'],
            'Credito aprovado na carteira: ' . coreWalletMoney((float)$request['amount']),
        ]);
    } else {
        $pdo->prepare("
            UPDATE project_wallet_requests
            SET status = 'rejected',
                reviewed_by_user_id = ?,
                reviewed_at = NOW(),
                review_notes = ?
            WHERE id = ?
        ")->execute([
            $adminId > 0 ? $adminId : null,
            'Credito rejeitado no Core.',
            $id,
        ]);

        $pdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'wallet_credit_rejected', ?, 'warning')
        ")->execute([
            (int)$request['project_id'],
            'Solicitacao de credito rejeitada: ' . coreWalletMoney((float)$request['amount']),
        ]);
    }

    $pdo->commit();
    flash('success', $decision === 'approved' ? 'Credito aprovado.' : 'Solicitacao rejeitada.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    flash('error', $e->getMessage());
}

redirect('/web/admin/financeiro/saldo.php');
