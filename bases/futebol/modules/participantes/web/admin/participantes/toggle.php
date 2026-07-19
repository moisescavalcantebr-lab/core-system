<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo invalido');
}

csrf_verify();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, user_id, status FROM participants WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$participant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participant) {
    exit('Participante nao encontrado.');
}

$newStatus = $participant['status'] === 'active' ? 'inactive' : 'active';
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("UPDATE participants SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);

    if (!empty($participant['user_id'])) {
        $stmt = $pdo->prepare("UPDATE project_users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus === 'active' ? 'active' : 'inactive', (int)$participant['user_id']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

flash('success', 'Status atualizado.');
redirect(participantAdminUrl());
