<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../competicoes/plan_fallback.php';

requireProjectAuth();

$user = projectUser();
$stmt = $pdo->prepare("SELECT id FROM players WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);

if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

$search = trim((string)($_GET['q'] ?? ''));
$view = ($_GET['view'] ?? '') === 'history' ? 'history' : 'open';

$where = [];
$params = [];

if ($view === 'history') {
    $where[] = "c.status IN ('finished', 'canceled')";
} else {
    $where[] = "c.status NOT IN ('finished', 'canceled')";
}

if ($search !== '') {
    $where[] = "c.name LIKE ?";
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
    SELECT c.*
    FROM competitions c
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.id DESC
    LIMIT 50
");
$stmt->execute($params);
$competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$contextLabels = ['external' => 'Externa', 'internal' => 'Interna'];
$typeLabels = [
    'championship' => 'Campeonato',
    'cup' => 'Copa',
    'league' => 'Liga',
    'tournament' => 'Torneio',
    'friendly' => 'Amistoso',
    'training' => 'Treino',
    'challenge' => 'Desafio',
    'ranking' => 'Ranking',
    'other' => 'Outro',
];
$statusLabels = [
    'active' => 'Ativa',
    'draft' => 'Rascunho',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function playerCompetitionStatusBadge(?string $status): string
{
    return match ($status) {
        'active' => 'c-badge--success',
        'draft' => 'c-badge--warning',
        'finished' => 'c-badge--neutral',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--neutral',
    };
}

$toggleQuery = array_filter([
    'view' => $view === 'history' ? 'open' : 'history',
    'q' => $search,
], static fn ($value) => $value !== '' && $value !== null);
$toggleUrl = PROJECT_URL . '/admin/player/competicoes.php?' . http_build_query($toggleQuery);
$seasonCompetitionTypes = ['championship', 'cup', 'league', 'tournament', 'challenge', 'ranking', 'other'];
$showSeasonColumn = count(array_intersect(projectPlanList('external_competition_types', $seasonCompetitionTypes), $seasonCompetitionTypes)) > 0;

$title = 'Competicoes';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Competições</h1>
            <p class="c-page-subtitle"><?= $view === 'history' ? 'Histórico de competições' : 'Competições abertas' ?></p>
        </div>
    </div>

    <div class="c-page-content">
        <form method="GET" class="c-card">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

            <div class="c-player-competition-filter-row">
                <div class="c-form-group">
                    <label>Buscar</label>
                    <input type="text" name="q" class="c-input" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar competição...">
                </div>
            </div>

            <div class="c-player-competition-filter-actions">
                <button class="c-btn-secondary">Filtrar</button>
                <a href="<?= htmlspecialchars($toggleUrl) ?>" class="c-btn-secondary">
                    <?= $view === 'history' ? 'Ver Abertas' : 'Ver Histórico' ?>
                </a>
            </div>
        </form>

        <div class="c-card">
            <h3><?= $view === 'history' ? 'Histórico' : 'Lista' ?></h3>

            <?php if (empty($competitions)): ?>
                <p>Nenhuma competição encontrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Contexto</th>
                                <th>Tipo</th>
                                <?php if ($showSeasonColumn): ?>
                                    <th>Temporada</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competitions as $competition): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($competition['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($contextLabels[$competition['context']] ?? $competition['context']) ?></td>
                                    <td><?= htmlspecialchars($typeLabels[$competition['type']] ?? $competition['type']) ?></td>
                                    <?php if ($showSeasonColumn): ?>
                                        <td><?= htmlspecialchars($competition['season'] ?? '-') ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="c-badge <?= playerCompetitionStatusBadge($competition['status'] ?? null) ?>">
                                            <?= htmlspecialchars($statusLabels[$competition['status']] ?? $competition['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/player/competicao.php?id=<?= (int)$competition['id'] ?>">
                                            Ver
                                        </a>
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
.c-player-competition-filter-row {
    display: grid;
    grid-template-columns: minmax(240px, 1fr);
    gap: 12px;
    align-items: end;
}

.c-player-competition-filter-actions {
    margin-top: 12px;
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
