<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.user_id,
        p.name AS player_name,
        p.nickname,
        p.whatsapp,
        p.position,
        p.shirt_number,
        p.status,
        p.created_at,
        p.updated_at,
        pp.name AS position_name,
        pp.code AS position_code,
        u.name AS user_name,
        u.username,
        u.email,
        COALESCE(p.avatar, u.avatar) AS avatar,
        u.role AS user_role,
        u.status AS user_status
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    flash('error', 'Jogador não encontrado.');
    redirect(PROJECT_URL . '/admin/jogadores/index.php');
}

$title = 'Detalhes do Jogador';

$displayName = $player['user_name'] ?: $player['player_name'];
$rosterName = playerDisplayName($player['nickname'] ?? null, (string)$displayName);
$position = trim((string)($player['position_code'] ?? '') . ' ' . (string)($player['position_name'] ?? $player['position'] ?? '-'));
$accessLabel = playerAccessLabel(!empty($player['user_id']) ? (int)$player['user_id'] : null, $player['user_status'] ?? null);
$permissionLabel = ($player['user_role'] ?? '') === 'FINANCE' ? 'Financeiro' : 'Jogador';
$statusLabel = ($player['status'] ?? '') === 'active' ? 'Ativo' : 'Inativo';
$statusBadge = ($player['status'] ?? '') === 'active' ? 'success' : 'neutral';
$toggleLabel = ($player['status'] ?? '') === 'active' ? 'Desativar' : 'Reativar';

ob_start();
?>

<div class="c-page c-player-show-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($displayName) ?></h1>
            <p class="c-page-subtitle">Ficha do jogador</p>
        </div>

        <div class="c-player-show-actions">
            <a href="<?= PROJECT_URL ?>/admin/jogadores/index.php" class="c-btn-secondary">
                Voltar
            </a>

            <a href="<?= PROJECT_URL ?>/admin/jogadores/edit.php?id=<?= (int)$player['id'] ?>" class="c-btn-secondary">
                Editar
            </a>

            <form action="<?= PROJECT_URL ?>/admin/jogadores/toggle.php?id=<?= (int)$player['id'] ?>" method="POST">
                <?= csrf_field(); ?>
                <button type="submit" class="c-btn-secondary">
                    <?= htmlspecialchars($toggleLabel) ?>
                </button>
            </form>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-player-show-grid">
            <div class="c-card">
                <h3>Dados do Jogador</h3>

                <div class="c-player-detail-grid">
                    <div><span>Nome</span><strong><?= htmlspecialchars($displayName) ?></strong></div>
                    <div><span>Apelido no elenco</span><strong><?= htmlspecialchars($rosterName) ?></strong></div>
                    <div><span>WhatsApp</span><strong><?= htmlspecialchars($player['whatsapp'] ?: '-') ?></strong></div>
                    <div><span>Posição</span><strong><?= htmlspecialchars($position !== '' ? $position : '-') ?></strong></div>
                    <div><span>Camisa</span><strong><?= htmlspecialchars((string)($player['shirt_number'] ?: '-')) ?></strong></div>
                    <div><span>Status</span><strong><?= htmlspecialchars($statusLabel) ?></strong></div>
                    <div><span>Cadastrado em</span><strong><?= htmlspecialchars((string)($player['created_at'] ?? '-')) ?></strong></div>
                </div>
            </div>

            <div class="c-card">
                <h3>Acesso</h3>

                <div class="c-player-detail-grid">
                    <div><span>Usuário</span><strong><?= htmlspecialchars($player['username'] ?: '-') ?></strong></div>
                    <div><span>Email</span><strong><?= htmlspecialchars($player['email'] ?: '-') ?></strong></div>
                    <div><span>Status do acesso</span><strong><?= htmlspecialchars($accessLabel) ?></strong></div>
                    <div><span>Permissão</span><strong><?= htmlspecialchars($accessLabel === 'Liberado' ? $permissionLabel : '-') ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.c-player-show-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr);
    gap: 14px;
    align-items: start;
}

.c-player-show-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.c-player-show-actions form {
    margin: 0;
}

.c-player-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.c-player-detail-grid div {
    min-width: 0;
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 10px;
}

.c-player-detail-grid span,
.c-player-detail-grid strong {
    display: block;
}

.c-player-detail-grid span {
    color: rgba(226, 232, 240, .72);
    margin-bottom: 5px;
    font-size: 11px;
}

.c-player-detail-grid strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 1000px) {
    .c-player-show-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 700px) {
    .c-player-show-actions {
        justify-content: flex-start;
        margin-left: 0;
    }
}
</style>

<?php
$content = ob_get_clean();
$rightSidebarEnabled = false;

require APP_PATH . '/views/layout_admin.php';
