<?php
declare(strict_types=1);

function tipsRequireAdmin(): void
{
    requireProjectRole(['ADMIN']);
}

function tipsRequireUser(): void
{
    requireProjectAuth();
}

function tipsEnsureSchema(PDO $pdo): void
{
    $schemaPaths = [];

    if (defined('PROJECT_PATH')) {
        $schemaPaths[] = PROJECT_PATH . '/modules/tips_survivor/database/schema.sql';
    }

    $schemaPaths[] = dirname(__DIR__, 3) . '/database/schema.sql';

    foreach ($schemaPaths as $schemaPath) {
        if (is_file($schemaPath)) {
            $pdo->exec((string)file_get_contents($schemaPath));
            return;
        }
    }
}

function tipsSlug(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'tips-' . substr(md5((string)microtime(true)), 0, 8);
}

function tipsStatusLabel(string $status): string
{
    return [
        'draft' => 'Rascunho',
        'open' => 'Aberta',
        'active' => 'Ativa',
        'finished' => 'Finalizada',
        'cancelled' => 'Cancelada',
        'scheduled' => 'Agendada',
        'locked' => 'Bloqueada',
        'processed' => 'Processada',
        'free' => 'Inicial',
        'start' => 'Ativa',
        'eliminated' => 'Eliminado',
        'champion' => 'Campeao',
    ][$status] ?? ucfirst($status);
}

function tipsBadgeClass(string $status): string
{
    return match ($status) {
        'open', 'active', 'start', 'champion', 'processed' => 'c-badge--success',
        'finished', 'scheduled' => 'c-badge--neutral',
        'locked' => 'c-badge--info',
        'cancelled', 'eliminated' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function tipsDashboardSummary(PDO $pdo): array
{
    $competitions = $pdo->query("
        SELECT
            COUNT(*) AS total_competitions,
            COUNT(CASE WHEN status IN ('open','active') THEN 1 END) AS active_competitions,
            COUNT(CASE WHEN status = 'finished' THEN 1 END) AS finished_competitions
        FROM tips_competitions
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $matches = $pdo->query("
        SELECT
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) AS scheduled_matches,
            COUNT(CASE WHEN status = 'finished' THEN 1 END) AS matches_to_process,
            COUNT(CASE WHEN status = 'processed' THEN 1 END) AS processed_matches
        FROM tips_matches
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $wallets = $pdo->query("
        SELECT
            COUNT(CASE WHEN status = 'start' THEN 1 END) AS start_users,
            COUNT(CASE WHEN status = 'free' THEN 1 END) AS free_users,
            COALESCE(SUM(tokens), 0) AS tokens_total
        FROM tips_user_wallets
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $participants = $pdo->query("
        SELECT
            COUNT(CASE WHEN status = 'active' THEN 1 END) AS active_players,
            COUNT(CASE WHEN status = 'eliminated' THEN 1 END) AS eliminated_players,
            COUNT(CASE WHEN status = 'champion' THEN 1 END) AS champions
        FROM tips_competition_users
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    return array_merge([
        'total_competitions' => 0,
        'active_competitions' => 0,
        'finished_competitions' => 0,
        'scheduled_matches' => 0,
        'matches_to_process' => 0,
        'processed_matches' => 0,
        'start_users' => 0,
        'free_users' => 0,
        'tokens_total' => 0,
        'active_players' => 0,
        'eliminated_players' => 0,
        'champions' => 0,
    ], $competitions, $matches, $wallets, $participants);
}

function tipsRecentCompetitions(PDO $pdo, int $limit = 8): array
{
    $stmt = $pdo->prepare("
        SELECT c.*,
               COUNT(cu.id) AS participants_count,
               COUNT(CASE WHEN cu.status = 'active' THEN 1 END) AS active_count
        FROM tips_competitions c
        LEFT JOIN tips_competition_users cu ON cu.competition_id = c.id
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tipsCompetitionsForSelect(PDO $pdo): array
{
    return $pdo->query("
        SELECT id, name, season, status
        FROM tips_competitions
        WHERE status IN ('draft', 'open', 'active')
        ORDER BY FIELD(status, 'active', 'open', 'draft'), created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function tipsSettings(PDO $pdo): array
{
    $rows = $pdo->query("SELECT setting_key, setting_value FROM tips_settings")->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];

    foreach ($rows as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }

    return array_merge([
        'initial_lives' => '3',
        'max_lives' => '5',
        'points_per_extra_life' => '1000',
        'tokens_on_start' => '30',
        'token_consumption_mode' => 'per_round',
        'token_consumption_amount' => '1',
        'champion_reward_tokens' => '30',
    ], $settings);
}

function tipsEnsureUserWallet(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM tips_user_wallets WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($wallet) {
        return $wallet;
    }

    $settings = tipsSettings($pdo);
    $initialTokens = max(0, (int)$settings['tokens_on_start']);

    $stmt = $pdo->prepare("
        INSERT INTO tips_user_wallets (user_id, tokens, status, first_start_activated_at, last_start_activated_at)
        VALUES (?, ?, 'free', NULL, NULL)
    ");
    $stmt->execute([$userId, $initialTokens]);

    if ($initialTokens > 0) {
        $tx = $pdo->prepare("
            INSERT INTO tips_token_transactions (user_id, amount, type, description)
            VALUES (?, ?, 'start_bonus', ?)
        ");
        $tx->execute([$userId, $initialTokens, 'Saldo inicial do Tips Survivor']);
    }

    $stmt = $pdo->prepare('SELECT * FROM tips_user_wallets WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function tipsRefreshWalletStatus(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('SELECT * FROM tips_user_wallets WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        return;
    }

    $active = $pdo->prepare("
        SELECT COUNT(*)
        FROM tips_competition_users cu
        INNER JOIN tips_competitions c ON c.id = cu.competition_id
        WHERE cu.user_id = ?
          AND cu.status = 'active'
          AND c.status IN ('open', 'active')
    ");
    $active->execute([$userId]);

    $activeCount = (int)$active->fetchColumn();
    $tokens = (int)($wallet['tokens'] ?? 0);
    $hasStarted = !empty($wallet['first_start_activated_at']);
    $status = ($hasStarted && ($activeCount > 0 || $tokens > 0)) ? 'start' : 'free';

    if ($activeCount === 0 && $tokens <= 0) {
        $status = 'free';
    }

    if ($status !== (string)$wallet['status']) {
        $update = $pdo->prepare('UPDATE tips_user_wallets SET status = ?, updated_at = NOW() WHERE user_id = ?');
        $update->execute([$status, $userId]);
    }
}

function tipsUserActiveCompetition(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT c.*, cu.lives, cu.points, cu.status AS participant_status
        FROM tips_competition_users cu
        INNER JOIN tips_competitions c ON c.id = cu.competition_id
        WHERE cu.user_id = ?
          AND cu.status = 'active'
          AND c.status IN ('open', 'active')
        ORDER BY cu.joined_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function tipsOpenCompetitionsForUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT c.*,
               cu.id AS joined_id,
               cu.status AS participant_status,
               COUNT(all_users.id) AS participants_count
        FROM tips_competitions c
        LEFT JOIN tips_competition_users cu
            ON cu.competition_id = c.id AND cu.user_id = ?
        LEFT JOIN tips_competition_users all_users
            ON all_users.competition_id = c.id
        WHERE c.status = 'open'
          AND (c.starts_at IS NULL OR c.starts_at > NOW())
        GROUP BY c.id, cu.id, cu.status
        ORDER BY c.starts_at IS NULL ASC, c.starts_at ASC, c.created_at DESC
    ");
    $stmt->execute([$userId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tipsJoinCompetition(PDO $pdo, int $competitionId, int $userId): string
{
    $active = tipsUserActiveCompetition($pdo, $userId);

    if ($active) {
        return 'Voce ja esta em uma competicao ativa.';
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM tips_competitions
        WHERE id = ?
          AND status = 'open'
          AND (starts_at IS NULL OR starts_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$competition) {
        return 'Competicao indisponivel para entrada.';
    }

    $wallet = tipsEnsureUserWallet($pdo, $userId);
    $entryCost = max(1, (int)$competition['token_consumption_amount']);

    if ((int)($wallet['tokens'] ?? 0) < $entryCost) {
        return 'Tokens insuficientes para entrar nesta competicao.';
    }

    $exists = $pdo->prepare('SELECT COUNT(*) FROM tips_competition_users WHERE competition_id = ? AND user_id = ?');
    $exists->execute([$competitionId, $userId]);

    if ((int)$exists->fetchColumn() > 0) {
        return 'Voce ja entrou nesta competicao.';
    }

    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare("
            INSERT INTO tips_competition_users (competition_id, user_id, lives, points, status, joined_at)
            VALUES (?, ?, ?, 0, 'active', NOW())
        ");
        $insert->execute([$competitionId, $userId, (int)$competition['initial_lives']]);

        $updateWallet = $pdo->prepare("
            UPDATE tips_user_wallets
            SET tokens = tokens - ?,
                status = 'start',
                first_start_activated_at = COALESCE(first_start_activated_at, NOW()),
                last_start_activated_at = NOW(),
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $updateWallet->execute([$entryCost, $userId]);

        $tx = $pdo->prepare("
            INSERT INTO tips_token_transactions (user_id, competition_id, amount, type, description)
            VALUES (?, ?, ?, 'consumption', ?)
        ");
        $tx->execute([$userId, $competitionId, -$entryCost, 'Entrada na competicao ' . (string)$competition['name']]);

        $pdo->commit();

        return '';
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Nao foi possivel entrar na competicao.';
    }
}

function tipsAwardChampionTokens(PDO $pdo, int $userId, int $competitionId): void
{
    $settings = tipsSettings($pdo);
    $reward = max(0, (int)$settings['champion_reward_tokens']);

    if ($reward <= 0) {
        return;
    }

    tipsEnsureUserWallet($pdo, $userId);

    $wallet = $pdo->prepare("
        UPDATE tips_user_wallets
        SET tokens = tokens + ?,
            status = 'start',
            first_start_activated_at = COALESCE(first_start_activated_at, NOW()),
            updated_at = NOW()
        WHERE user_id = ?
    ");
    $wallet->execute([$reward, $userId]);

    $tx = $pdo->prepare("
        INSERT INTO tips_token_transactions (user_id, competition_id, amount, type, description)
        VALUES (?, ?, ?, 'performance_bonus', ?)
    ");
    $tx->execute([$userId, $competitionId, $reward, 'Premio do campeao']);
}

function tipsRecentMatches(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS competition_name
        FROM tips_matches m
        INNER JOIN tips_competitions c ON c.id = m.competition_id
        ORDER BY m.match_datetime DESC
        LIMIT {$limit}
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function tipsNav(string $active): string
{
    $items = [
        'index' => ['Dashboard', 'index.php'],
        'player' => ['Minha Area', 'player.php'],
        'competitions' => ['Competicoes', 'competitions.php'],
        'matches' => ['Partidas', 'matches.php'],
        'ranking' => ['Ranking', 'ranking.php'],
        'tokens' => ['Tokens', 'tokens.php'],
        'settings' => ['Configuracoes', 'settings.php'],
    ];

    ob_start();
    ?>
    <div class="tips-tabs">
        <?php foreach ($items as $key => [$label, $file]): ?>
            <a class="c-btn-secondary <?= $active === $key ? 'is-active' : '' ?>"
               href="<?= PROJECT_URL ?>/admin/tips_survivor/<?= $file ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </div>
    <?php

    return (string)ob_get_clean();
}
