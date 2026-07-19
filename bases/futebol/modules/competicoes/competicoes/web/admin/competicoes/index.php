<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();

$title = 'Competicoes';

function competitionEnsureDefaultFriendly(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("
            SELECT id
            FROM competitions
            WHERE context = 'external'
              AND type = 'friendly'
              AND LOWER(name) = LOWER('Amistoso')
            LIMIT 1
        ");

        if ($stmt && $stmt->fetchColumn()) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO competitions (name, context, type, status, notes)
            VALUES (?, 'external', 'friendly', 'active', ?)
        ");
        $stmt->execute([
            'Amistoso',
            'Competicao simples para partidas amistosas do plano gratis.',
        ]);
    } catch (Throwable $e) {
        // A tela continua abrindo mesmo antes do schema ser sincronizado.
    }
}

competitionEnsureDefaultFriendly($pdo);

function competitionRelatedCount(PDO $pdo, int $competitionId): int
{
    $total = 0;

    foreach (['competition_participants', 'statistic_records', 'matches'] as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE competition_id = ?");
            $stmt->execute([$competitionId]);
            $total += (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }

    return $total;
}

$search = trim((string)($_GET['q'] ?? ''));
$contextFilter = in_array($_GET['context'] ?? '', ['external', 'internal'], true) ? $_GET['context'] : '';
$typeFilter = in_array($_GET['type'] ?? '', ['championship','cup','league','tournament','friendly','training','challenge','ranking','other'], true) ? $_GET['type'] : '';
$yearFilter = preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? (string)$_GET['year'] : '';
$view = ($_GET['view'] ?? '') === 'history' ? 'history' : 'open';
$internalCompetitionsEnabled = function_exists('projectModuleProvides')
    && projectModuleProvides('internal_competitions');

if (!$internalCompetitionsEnabled && $contextFilter === 'internal') {
    $contextFilter = '';
    $typeFilter = '';
}

if ($contextFilter === 'external' && $typeFilter === 'training') {
    $typeFilter = '';
}

if ($contextFilter === 'internal' && $typeFilter !== '' && $typeFilter !== 'training') {
    $typeFilter = '';
}

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

if ($contextFilter !== '') {
    $where[] = "c.context = ?";
    $params[] = $contextFilter;
}

if ($typeFilter !== '') {
    $where[] = "c.type = ?";
    $params[] = $typeFilter;
}

if ($yearFilter !== '') {
    $where[] = "(YEAR(c.starts_at) = ? OR c.season = ?)";
    $params[] = (int)$yearFilter;
    $params[] = $yearFilter;
}

$sql = "
    SELECT c.*
    FROM competitions c
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.id DESC
    LIMIT 50
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$years = [];
try {
    $years = $pdo->query("
        SELECT DISTINCT year_value
        FROM (
            SELECT YEAR(starts_at) AS year_value FROM competitions WHERE starts_at IS NOT NULL
            UNION
            SELECT season AS year_value FROM competitions WHERE season REGEXP '^[0-9]{4}$'
        ) years
        WHERE year_value IS NOT NULL
        ORDER BY year_value DESC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $years = [];
}

$contextLabels = [
    'external' => 'Externa',
    'internal' => 'Interna',
];

$externalTypeLabels = [
    'championship' => 'Campeonato',
    'cup' => 'Copa',
    'league' => 'Liga',
    'tournament' => 'Torneio',
    'friendly' => 'Amistoso',
    'challenge' => 'Desafio',
    'ranking' => 'Ranking',
    'other' => 'Outro',
];

$internalTypeLabels = $internalCompetitionsEnabled ? [
    'training' => 'Treino',
] : [];

$typeLabels = $externalTypeLabels + $internalTypeLabels;

$statusLabels = [
    'active' => 'Ativa',
    'draft' => 'Rascunho',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function competitionStatusBadge(?string $status): string
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
    'context' => $contextFilter,
    'type' => $typeFilter,
    'year' => $yearFilter,
], static fn ($value) => $value !== '' && $value !== null);
$toggleUrl = PROJECT_URL . '/admin/competicoes/index.php?' . http_build_query($toggleQuery);

$openCompetitionLimit = projectPlanLimit('competitions_open', 3);
$seasonCompetitionTypes = ['championship', 'cup', 'league', 'tournament', 'challenge', 'ranking', 'other'];
$showSeasonColumn = count(array_intersect(projectPlanList('external_competition_types', $seasonCompetitionTypes), $seasonCompetitionTypes)) > 0;
$openCompetitionCount = 0;
try {
    $openCompetitionCount = (int)$pdo->query("SELECT COUNT(*) FROM competitions WHERE status IN ('draft', 'active')")->fetchColumn();
} catch (Throwable $e) {
    $openCompetitionCount = 0;
}
$canCreateCompetition = $openCompetitionCount < $openCompetitionLimit;

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Competicoes</h1>
            <p class="c-page-subtitle"><?= $view === 'history' ? 'Histórico de competições finalizadas' : 'Competições abertas' ?></p>
        </div>

        <?php if ($canCreateCompetition): ?>
            <a href="<?= PROJECT_URL ?>/admin/competicoes/create.php" class="c-btn-secondary">
                Criar Competicao
            </a>
        <?php endif; ?>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="GET" class="c-card">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">

            <div class="c-competition-filter-row">
                <div class="c-form-group">
                    <label>Buscar</label>
                    <input type="text" name="q" class="c-input" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar competição...">
                </div>

                <div class="c-form-group">
                    <label>Contexto</label>
                    <select name="context" class="c-input" id="competitionFilterContext">
                        <option value="">Todos</option>
                        <option value="external" <?= $contextFilter === 'external' ? 'selected' : '' ?>>Externa</option>
                        <?php if ($internalCompetitionsEnabled): ?>
                            <option value="internal" <?= $contextFilter === 'internal' ? 'selected' : '' ?>>Interna</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Tipo</label>
                    <select name="type" class="c-input" id="competitionFilterType">
                        <option value="">Todos</option>
                        <?php foreach ($externalTypeLabels as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" data-context="external" <?= $typeFilter === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php foreach ($internalTypeLabels as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" data-context="internal" <?= $typeFilter === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Ano</label>
                    <select name="year" class="c-input">
                        <option value="">Todos</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= htmlspecialchars((string)$year) ?>" <?= $yearFilter === (string)$year ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$year) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="c-competition-filter-actions">
                <button class="c-btn-secondary">Filtrar</button>
                <a href="<?= htmlspecialchars($toggleUrl) ?>" class="c-btn-secondary">
                    <?= $view === 'history' ? 'Ver Abertas' : 'Ver Histórico' ?>
                </a>
            </div>
        </form>

        <div class="c-card">
            <h3><?= $view === 'history' ? 'Histórico' : 'Lista' ?></h3>

            <?php if (empty($competitions)): ?>
                <p>Nenhuma competicao encontrada.</p>
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
                                <th>Acoes</th>
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
                                        <span class="c-badge <?= competitionStatusBadge($competition['status'] ?? null) ?>">
                                            <?= htmlspecialchars($statusLabels[$competition['status']] ?? $competition['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$competition['id'] ?>">
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
.c-competition-filter-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 12px;
    align-items: end;
}

.c-competition-filter-actions {
    margin-top: 12px;
}

@media (max-width: 900px) {
    .c-competition-filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const context = document.getElementById('competitionFilterContext');
    const type = document.getElementById('competitionFilterType');

    if (!context || !type) {
        return;
    }

    const syncTypeFilter = function () {
        Array.from(type.options).forEach(function (option) {
            if (option.value === '') {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const visible = context.value === '' || option.dataset.context === context.value;
            option.hidden = !visible;
            option.disabled = !visible;

            if (!visible && option.selected) {
                type.value = '';
            }
        });
    };

    context.addEventListener('change', syncTypeFilter);
    syncTypeFilter();
});
</script>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
