<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$title = 'Resultados';

$allowedDays = [7, 15, 30];
$days = (int)($_GET['days'] ?? 7);
$competitionId = (int)($_GET['competition_id'] ?? 0);

if (!in_array($days, $allowedDays, true)) {
    $days = 7;
}

$dateLimit = (new DateTimeImmutable())->modify('-' . $days . ' days')->format('Y-m-d 00:00:00');

$competitionOptions = $pdo->query("
    SELECT id, name
    FROM competitions
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$where = [
    "m.status = 'finished'",
    "m.score_a IS NOT NULL",
    "m.score_b IS NOT NULL",
    "COALESCE(m.match_date, m.updated_at, m.created_at) >= ?",
];
$params = [$dateLimit];

if ($competitionId > 0) {
    $where[] = 'm.competition_id = ?';
    $params[] = $competitionId;
}

$stmt = $pdo->prepare("
    SELECT
        m.*,
        c.name AS competition_name
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY COALESCE(m.match_date, m.updated_at, m.created_at) DESC
");
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

function adminResultLabel(array $result): string
{
    $scoreA = (int)($result['score_a'] ?? 0);
    $scoreB = (int)($result['score_b'] ?? 0);

    if ($scoreA > $scoreB) {
        return 'Vitória';
    }

    if ($scoreA < $scoreB) {
        return 'Derrota';
    }

    return 'Empate';
}

function adminResultClass(array $result): string
{
    return match (adminResultLabel($result)) {
        'Vitória' => 'c-result-score--win',
        'Derrota' => 'c-result-score--loss',
        default => 'c-result-score--draw',
    };
}

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Resultados</h1>
            <p class="c-page-subtitle">Últimos resultados das partidas finalizadas</p>
        </div>
    </div>

    <div class="c-page-content">
        <form method="GET" class="c-card">
            <div class="c-result-filter-row">
                <div class="c-form-group">
                    <label>Período</label>
                    <select name="days" class="c-input">
                        <option value="7" <?= $days === 7 ? 'selected' : '' ?>>Últimos 7 dias</option>
                        <option value="15" <?= $days === 15 ? 'selected' : '' ?>>Últimos 15 dias</option>
                        <option value="30" <?= $days === 30 ? 'selected' : '' ?>>Últimos 30 dias</option>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Competição</label>
                    <select name="competition_id" class="c-input">
                        <option value="0">Todas</option>
                        <?php foreach ($competitionOptions as $competition): ?>
                            <option value="<?= (int)$competition['id'] ?>" <?= $competitionId === (int)$competition['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($competition['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="c-result-filter-actions">
                <button class="c-btn-secondary">Filtrar</button>
            </div>
        </form>

        <div class="c-card">
            <h3>Resultados</h3>

            <?php if (empty($results)): ?>
                <p>Nenhum resultado encontrado nesse período.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <th>Placar</th>
                                <th>Competição</th>
                                <th>Cartões</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($result['participant_a'] . ' x ' . ($result['participant_b'] ?? '-')) ?></strong></td>
                                    <td>
                                        <span class="c-result-score <?= adminResultClass($result) ?>">
                                            <?= (int)$result['score_a'] ?> x <?= (int)$result['score_b'] ?>
                                        </span>
                                        <span class="c-result-label"><?= adminResultLabel($result) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($result['competition_name'] ?? '-') ?></td>
                                    <td>
                                        <span class="c-result-card-pill c-result-card-pill--yellow"><?= (int)($result['yellow_cards_a'] ?? 0) + (int)($result['yellow_cards_b'] ?? 0) ?></span>
                                        <span class="c-result-card-pill c-result-card-pill--red"><?= (int)($result['red_cards_a'] ?? 0) + (int)($result['red_cards_b'] ?? 0) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($result['match_date'] ?? $result['updated_at'] ?? $result['created_at'] ?? '-') ?></td>
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
.c-result-filter-row {
    display: grid;
    grid-template-columns: minmax(220px, 320px) minmax(240px, 1fr);
    gap: 12px;
    align-items: end;
}

.c-result-filter-actions {
    margin-top: 12px;
}

.c-result-card-pill {
    display: inline-grid;
    min-width: 26px;
    height: 22px;
    place-items: center;
    border-radius: 5px;
    margin-right: 5px;
    font-size: 11px;
    font-weight: 800;
}

.c-result-card-pill--yellow {
    background: color-mix(in srgb, var(--warning-color, #f59e0b) 18%, transparent);
    color: var(--warning-color, #f59e0b);
}

.c-result-card-pill--red {
    background: color-mix(in srgb, var(--danger-color, #ef4444) 18%, transparent);
    color: var(--danger-color, #ef4444);
}

.c-result-score {
    display: inline-grid;
    min-width: 68px;
    height: 30px;
    place-items: center;
    border-radius: 6px;
    font-weight: 900;
    margin-right: 8px;
}

.c-result-score--win {
    background: color-mix(in srgb, var(--success-color, #22c55e) 16%, transparent);
    color: var(--success-color, #22c55e);
}

.c-result-score--draw {
    background: color-mix(in srgb, var(--text-secondary, #94a3b8) 16%, transparent);
    color: var(--text-primary);
}

.c-result-score--loss {
    background: color-mix(in srgb, var(--danger-color, #ef4444) 16%, transparent);
    color: var(--danger-color, #ef4444);
}

.c-result-label {
    color: var(--text-primary);
    font-size: 11px;
    font-weight: 800;
}

.c-table td {
    color: var(--text-primary);
}

@media (max-width: 700px) {
    .c-result-filter-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
