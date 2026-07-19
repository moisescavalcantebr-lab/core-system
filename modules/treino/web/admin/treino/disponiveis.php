<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function trainingAvailableEnsureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_roster (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player_id INT NOT NULL,
            team_key ENUM('time_1','time_2') NOT NULL DEFAULT 'time_1',
            status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve',
            slot_group VARCHAR(40) NULL,
            slot_index INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_training_player (player_id),
            INDEX(team_key),
            INDEX(status),
            INDEX(slot_group, slot_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("ALTER TABLE training_roster MODIFY COLUMN status ENUM('field','reserve','inactive') NOT NULL DEFAULT 'reserve'");
}

function trainingAvailableInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $upper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    return $upper($substr($parts[0] ?? '', 0, 1) . $substr($parts[1] ?? '', 0, 1));
}

function trainingAvailableAvatarHtml(?string $avatar, string $name): string
{
    if ($avatar !== null && trim($avatar) !== '') {
        $src = PROJECT_URL . '/' . ltrim($avatar, '/');
        return '<img class="c-training-available-avatar" src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($name) . '">';
    }

    return '<span class="c-training-available-avatar">' . htmlspecialchars(trainingAvailableInitials($name)) . '</span>';
}

function trainingAvailablePositionDisplay(?string $code): string
{
    return preg_replace('/\d+$/', '', (string)$code) ?: '-';
}

trainingAvailableEnsureSchema($pdo);

$players = $pdo->query("
    SELECT
        p.id,
        tr.id AS training_id,
        tr.status AS training_status,
        tr.team_key,
        COALESCE(NULLIF(p.nickname, ''), LEFT(SUBSTRING_INDEX(p.name, ' ', 1), 14)) AS player_name,
        COALESCE(p.avatar, u.avatar) AS avatar,
        pp.code AS position_code,
        pp.name AS position_name
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    LEFT JOIN training_roster tr ON tr.player_id = p.id
    WHERE p.status = 'active'
    ORDER BY pp.sort_order ASC, p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Jogadores do treino';
ob_start();
?>

<div class="c-page c-training-available-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Jogadores do treino</h1>
            <p class="c-page-subtitle">Controle quais jogadores ativos aparecem na tela de treino.</p>
        </div>
        <div class="c-page-actions">
            <a href="<?= PROJECT_URL ?>/admin/treino/index.php" class="c-btn-secondary">Treino</a>
            <a href="<?= PROJECT_URL ?>/admin/treino/config.php" class="c-btn-secondary">Posições do treino</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <section class="c-card c-training-available-section">
            <div class="c-training-available-head">
                <h2>Jogadores ativos</h2>
                <span><?= count($players) ?></span>
            </div>

            <?php if (!$players): ?>
                <p class="c-training-available-empty">Nenhum jogador ativo cadastrado.</p>
            <?php else: ?>
                <div class="c-training-available-grid">
                    <?php foreach ($players as $player): ?>
                        <?php
                            $trainingStatus = (string)($player['training_status'] ?? '');
                            $isHidden = $trainingStatus === 'inactive';
                            $actionId = $isHidden ? (int)$player['training_id'] : (int)$player['id'];
                        ?>
                        <article class="c-training-available-card<?= $isHidden ? ' is-hidden' : '' ?>">
                            <?= trainingAvailableAvatarHtml($player['avatar'] ?? null, (string)$player['player_name']) ?>
                            <div>
                                <strong><?= htmlspecialchars((string)$player['player_name']) ?></strong>
                                <span><?= htmlspecialchars(trainingAvailablePositionDisplay($player['position_code'] ?? null)) ?></span>
                            </div>
                            <form action="<?= PROJECT_URL ?>/admin/treino/toggle.php?id=<?= $actionId ?>" method="POST">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="return" value="disponiveis">
                                <?php if (!$isHidden): ?>
                                    <input type="hidden" name="source" value="player">
                                <?php endif; ?>
                                <input type="hidden" name="target" value="<?= $isHidden ? 'active' : 'inactive' ?>">
                                <button class="c-training-visibility-btn <?= $isHidden ? 'is-activate' : 'is-hide' ?>">
                                    <?= $isHidden ? 'Ativar' : 'Ocultar' ?>
                                </button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<style>
.c-training-available-page .c-page-content {
    display: grid;
    gap: 14px;
}

.c-training-available-section {
    display: grid;
    gap: 10px;
}

.c-training-available-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.c-training-available-head h2 {
    margin: 0;
    font-size: 15px;
}

.c-training-available-head span {
    color: #9fb1c7;
    font-size: 12px;
}

.c-training-available-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
}

.c-training-available-card {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr) auto;
    gap: 6px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, .22);
    padding: 6px;
    background: rgba(15, 23, 42, .35);
}

.c-training-available-card.is-hidden {
    opacity: .76;
}

.c-training-available-avatar {
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border: 1px solid #38bdf8;
    border-radius: 999px;
    object-fit: cover;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    background: linear-gradient(135deg, #34d399, #2563eb);
}

.c-training-available-card strong,
.c-training-available-card span {
    display: block;
}

.c-training-available-card strong {
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.c-training-available-card span {
    color: #9fb1c7;
    font-size: 9px;
}

.c-training-available-card form {
    margin: 0;
}

.c-training-available-card button {
    min-height: 24px;
    padding: 3px 7px;
    font-size: 10px;
    white-space: nowrap;
}

.c-training-visibility-btn {
    border-radius: 5px;
    cursor: pointer;
}

.c-training-visibility-btn.is-hide {
    border: 1px solid rgba(245, 158, 11, .45);
    background: rgba(120, 53, 15, .55);
    color: #fbbf24;
}

.c-training-visibility-btn.is-activate {
    border: 1px solid rgba(34, 197, 94, .45);
    background: rgba(20, 83, 45, .58);
    color: #86efac;
}

.c-training-available-empty {
    margin: 0;
    color: #b8c7da;
}

@media (max-width: 980px) {
    .c-training-available-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .c-training-available-grid {
        grid-template-columns: 1fr;
    }

    .c-training-available-card {
        grid-template-columns: 30px minmax(0, 1fr) auto;
    }
}
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../app/views/layout_admin.php';
