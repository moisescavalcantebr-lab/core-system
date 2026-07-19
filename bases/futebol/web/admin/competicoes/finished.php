<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    http_response_code(404);
    exit('Competicao nao encontrada.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM matches
    WHERE competition_id = ?
      AND status = 'finished'
    ORDER BY COALESCE(match_date, created_at) DESC, id DESC
");
$stmt->execute([$id]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

function competitionFinishedResultClass(array $match): string
{
    if (($match['score_a'] ?? null) === null || ($match['score_b'] ?? null) === null) {
        return 'c-competition-result-score--draw';
    }

    $scoreA = (int)$match['score_a'];
    $scoreB = (int)$match['score_b'];

    return match (true) {
        $scoreA > $scoreB => 'c-competition-result-score--win',
        $scoreA < $scoreB => 'c-competition-result-score--loss',
        default => 'c-competition-result-score--draw',
    };
}

$title = 'Partidas Finalizadas';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Partidas finalizadas</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$competition['name']) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <?php if (empty($matches)): ?>
                <p>Nenhuma partida finalizada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Partida</th>
                                <th>Placar</th>
                                <th>Data</th>
                                <th>Local</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong></td>
                                    <td>
                                        <?php if ($match['score_a'] !== null && $match['score_b'] !== null): ?>
                                            <span class="c-competition-result-score <?= competitionFinishedResultClass($match) ?>">
                                                <?= (int)$match['score_a'] ?> x <?= (int)$match['score_b'] ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($match['match_date'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($match['venue'] ?? '-') ?></td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$match['id'] ?>">
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
.c-competition-result-score {
    display: inline-grid;
    min-width: 64px;
    height: 28px;
    place-items: center;
    border-radius: 6px;
    font-weight: 900;
}

.c-competition-result-score--win {
    background: color-mix(in srgb, var(--success-color, #22c55e) 16%, transparent);
    color: var(--success-color, #22c55e);
}

.c-competition-result-score--draw {
    background: color-mix(in srgb, var(--text-secondary, #94a3b8) 16%, transparent);
    color: var(--text-primary);
}

.c-competition-result-score--loss {
    background: color-mix(in srgb, var(--danger-color, #ef4444) 16%, transparent);
    color: var(--danger-color, #ef4444);
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
