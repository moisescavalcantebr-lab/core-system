<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

$title = 'Jogadores';
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'active'")->fetchColumn();
$inactiveCount = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'inactive'")->fetchColumn();
$playerAccessAvailable = playerAccessFeatureEnabled();
$publicRegistrationEnabled = $playerAccessAvailable && getSetting('player_public_registration_enabled', '0') === '1';
$publicRegistrationUrl = PROJECT_URL . '/cadastro-jogador.php';

$stmt = $pdo->query("
    SELECT p.id, COALESCE(u.name, p.name) AS name, p.nickname, p.whatsapp, p.position, p.roster_status, p.shirt_number, p.status, p.created_at,
        pp.name AS position_name,
        pp.code AS position_code,
        p.user_id,
        COALESCE(p.avatar, u.avatar) AS avatar,
        u.username,
        u.role AS user_role,
        u.status AS user_status
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.status = 'active'
    ORDER BY name ASC
");
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
$rankingByPlayer = [];

try {
    $hasAttendance = (bool)$pdo->query("SHOW TABLES LIKE 'match_attendance'")->fetchColumn();

    if ($hasAttendance) {
        $rankingRows = $pdo->query("
            SELECT
                p.id,
                p.birth_date,
                COALESCE((
                    SELECT SUM(ma.points)
                    FROM match_attendance ma
                    INNER JOIN matches m ON m.id = ma.match_id
                    WHERE ma.player_id = p.id
                      AND m.status = 'finished'
                ), 0) AS points,
                COALESCE((
                    SELECT COUNT(*)
                    FROM match_attendance ma
                    INNER JOIN matches m ON m.id = ma.match_id
                    WHERE ma.player_id = p.id
                      AND ma.status = 'present'
                      AND m.status = 'finished'
                ), 0) AS presences
            FROM players p
            WHERE p.status = 'active'
            ORDER BY points DESC, p.birth_date IS NULL ASC, p.birth_date ASC, p.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rankingRows as $index => $row) {
            $rankingByPlayer[(int)$row['id']] = $index + 1;
        }
    }
} catch (Throwable $e) {
    $rankingByPlayer = [];
}

function playerIndexInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $upper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';

    return $upper($substr($parts[0] ?? 'J', 0, 1) . $substr($parts[1] ?? '', 0, 1));
}

function playerIndexAvatar(?string $avatar, string $name): string
{
    if ($avatar !== null && trim($avatar) !== '') {
        return '<img class="c-player-card-avatar" src="' . htmlspecialchars(PROJECT_URL . '/' . ltrim($avatar, '/')) . '" alt="' . htmlspecialchars($name) . '">';
    }

    return '<span class="c-player-card-avatar">' . htmlspecialchars(playerIndexInitials($name)) . '</span>';
}

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Resumo</h3>
    <p>Use Ver para abrir a ficha completa do jogador.</p>
    <p>Ativos: ' . $activeCount . '/' . playerActiveLimit() . '<br>Inativos: ' . $inactiveCount . '<br>Acesso do jogador: ' . ($playerAccessAvailable ? 'Start ativo' : 'Disponivel no Start') . '</p>
</div>
';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Jogadores</h1>
            <p class="c-page-subtitle">Gerencie o elenco do projeto · <?= $activeCount ?>/<?= playerActiveLimit() ?> ativos</p>
        </div>

        <div class="c-player-header-actions">
            <a href="<?= PROJECT_URL ?>/admin/jogadores/create.php" class="c-btn-secondary">
                Novo Jogador
            </a>

            <?php if ($playerAccessAvailable): ?>
                <form action="<?= PROJECT_URL ?>/admin/jogadores/registration_toggle.php" method="POST">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="enabled" value="<?= $publicRegistrationEnabled ? '0' : '1' ?>">
                    <button type="submit" class="c-btn-secondary">
                        <?= $publicRegistrationEnabled ? 'Bloquear Cadastro' : 'Liberar Cadastro' ?>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($publicRegistrationEnabled): ?>
                <a href="<?= htmlspecialchars($publicRegistrationUrl) ?>" class="c-btn-secondary" target="_blank">
                    Link de Cadastro
                </a>
            <?php endif; ?>

            <a href="<?= PROJECT_URL ?>/admin/jogadores/inativos.php" class="c-btn-secondary">
                Inativos: <?= $inactiveCount ?>
            </a>

            <a href="<?= PROJECT_URL ?>/admin/jogadores/positions.php" class="c-btn-secondary">
                Posições
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-player-list-section">

            <?php if (empty($players)): ?>

                <p>Nenhum jogador ativo cadastrado.</p>

            <?php else: ?>

                <div class="c-player-list-grid">
                    <?php foreach ($players as $player): ?>
                        <?php
                            $positionCode = preg_replace('/\d+$/', '', (string)($player['position_code'] ?? '')) ?: '-';
                            $shirtNumber = $player['shirt_number'] ? (string)$player['shirt_number'] : '-';
                            $position = trim((string)($player['position_code'] ?? '') . ' ' . (string)($player['position_name'] ?? $player['position'] ?? '-'));
                            $rosterStatus = (string)($player['roster_status'] ?? 'titular');
                            $displayName = playerDisplayName($player['nickname'] ?? null, (string)$player['name']);
                        ?>
                        <div class="c-player-row-card c-player-row-card--<?= htmlspecialchars($rosterStatus) ?>" title="<?= htmlspecialchars($position !== '' ? $position : '-') ?>">
                            <?php if (isset($rankingByPlayer[(int)$player['id']])): ?>
                                <span class="c-player-ranking-badge">
                                    #<?= (int)$rankingByPlayer[(int)$player['id']] ?>
                                </span>
                            <?php endif; ?>
                            <div class="c-player-card-main">
                                <div class="c-player-card-avatar-wrap">
                                    <?= playerIndexAvatar($player['avatar'] ?? null, $displayName) ?>
                                </div>

                                <div class="c-player-card-meta">
                                    <span class="c-player-access-badge c-player-access-badge--<?= htmlspecialchars(playerAccessBadge(!empty($player['user_id']) ? (int)$player['user_id'] : null, $player['user_status'] ?? null)) ?>">
                                        <?= htmlspecialchars(playerAccessLabel(!empty($player['user_id']) ? (int)$player['user_id'] : null, $player['user_status'] ?? null)) ?>
                                    </span>
                                    <span class="c-player-shirt-chip"><?= htmlspecialchars($shirtNumber) ?></span>
                                    <span class="c-player-position-chip"><?= htmlspecialchars($positionCode) ?></span>
                                </div>
                            </div>

                            <strong title="<?= htmlspecialchars((string)$player['name']) ?>"><?= htmlspecialchars($displayName) ?></strong>

                            <div class="c-player-card-actions">
                                <a href="<?= PROJECT_URL ?>/admin/jogadores/show.php?id=<?= (int)$player['id'] ?>" class="c-btn-secondary">
                                    Ver
                                </a>

                                <form action="<?= PROJECT_URL ?>/admin/jogadores/toggle.php?id=<?= (int)$player['id'] ?>" method="POST">
                                    <?= csrf_field(); ?>
                                    <button type="submit" class="c-btn-secondary">
                                        Desativar
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<style>
.c-player-list-grid {
    display: grid;
    grid-template-columns: repeat(10, 102px);
    align-items: start;
    gap: 8px;
}

.c-player-header-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.c-player-header-actions form {
    margin: 0;
}

.c-player-list-section {
    padding: 0;
}

.c-player-row-card {
    position: relative;
    display: grid;
    width: 102px;
    gap: 4px;
    justify-items: center;
    border: 1px solid rgba(148, 163, 184, .28);
    background: var(--bg-sidebar);
    padding: 22px 6px 6px;
    min-width: 0;
    min-height: 122px;
    text-align: center;
    box-shadow: 0 10px 22px rgba(0, 0, 0, .08);
}

.c-player-row-card--titular {
    border-color: rgba(34, 197, 94, .56);
    box-shadow: inset 3px 0 0 rgba(34, 197, 94, .55), 0 10px 22px rgba(0, 0, 0, .08);
}

.c-player-row-card--reserva {
    border-color: rgba(245, 158, 11, .58);
    box-shadow: inset 3px 0 0 rgba(245, 158, 11, .62), 0 10px 22px rgba(0, 0, 0, .08);
}

.c-player-ranking-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 17px;
    padding: 0 4px;
    border: 1px solid rgba(96, 165, 250, .36);
    background: rgba(59, 130, 246, .16);
    color: #dbeafe;
    font-size: 9px;
    font-weight: 900;
    line-height: 1;
}

.c-player-access-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 17px;
    padding: 0 5px;
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .72);
    color: rgba(226, 232, 240, .82);
    font-size: 9px;
    font-weight: 800;
    line-height: 1;
    width: 100%;
}

.c-player-access-badge--success {
    border-color: rgba(34, 197, 94, .28);
    background: rgba(34, 197, 94, .14);
    color: #bbf7d0;
}

.c-player-card-main {
    display: grid;
    grid-template-columns: 42px minmax(0, 1fr);
    align-items: center;
    gap: 6px;
    width: 100%;
    min-width: 0;
}

.c-player-card-avatar-wrap {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
}

.c-player-card-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,.78);
    overflow: hidden;
    object-fit: cover;
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    background:
        radial-gradient(circle at 35% 28%, rgba(255,255,255,.95) 0 9px, transparent 10px),
        linear-gradient(145deg, #f7d4bf 0 45%, #2f7c44 46% 100%);
}

.c-player-row-card > strong {
    display: block;
    width: 100%;
    font-size: 10px;
    line-height: 1.15;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-player-card-meta {
    display: grid;
    grid-template-columns: 1fr;
    justify-content: stretch;
    align-items: center;
    gap: 4px;
    width: 100%;
    min-width: 0;
}

.c-player-position-chip,
.c-player-shirt-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    min-height: 17px;
    padding: 1px 3px;
    border: 1px solid rgba(148, 163, 184, .26);
    background: rgba(255,255,255,.04);
    font-size: 8.5px;
    font-weight: 700;
}

.c-player-card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
    width: 100%;
}

.c-player-card-actions form {
    margin: 0;
}

.c-player-card-actions .c-btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 22px;
    min-height: 22px;
    padding: 3px 2px;
    font-size: 9.5px;
    line-height: 1;
}

@media (max-width: 900px) {
    .c-player-header-actions {
        justify-content: flex-start;
        margin-left: 0;
    }

    .c-player-list-grid {
        grid-template-columns: repeat(auto-fit, minmax(102px, 1fr));
        gap: 10px;
    }

    .c-right-sidebar .c-card {
        margin-top: 0;
    }
}

@media (min-width: 901px) and (max-width: 1180px) {
    .c-player-list-grid {
        grid-template-columns: repeat(auto-fit, 102px);
        gap: 10px;
    }
}

@media (max-width: 520px) {
    .c-player-list-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .c-player-row-card {
        width: auto;
    }
}

@media (max-width: 360px) {
    .c-player-list-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
