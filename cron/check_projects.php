<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap/bootstrap.php';
require APP_PATH . '/services/ProjectSyncService.php';
require APP_PATH . '/services/LogService.php';

$sync = new ProjectSyncService($pdo);
$logger = new LogService($pdo);

// Buscar projetos ativos que expiraram
$stmt = $pdo->query("
    SELECT * FROM projects
    WHERE billing_status = 'active'
    AND expires_at IS NOT NULL
    AND expires_at < CURDATE()
");

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($projects as $project) {

    // Suspender
    $pdo->prepare("
        UPDATE projects
        SET billing_status = 'suspended',
            status = 'blocked'
        WHERE id = :id
    ")->execute([
        'id' => $project['id']
    ]);

    // Sincronizar project.json
    $sync->sync((int)$project['id']);

    // Log
    $logger->log(
        (int)$project['id'],
        'billing_suspended',
        'Projeto suspenso automaticamente por expiração.',
        'warning'
    );
}

echo "Cron executado com sucesso.\n";
