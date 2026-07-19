<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectProvisioner.php';

requireAdmin();
csrf_verify();

try {
    $project = ProjectProvisioner::createFreeProject($pdo, [
        'name' => $_POST['name'] ?? '',
        'slug' => $_POST['slug'] ?? '',
        'owner_name' => $_POST['owner_name'] ?? '',
        'owner_email' => $_POST['owner_email'] ?? '',
        'base_id' => (int)($_POST['base_id'] ?? 0),
        'lead_id' => (int)($_POST['lead_id'] ?? 0),
        'meta' => [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    die($e->getMessage());
}

header('Location: /web/admin/projects/view.php?id=' . (int)$project['project_id']);
exit;
