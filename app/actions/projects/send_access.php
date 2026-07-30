<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectProvisioner.php';

requireAdmin();
csrf_verify();

$projectId = (int)($_POST['id'] ?? 0);
$redirectTo = (string)($_POST['redirect_to'] ?? '');
$redirectTo = str_starts_with($redirectTo, '/web/admin/')
    ? $redirectTo
    : '/web/admin/projects/view.php?id=' . $projectId;

if ($projectId <= 0) {
    flash('error', 'Projeto inválido.');
    redirect('/web/admin/projects');
}

try {
    $mailId = ProjectProvisioner::sendAccessEmail($pdo, $projectId);
} catch (Throwable $e) {
    flash('error', 'Não foi possível enviar o acesso: ' . $e->getMessage());
    redirect($redirectTo);
}

flash($mailId ? 'success' : 'error', $mailId ? 'Link de acesso reenviado.' : 'Não foi possível enviar o link de acesso.');
redirect($redirectTo);
