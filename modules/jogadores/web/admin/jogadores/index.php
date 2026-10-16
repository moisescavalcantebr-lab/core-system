<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';
require __DIR__ . '/card_helpers.php';

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
$formationLabels = playerCardFormationLabels($pdo);

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

$rightSidebarEnabled = false;
$rightSidebarContent = '';

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
                            $shirtNumber = $player['shirt_number'] !== null ? (string)$player['shirt_number'] : '-';
                            $displayName = playerDisplayName($player['nickname'] ?? null, (string)$player['name']);
                            $formation = $formationLabels === null ? 'Sem formação definida' : ($formationLabels[(int)$player['id']] ?? 'Disponível');
                            $profileUrl = PROJECT_URL . '/admin/jogadores/show.php?id=' . (int)$player['id'];
                        ?>
                        <article class="c-player-row-card" data-player-id="<?= (int)$player['id'] ?>">
                            <header class="c-player-card-top">
                                <span class="c-player-active"><span aria-hidden="true"></span>Ativo</span>
                                <span class="c-player-shirt"><small>Camisa</small><strong><?= htmlspecialchars($shirtNumber) ?></strong></span>
                            </header>
                            <a href="<?= htmlspecialchars($profileUrl) ?>" class="c-player-card-identity" aria-label="Ver perfil de <?= htmlspecialchars($displayName) ?>">
                                <?= playerIndexAvatar($player['avatar'] ?? null, $displayName) ?>
                                <h2 title="<?= htmlspecialchars((string)$player['name']) ?>"><?= htmlspecialchars($displayName) ?></h2>
                                <span class="c-player-card-position"><?= htmlspecialchars(playerCardPosition($player)) ?><?php if ($positionCode !== '-'): ?><span class="c-player-position-code"> · <?= htmlspecialchars($positionCode) ?></span><?php endif; ?></span>
                            </a>
                            <div class="c-player-formation" title="<?= htmlspecialchars($formation) ?>"><?= htmlspecialchars($formation) ?></div>
                            <div class="c-player-participation" data-ranking-state="pending" title="Ranking de participação ainda não calculado">
                                <span>Ranking de participação</span>
                                <strong aria-label="Ainda não calculado">—</strong>
                            </div>
                            <footer class="c-player-card-actions">
                                <a href="<?= htmlspecialchars($profileUrl) ?>" class="c-btn-secondary c-player-profile-link">Ver perfil</a>
                                <details class="c-player-card-options">
                                    <summary class="c-btn-secondary" aria-label="Opções de <?= htmlspecialchars($displayName) ?>" title="Opções do jogador"><span aria-hidden="true">&hellip;</span></summary>
                                    <div class="c-player-card-menu">
                                        <a href="<?= PROJECT_URL ?>/admin/jogadores/edit.php?id=<?= (int)$player['id'] ?>" class="c-btn-secondary">Editar cadastro</a>
                                        <form action="<?= PROJECT_URL ?>/admin/jogadores/toggle.php?id=<?= (int)$player['id'] ?>" method="POST" data-deactivate-player>
                                            <?= csrf_field(); ?>
                                            <button type="submit" class="c-btn-secondary">Desativar jogador</button>
                                        </form>
                                    </div>
                                </details>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<style>
.c-player-header-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 10px; margin-left: auto; }
.c-player-header-actions form { margin: 0; }
.c-player-list-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); align-items: stretch; gap: 14px; }
.c-player-list-section { padding: 0; min-width: 0; }
.c-player-row-card { position: relative; display: grid; grid-template-rows: auto 1fr minmax(36px, auto) auto auto; min-width: 0; min-height: 340px; padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); letter-spacing: 0; }
.c-player-row-card.is-menu-open { z-index: 5; }
.c-player-card-top { display: flex; justify-content: space-between; align-items: start; gap: 8px; min-height: 36px; }
.c-player-active { display: inline-flex; align-items: center; gap: 6px; color: var(--text-secondary); font-size: 12px; }
.c-player-active > span { width: 7px; height: 7px; border-radius: 50%; background: var(--color-success); }
.c-player-shirt { display: grid; justify-items: end; line-height: 1.15; }
.c-player-shirt small { font-size: 11px; color: var(--text-secondary); }
.c-player-shirt strong { font-size: 21px; font-weight: 600; }
.c-player-card-identity { display: grid; grid-template-rows: 80px minmax(44px, auto) minmax(36px, auto); align-content: start; justify-items: center; gap: 8px; min-width: 0; padding-top: 12px; color: inherit; text-decoration: none; text-align: center; }
.c-player-card-identity:hover { color: inherit; }
.c-player-card-avatar { display: grid; place-items: center; width: 80px; height: 80px; border-radius: 50%; background: color-mix(in srgb, var(--color-success) 14%, var(--bg-card)); color: var(--text-primary); object-fit: cover; font-size: 25px; font-weight: 500; }
.c-player-card-identity h2 { align-self: center; width: 100%; margin: 0; font-size: 18px; line-height: 1.25; font-weight: 600; overflow-wrap: anywhere; }
.c-player-card-position { font-size: 12px; line-height: 1.5; color: var(--text-secondary); overflow-wrap: anywhere; }
.c-player-position-code { white-space: nowrap; }
.c-player-formation { display: grid; align-items: center; padding: 6px 0; text-align: center; font-size: 13px; overflow-wrap: anywhere; }
.c-player-participation { display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 48px; margin-top: 8px; padding: 8px 0; border-top: 1px solid var(--border-color); font-size: 11px; color: var(--text-secondary); }
.c-player-participation > span { max-width: 124px; overflow-wrap: anywhere; }
.c-player-participation > strong { font-size: 20px; font-weight: 500; color: var(--text-primary); }
.c-player-card-actions { display: flex; align-items: stretch; gap: 8px; min-width: 0; padding-top: 8px; }
.c-player-profile-link { display: flex; align-items: center; justify-content: center; flex: 1; min-width: 0; text-align: center; font-size: 12px; white-space: normal; overflow-wrap: anywhere; }
.c-player-card-options summary { display: grid; place-items: center; width: 40px; min-height: 40px; padding: 0; list-style: none; cursor: pointer; font-size: 22px; line-height: 1; }
.c-player-card-options summary::-webkit-details-marker { display: none; }
.c-player-card-options:not([open]) > .c-player-card-menu { display: none; }
.c-player-card-menu { position: absolute; left: 10px; right: 10px; bottom: 68px; z-index: 10; display: grid; gap: 6px; padding: 8px; border: 1px solid var(--border-color); border-radius: 5px; background: var(--bg-card); box-shadow: var(--shadow-card); }
.c-player-card-menu form { margin: 0; min-width: 0; }
.c-player-card-menu .c-btn-secondary { display: block; width: 100%; min-height: 36px; text-align: left; font-size: 12px; white-space: normal; overflow-wrap: anywhere; }
.c-player-card-menu button { color: var(--color-danger); }
@media (max-width: 1500px) { .c-player-list-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
@media (max-width: 1180px) { .c-player-list-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 900px) {
    .c-player-header-actions { justify-content: flex-start; margin-left: 0; }
    .c-player-list-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 640px) { .c-player-list-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; } }
@media (max-width: 420px) {
    .c-player-row-card { padding: 12px; }
    .c-player-card-identity { grid-template-rows: 68px minmax(44px, auto) minmax(36px, auto); }
    .c-player-card-avatar { width: 68px; height: 68px; font-size: 22px; }
    .c-player-card-identity h2 { font-size: 16px; }
    .c-player-card-actions { gap: 6px; }
}
@media (pointer: coarse) {
    .c-player-profile-link, .c-player-card-options summary, .c-player-card-menu .c-btn-secondary { min-height: 44px; }
    .c-player-card-options summary { width: 44px; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cards = Array.from(document.querySelectorAll('.c-player-row-card'));
    cards.forEach(function (card) {
        const menu = card.querySelector('details');
        menu.addEventListener('toggle', function () {
            card.classList.toggle('is-menu-open', menu.open);
            if (menu.open) cards.forEach(function (other) {
                if (other !== card) other.querySelector('details').open = false;
            });
        });
    });
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.c-player-card-options')) cards.forEach(function (card) { card.querySelector('details').open = false; });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') cards.forEach(function (card) {
            const menu = card.querySelector('details');
            if (menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
        });
    });
    document.querySelectorAll('[data-deactivate-player]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm('Desativar este jogador? A posição e o número da camisa serão liberados.')) event.preventDefault();
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
