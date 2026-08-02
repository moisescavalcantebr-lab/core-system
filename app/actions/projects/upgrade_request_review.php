<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/services/projects/ProjectInstaller.php';

requireAdmin();
csrf_verify();

$id = (int)($_POST['id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$notes = trim((string)($_POST['review_notes'] ?? ''));

function upgradeReviewSafeModuleSlug(string $slug): string
{
    return preg_replace('/[^a-z0-9\-_]/', '', strtolower($slug));
}

function upgradeReviewCopyFolder(string $source, string $destination): void
{
    if (!is_dir($source)) {
        return;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    foreach (scandir($source) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $sourceFile = $source . '/' . $file;
        $destinationFile = $destination . '/' . $file;

        if (is_dir($sourceFile)) {
            upgradeReviewCopyFolder($sourceFile, $destinationFile);
            continue;
        }

        if (!is_file($destinationFile) || md5_file($sourceFile) !== md5_file($destinationFile)) {
            copy($sourceFile, $destinationFile);
        }
    }
}

function upgradeReviewProjectPdo(array $project): ?PDO
{
    $configPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/') . '/app/config/database.php';
    if (!is_file($configPath)) {
        return null;
    }

    $dbConfig = require $configPath;

    try {
        return new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}",
            $dbConfig['user'],
            $dbConfig['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function upgradeReviewApplyModuleSchemas(?PDO $projectPdo, string $schemaPath): void
{
    if (!$projectPdo || !is_file($schemaPath)) {
        return;
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false || trim($sql) === '') {
        return;
    }

    $projectPdo->exec($sql);
}

function upgradeReviewInstallRequestedModules(PDO $corePdo, array $project, array $request): array
{
    $requestedModules = json_decode((string)($request['requested_modules_json'] ?? ''), true);
    if (!is_array($requestedModules)) {
        $requestedModules = [];
    }

    if (empty($requestedModules)) {
        return [];
    }

    $projectPath = ROOT_PATH . '/' . ltrim((string)$project['path'], '/');
    $projectPdo = upgradeReviewProjectPdo($project);
    $installed = [];

    foreach ($requestedModules as $module) {
        $moduleSlug = upgradeReviewSafeModuleSlug((string)($module['slug'] ?? ''));
        if ($moduleSlug === '') {
            continue;
        }

        $modulePath = ROOT_PATH . '/modules/' . $moduleSlug;
        $manifestPath = $modulePath . '/module.json';
        if (!is_file($manifestPath)) {
            continue;
        }

        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            continue;
        }

        upgradeReviewCopyFolder($modulePath, $projectPath . '/modules/' . $moduleSlug);

        foreach (($manifest['copy'] ?? []) as $copy) {
            if (!is_array($copy)) {
                continue;
            }

            $from = trim((string)($copy['from'] ?? ''), '/');
            $to = trim((string)($copy['to'] ?? ''), '/');
            if ($from === '' || $to === '' || str_contains($from, '..') || str_contains($to, '..')) {
                continue;
            }

            upgradeReviewCopyFolder($modulePath . '/' . $from, $projectPath . '/' . $to);
        }

        $schema = $manifest['database']['schema'] ?? '';
        if ($schema) {
            upgradeReviewApplyModuleSchemas($projectPdo, $modulePath . '/' . ltrim((string)$schema, '/'));
        }

        $installed[] = (string)($manifest['label'] ?? $moduleSlug);
    }

    if (!empty($installed)) {
        $corePdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'modules_approved', ?, 'info')
        ")->execute([
            (int)$project['id'],
            'Modulos extras aprovados: ' . implode(', ', $installed),
        ]);
    }

    return $installed;
}

if ($id <= 0 || !in_array($decision, ['approved', 'rejected'], true)) {
    flash('error', 'Solicitacao invalida.');
    redirect('/web/admin/projects/upgrade_requests.php');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            p.base_id,
            p.id AS project_id,
            p.path,
            COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle,
            pl.name AS plan_name
        FROM plan_upgrade_requests r
        INNER JOIN projects p ON p.id = r.project_id
        INNER JOIN plans pl ON pl.id = r.plan_id
        LEFT JOIN plan_prices pp ON pp.id = r.plan_price_id
        WHERE r.id = ?
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

    if (!empty($request['plan_price_id'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM base_plan_prices bpp
            WHERE bpp.plan_price_id = ?
              AND bpp.base_id = ?
              AND bpp.status = 1
        ");
        $stmt->execute([(int)$request['plan_price_id'], (int)$request['base_id']]);

        if ((int)$stmt->fetchColumn() <= 0) {
            throw new RuntimeException('Plano nao vinculado a base do projeto.');
        }
    }

    $reviewerId = (int)($_SESSION['core_user']['id'] ?? 0) ?: null;

    if ($decision === 'approved') {
        $expiresAt = null;
        if ($request['billing_cycle'] === 'monthly') {
            $expiresAt = date('Y-m-d', strtotime('+30 days'));
        } elseif ($request['billing_cycle'] === 'annual') {
            $expiresAt = date('Y-m-d', strtotime('+365 days'));
        }

        $stmt = $pdo->prepare("
            UPDATE projects
            SET plan_id = ?, plan_price_id = ?, billing_status = 'active', expires_at = ?
            WHERE id = ?
        ");
        $stmt->execute([(int)$request['plan_id'], !empty($request['plan_price_id']) ? (int)$request['plan_price_id'] : null, $expiresAt, (int)$request['project_id']]);

        ProjectInstaller::syncFromDatabase($pdo, (int)$request['project_id']);
        ProjectInstaller::syncInstalledModulesFromBase($pdo, (int)$request['project_id']);
        $installedModules = upgradeReviewInstallRequestedModules($pdo, [
            'id' => (int)$request['project_id'],
            'path' => (string)$request['path'],
        ], $request);

        $pdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'upgrade_approved', ?, 'info')
        ")->execute([
            (int)$request['project_id'],
            'Upgrade aprovado para ' . $request['plan_name'] . (!empty($installedModules) ? ' com modulos: ' . implode(', ', $installedModules) : ''),
        ]);
    }

    $stmt = $pdo->prepare("
        UPDATE plan_upgrade_requests
        SET status = ?, reviewed_by_user_id = ?, reviewed_at = NOW(), review_notes = ?
        WHERE id = ?
    ");
    $stmt->execute([$decision, $reviewerId, $notes !== '' ? $notes : null, $id]);

    if ($decision === 'rejected') {
        $pdo->prepare("
            INSERT INTO project_logs (project_id, action, message, level)
            VALUES (?, 'upgrade_rejected', ?, 'warning')
        ")->execute([
            (int)$request['project_id'],
            'Upgrade rejeitado para ' . $request['plan_name'],
        ]);
    }

    $pdo->commit();
    flash('success', $decision === 'approved' ? 'Upgrade aprovado e projeto sincronizado.' : 'Solicitacao rejeitada.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $e->getMessage());
}

redirect('/web/admin/projects/upgrade_requests.php');
