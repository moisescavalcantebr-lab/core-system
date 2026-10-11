<?php
declare(strict_types=1);

function projectCorePdo(): ?PDO
{
    $coreRootPath = dirname(PROJECT_PATH, 2);
    $coreEnvPath = $coreRootPath . '/env/env.production.php';

    if (!is_file($coreEnvPath)) {
        return null;
    }

    try {
        $coreConfig = require $coreEnvPath;
        $coreDb = $coreConfig['db'] ?? [];
        $coreDsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            (string)($coreDb['host'] ?? 'localhost'),
            (string)($coreDb['name'] ?? ''),
            (string)($coreDb['charset'] ?? 'utf8mb4')
        );

        return new PDO(
            $coreDsn,
            (string)($coreDb['user'] ?? ''),
            (string)($coreDb['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function projectCoreWalletMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function projectCoreWalletBalance(?PDO $corePdo, int $projectId): float
{
    if (!$corePdo || $projectId <= 0) {
        return 0.0;
    }

    $stmt = $corePdo->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN movement_type = 'credit' THEN amount
                WHEN movement_type = 'debit' THEN -amount
                ELSE 0
            END
        ), 0)
        FROM project_wallet_movements
        WHERE project_id = ?
          AND status = 'applied'
    ");
    $stmt->execute([$projectId]);

    return (float)$stmt->fetchColumn();
}

function projectCoreSettings(?PDO $corePdo, array $keys): array
{
    if (!$corePdo || empty($keys)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $corePdo->prepare("
        SELECT setting_key, setting_value
        FROM core_settings
        WHERE setting_key IN ($placeholders)
    ");
    $stmt->execute($keys);

    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
    }

    return $settings;
}

function projectCoreWalletRequests(?PDO $corePdo, int $projectId, ?int $limit = 20): array
{
    if (!$corePdo || $projectId <= 0) {
        return [];
    }

    $sql = "
        SELECT *
        FROM project_wallet_requests
        WHERE project_id = ?
        ORDER BY created_at DESC, id DESC
    ";

    if ($limit !== null && $limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $corePdo->prepare($sql);
    $stmt->execute([$projectId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function projectCoreWalletMovements(?PDO $corePdo, int $projectId, ?int $limit = 50): array
{
    if (!$corePdo || $projectId <= 0) {
        return [];
    }

    $sql = "
        SELECT *
        FROM project_wallet_movements
        WHERE project_id = ?
        ORDER BY created_at DESC, id DESC
    ";

    if ($limit !== null && $limit > 0) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $corePdo->prepare($sql);
    $stmt->execute([$projectId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
