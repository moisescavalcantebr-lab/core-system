<?php
declare(strict_types=1);

function coreWalletMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function coreWalletBalance(PDO $pdo, int $projectId): float
{
    if ($projectId <= 0) {
        return 0.0;
    }

    $stmt = $pdo->prepare("
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

function coreWalletBalances(PDO $pdo, array $projectIds): array
{
    $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds))));
    if (empty($projectIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
    $stmt = $pdo->prepare("
        SELECT project_id,
               COALESCE(SUM(
                   CASE
                       WHEN movement_type = 'credit' THEN amount
                       WHEN movement_type = 'debit' THEN -amount
                       ELSE 0
                   END
               ), 0) AS balance
        FROM project_wallet_movements
        WHERE status = 'applied'
          AND project_id IN ($placeholders)
        GROUP BY project_id
    ");
    $stmt->execute($projectIds);

    $balances = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $balances[(int)$row['project_id']] = (float)$row['balance'];
    }

    foreach ($projectIds as $projectId) {
        $balances[$projectId] ??= 0.0;
    }

    return $balances;
}
