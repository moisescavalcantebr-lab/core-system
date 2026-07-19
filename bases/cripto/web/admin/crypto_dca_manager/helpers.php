<?php
declare(strict_types=1);

function cryptoDcaMoney(float $value): string
{
    return 'US$ ' . number_format($value, 2, ',', '.');
}

function cryptoDcaPercent(float $value): string
{
    return number_format($value, 2, ',', '.') . '%';
}

function cryptoDcaDecimal(string $value): float
{
    $value = trim($value);
    $value = str_replace(['US$', '$', ' '], '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);

    return is_numeric($value) ? round((float)$value, 8) : 0.0;
}

function cryptoDcaSlug(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'item-' . substr(md5((string)microtime(true)), 0, 8);
}

function cryptoDcaEnsureSchema(PDO $pdo): void
{
    $schemaPath = dirname(__DIR__, 3) . '/database/schema.sql';

    if (is_file($schemaPath)) {
        $pdo->exec((string)file_get_contents($schemaPath));
    }
}

function cryptoDcaWallets(PDO $pdo, bool $activeOnly = true): array
{
    $where = $activeOnly ? "WHERE status = 'active'" : '';

    return $pdo->query("SELECT * FROM crypto_wallets {$where} ORDER BY status = 'active' DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function cryptoDcaGroups(PDO $pdo, bool $activeOnly = true): array
{
    $where = $activeOnly ? "WHERE status = 'active'" : '';

    return $pdo->query("SELECT * FROM crypto_groups {$where} ORDER BY status = 'active' DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function cryptoDcaStrategyStatusOptions(): array
{
    return [
        'em_observacao' => 'Em observacao',
        'validado' => 'Validado',
        'top_5' => 'Top 5',
        'x2_recuperacao' => 'X2 / Recuperacao',
        'venda' => 'Venda',
        'removido' => 'Removido',
    ];
}

function cryptoDcaStrategyStatusLabel(string $status): string
{
    return cryptoDcaStrategyStatusOptions()[$status] ?? 'Em observacao';
}

function cryptoDcaRiskOptions(): array
{
    return [
        'low' => 'Baixo',
        'medium' => 'Medio',
        'high' => 'Alto',
        'watch' => 'Observacao',
    ];
}

function cryptoDcaRecommendationOptions(): array
{
    return [
        'manter' => 'Manter',
        'top_5' => 'Subir para Top 5',
        'x2' => 'Mover para X2',
        'venda' => 'Colocar em venda',
        'remover' => 'Remover',
    ];
}

function cryptoDcaFetchAssets(PDO $pdo, string $where = '', array $params = []): array
{
    $sql = "
        SELECT a.*, w.name AS wallet_name, g.name AS group_name
        FROM crypto_assets a
        INNER JOIN crypto_wallets w ON w.id = a.wallet_id
        INNER JOIN crypto_groups g ON g.id = a.group_id
        {$where}
        ORDER BY a.status = 'active' DESC, a.strategy_status, a.symbol
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function cryptoDcaDashboardSummary(PDO $pdo): array
{
    $assetCounts = $pdo->query("
        SELECT
            COUNT(CASE WHEN status = 'active' THEN 1 END) AS active_assets,
            COUNT(CASE WHEN strategy_status = 'em_observacao' AND status = 'active' THEN 1 END) AS watch_assets,
            COUNT(CASE WHEN strategy_status = 'top_5' AND status = 'active' THEN 1 END) AS top_assets,
            COUNT(CASE WHEN strategy_status = 'x2_recuperacao' AND status = 'active' THEN 1 END) AS x2_assets
        FROM crypto_assets
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $cycleTotals = $pdo->query("
        SELECT
            COALESCE(SUM(total_allocated), 0) AS capital_allocated,
            COALESCE(SUM(CASE WHEN status = 'open' THEN total_allocated ELSE 0 END), 0) AS capital_risk,
            COALESCE(SUM(profit_amount), 0) AS profit_total,
            COUNT(CASE WHEN status = 'closed' THEN 1 END) AS closed_cycles
        FROM crypto_cycles
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $bestAsset = $pdo->query("
        SELECT a.symbol, COALESCE(SUM(c.profit_amount), 0) AS profit
        FROM crypto_assets a
        LEFT JOIN crypto_cycles c ON c.asset_id = a.id
        GROUP BY a.id
        ORDER BY profit DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    $worstAsset = $pdo->query("
        SELECT a.symbol, COALESCE(SUM(c.profit_amount), 0) AS profit
        FROM crypto_assets a
        LEFT JOIN crypto_cycles c ON c.asset_id = a.id
        GROUP BY a.id
        ORDER BY profit ASC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    $bestGroup = $pdo->query("
        SELECT g.name, COALESCE(SUM(c.profit_amount), 0) AS profit
        FROM crypto_groups g
        LEFT JOIN crypto_cycles c ON c.group_id = g.id
        GROUP BY g.id
        ORDER BY profit DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    $worstGroup = $pdo->query("
        SELECT g.name, COALESCE(SUM(c.profit_amount), 0) AS profit
        FROM crypto_groups g
        LEFT JOIN crypto_cycles c ON c.group_id = g.id
        GROUP BY g.id
        ORDER BY profit ASC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;

    return array_merge([
        'active_assets' => 0,
        'watch_assets' => 0,
        'top_assets' => 0,
        'x2_assets' => 0,
        'capital_allocated' => 0,
        'capital_risk' => 0,
        'profit_total' => 0,
        'closed_cycles' => 0,
        'best_asset' => $bestAsset,
        'worst_asset' => $worstAsset,
        'best_group' => $bestGroup,
        'worst_group' => $worstGroup,
    ], $assetCounts, $cycleTotals);
}

function cryptoDcaRecalculateCycle(PDO $pdo, int $cycleId): void
{
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN entry_type IN ('initial','dca','adjustment') THEN amount_usd ELSE 0 END), 0) AS allocated,
            COALESCE(SUM(CASE WHEN entry_type IN ('initial','dca','adjustment') THEN quantity ELSE 0 END), 0) AS quantity_total,
            COUNT(CASE WHEN entry_type = 'dca' THEN 1 END) AS dca_count
        FROM crypto_entries
        WHERE cycle_id = ?
    ");
    $stmt->execute([$cycleId]);
    $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $allocated = (float)($totals['allocated'] ?? 0);
    $quantity = (float)($totals['quantity_total'] ?? 0);
    $average = $quantity > 0 ? $allocated / $quantity : null;
    $dcaCount = (int)($totals['dca_count'] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE crypto_cycles
        SET total_allocated = ?, average_price = ?, dca_count = ?
        WHERE id = ?
    ");
    $stmt->execute([$allocated, $average, $dcaCount, $cycleId]);

    $stmt = $pdo->prepare("
        UPDATE crypto_assets a
        INNER JOIN crypto_cycles c ON c.asset_id = a.id
        SET a.current_dca_count = c.dca_count
        WHERE c.id = ?
    ");
    $stmt->execute([$cycleId]);
}

function cryptoDcaRequireAdmin(): void
{
    requireProjectRole(['ADMIN']);
}
