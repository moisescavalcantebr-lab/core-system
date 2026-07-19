<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectProvisioner.php';

requireAdmin();
csrf_verify();

function projectForceDeleteRecursive(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    @chmod($path, 0775);

    $items = array_diff(scandir($path) ?: [], ['.', '..']);

    foreach ($items as $item) {
        $itemPath = $path . DIRECTORY_SEPARATOR . $item;

        if (is_dir($itemPath) && !is_link($itemPath)) {
            projectForceDeleteRecursive($itemPath);
            continue;
        }

        @chmod($itemPath, 0664);

        if (!@unlink($itemPath)) {
            throw new RuntimeException('Não foi possível remover o arquivo: ' . $itemPath);
        }
    }

    @chmod($path, 0775);

    if (!@rmdir($path)) {
        throw new RuntimeException('Não foi possível remover a pasta: ' . $path);
    }
}

$projectId = (int)($_POST['id'] ?? 0);
$confirmSlug = trim((string)($_POST['confirm_slug'] ?? ''));

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    http_response_code(404);
    exit('Projeto não encontrado.');
}

$forceDeleteUrl = '/web/admin/projects/force_delete.php?id=' . (int)$project['id'];

if (!hash_equals((string)$project['slug'], $confirmSlug)) {
    flash('error', 'Slug não confere. Exclusão cancelada.');
    redirect($forceDeleteUrl);
}

$projectsRoot = realpath(ROOT_PATH . '/projects');
$projectPath = realpath(ROOT_PATH . '/' . ltrim((string)$project['path'], '/'));

if ($projectsRoot === false) {
    throw new RuntimeException('Pasta de projetos não encontrada.');
}

if ($projectPath !== false) {
    $rootPrefix = rtrim($projectsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!str_starts_with($projectPath . DIRECTORY_SEPARATOR, $rootPrefix)) {
        throw new RuntimeException('Caminho do projeto fora da pasta permitida.');
    }
}

$databaseName = ProjectProvisioner::projectDatabaseName((string)$project['slug']);
$configPath = ($projectPath ?: ROOT_PATH . '/' . ltrim((string)$project['path'], '/')) . '/app/config/database.php';

if (is_file($configPath)) {
    try {
        $dbConfig = require $configPath;
        $databaseName = (string)($dbConfig['name'] ?? $databaseName);
    } catch (Throwable $e) {
    }
}

if (!preg_match('/^[A-Za-z0-9_]+$/', $databaseName)) {
    throw new RuntimeException('Nome do banco inválido para exclusão.');
}

if ($projectPath !== false) {
    try {
        projectForceDeleteRecursive($projectPath);
    } catch (Throwable $e) {
        flash('error', 'Não foi possível remover os arquivos do projeto. Verifique permissões no servidor. Detalhe: ' . $e->getMessage());
        redirect($forceDeleteUrl);
    }
}

try {
    $pdo->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
} catch (Throwable $e) {
    throw new RuntimeException('Arquivos removidos, mas o banco não pôde ser excluído: ' . $e->getMessage(), 0, $e);
}

$baseId = (int)$project['base_id'];
$auditMessage = sprintf(
    'Exclusão manual concluída. Projeto: %s | Slug: %s | Banco: %s | Cliente: %s',
    (string)$project['name'],
    (string)$project['slug'],
    $databaseName,
    (string)$project['owner_email']
);

$pdo->beginTransaction();

try {
    $refundDelete = $pdo->prepare("
        DELETE prr
        FROM plan_refund_requests prr
        INNER JOIN plan_upgrade_requests pur ON pur.id = prr.upgrade_request_id
        WHERE pur.project_id = :project_id
    ");
    $refundDelete->execute(['project_id' => $projectId]);

    $pdo->prepare("DELETE FROM plan_refund_requests WHERE project_id = :project_id")->execute(['project_id' => $projectId]);
    $pdo->prepare("DELETE FROM plan_upgrade_requests WHERE project_id = :project_id")->execute(['project_id' => $projectId]);
    $pdo->prepare("DELETE FROM project_wallet_movements WHERE project_id = :project_id")->execute(['project_id' => $projectId]);
    $pdo->prepare("DELETE FROM project_wallet_requests WHERE project_id = :project_id")->execute(['project_id' => $projectId]);
    $pdo->prepare("DELETE FROM project_access_tokens WHERE project_id = :project_id")->execute(['project_id' => $projectId]);
    $pdo->prepare("DELETE FROM project_logs WHERE project_id = :project_id")->execute(['project_id' => $projectId]);
    $pdo->prepare("DELETE FROM projects WHERE id = :id")->execute(['id' => $projectId]);
    $pdo->prepare("
        INSERT INTO project_logs (project_id, action, message, level)
        VALUES (NULL, 'force_deleted', :message, 'warning')
    ")->execute(['message' => $auditMessage]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

flash('success', 'Projeto, arquivos e banco removidos por completo.');
redirect('/web/admin/bases/projects.php?id=' . $baseId);
