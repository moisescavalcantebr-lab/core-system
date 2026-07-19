<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/positions_helper.php';

requireProjectAdmin();
playerEnsureDefaultPositions($pdo);

$title = 'Jogadores Inativos';
$activeCount = (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'active'")->fetchColumn();
$canReactivate = $activeCount < playerActiveLimit();

$search = trim((string)($_GET['q'] ?? ''));
$positionFilter = trim((string)($_GET['position'] ?? ''));
$accessFilter = in_array($_GET['access'] ?? '', ['player', 'finance'], true) ? (string)$_GET['access'] : '';

$where = ["p.status = 'inactive'"];
$params = [];

if ($search !== '') {
    $where[] = "(COALESCE(u.name, p.name) LIKE ? OR u.username LIKE ? OR p.whatsapp LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($positionFilter !== '') {
    $where[] = "pp.code = ?";
    $params[] = $positionFilter;
}

if ($accessFilter === 'finance') {
    $where[] = "u.role = 'FINANCE'";
} elseif ($accessFilter === 'player') {
    $where[] = "(u.role IS NULL OR u.role <> 'FINANCE')";
}

$stmt = $pdo->prepare("
    SELECT p.id, COALESCE(u.name, p.name) AS name, p.whatsapp, p.position, p.shirt_number, p.created_at, p.notes,
        pp.name AS position_name,
        pp.code AS position_code,
        u.username,
        u.role AS user_role
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    LEFT JOIN project_users u ON u.id = p.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY CASE WHEN p.notes LIKE 'Cadastro feito pelo jogador%' THEN 0 ELSE 1 END, p.created_at DESC, name ASC
");
$stmt->execute($params);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$positions = $pdo->query("
    SELECT pp.code, pp.name, MIN(pp.sort_order) AS sort_order
    FROM player_positions pp
    INNER JOIN players p ON p.position_id = pp.id
    WHERE p.status = 'inactive'
    GROUP BY pp.code, pp.name
    ORDER BY sort_order ASC, pp.code ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Jogadores Inativos</h1>
            <p class="c-page-subtitle">Lista de jogadores fora do elenco ativo · <?= $activeCount ?>/<?= playerActiveLimit() ?> ativos</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/jogadores/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form method="GET" class="c-card">
            <div class="c-inactive-filter-row">
                <div class="c-form-group">
                    <label>Buscar</label>
                    <input type="text" name="q" class="c-input" value="<?= htmlspecialchars($search) ?>" placeholder="Nome, usuário ou WhatsApp...">
                </div>

                <div class="c-form-group">
                    <label>Posição</label>
                    <select name="position" class="c-input">
                        <option value="">Todas</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?= htmlspecialchars((string)$position['code']) ?>" <?= $positionFilter === (string)$position['code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$position['code'] . ' ' . (string)$position['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Acesso</label>
                    <select name="access" class="c-input">
                        <option value="">Todos</option>
                        <option value="player" <?= $accessFilter === 'player' ? 'selected' : '' ?>>Jogador</option>
                        <option value="finance" <?= $accessFilter === 'finance' ? 'selected' : '' ?>>Financeiro</option>
                    </select>
                </div>
            </div>

            <div class="c-inactive-filter-actions">
                <button class="c-btn-secondary">Filtrar</button>
                <a href="<?= PROJECT_URL ?>/admin/jogadores/inativos.php" class="c-btn-secondary">Limpar</a>
            </div>
        </form>

        <div class="c-card">

            <?php if (empty($players)): ?>

                <p>Nenhum jogador inativo.</p>

            <?php else: ?>

                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Usuario</th>
                                <th>WhatsApp</th>
                                <th>Posicao</th>
                                <th>Camisa</th>
                                <th>Acesso</th>
                                <th>Cadastro</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                                <?php
                                    $position = trim((string)($player['position_code'] ?? '') . ' ' . (string)($player['position_name'] ?? $player['position'] ?? '-'));
                                    $accessLabel = ($player['user_role'] ?? '') === 'FINANCE' ? 'Financeiro' : 'Jogador';
                                    $isPublicRegistration = str_starts_with((string)($player['notes'] ?? ''), 'Cadastro feito pelo jogador');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($player['name']) ?></td>
                                    <td><?= htmlspecialchars($player['username'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($player['whatsapp'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($position !== '' ? $position : '-') ?></td>
                                    <td><?= htmlspecialchars((string)($player['shirt_number'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($accessLabel) ?></td>
                                    <td>
                                        <?php if ($isPublicRegistration): ?>
                                            <span class="c-badge c-badge--warning">Novo cadastro</span>
                                        <?php else: ?>
                                            <span class="c-badge c-badge--neutral">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= PROJECT_URL ?>/admin/jogadores/edit.php?id=<?= (int)$player['id'] ?>" class="c-btn-secondary">
                                            Editar
                                        </a>
                                        <?php if ($canReactivate): ?>
                                            <form action="<?= PROJECT_URL ?>/admin/jogadores/toggle.php?id=<?= (int)$player['id'] ?>&return=inactive" method="POST" style="display:inline;">
                                                <?= csrf_field(); ?>
                                                <button type="submit" class="c-btn-secondary">
                                                    <?= $isPublicRegistration ? 'Ativar' : 'Reativar' ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="c-badge c-badge--neutral">Limite cheio</span>
                                        <?php endif; ?>
                                        <form action="<?= PROJECT_URL ?>/admin/jogadores/delete.php?id=<?= (int)$player['id'] ?>&return=inactive" method="POST" style="display:inline;" onsubmit="return confirm('Excluir definitivamente este jogador?');">
                                            <?= csrf_field(); ?>
                                            <button type="submit" class="c-btn-secondary">
                                                Excluir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<style>
.c-inactive-filter-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 12px;
}

.c-inactive-filter-actions {
    margin-top: 12px;
}

@media (max-width: 900px) {
    .c-inactive-filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
