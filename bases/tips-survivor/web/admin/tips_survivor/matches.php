<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireAdmin();
tipsEnsureSchema($pdo);

$title = 'Partidas - Tips Survivor';
$competitions = tipsCompetitionsForSelect($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $competitionId = (int)($_POST['competition_id'] ?? 0);
        $homeTeam = trim((string)($_POST['home_team'] ?? ''));
        $awayTeam = trim((string)($_POST['away_team'] ?? ''));
        $championshipName = trim((string)($_POST['championship_name'] ?? ''));
        $roundNumber = max(1, (int)($_POST['round_number'] ?? 1));
        $matchDatetime = trim((string)($_POST['match_datetime'] ?? ''));

        $check = $pdo->prepare("SELECT COUNT(*) FROM tips_competitions WHERE id = ? AND status IN ('draft', 'open', 'active')");
        $check->execute([$competitionId]);

        if ((int)$check->fetchColumn() === 0 || $homeTeam === '' || $awayTeam === '' || $matchDatetime === '') {
            flash('error', 'Informe competicao, times e data da partida.');
            redirect(PROJECT_URL . '/admin/tips_survivor/matches.php');
        }

        $stmt = $pdo->prepare("
            INSERT INTO tips_matches (
                competition_id, round_number, championship_name, home_team, away_team, match_datetime, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
        ");
        $stmt->execute([
            $competitionId,
            $roundNumber,
            $championshipName !== '' ? $championshipName : null,
            $homeTeam,
            $awayTeam,
            str_replace('T', ' ', $matchDatetime) . ':00',
        ]);

        flash('success', 'Partida cadastrada.');
        redirect(PROJECT_URL . '/admin/tips_survivor/matches.php');
    }

    if ($action === 'result') {
        $id = (int)($_POST['id'] ?? 0);
        $homeScore = max(0, (int)($_POST['home_score'] ?? 0));
        $awayScore = max(0, (int)($_POST['away_score'] ?? 0));
        $status = (string)($_POST['status'] ?? 'finished');

        if ($id > 0 && in_array($status, ['scheduled', 'locked', 'finished', 'cancelled'], true)) {
            $stmt = $pdo->prepare("
                UPDATE tips_matches
                SET home_score = ?, away_score = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$homeScore, $awayScore, $status, $id]);
            flash('success', 'Partida atualizada.');
        }

        redirect(PROJECT_URL . '/admin/tips_survivor/matches.php');
    }
}

$matches = tipsRecentMatches($pdo, 80);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Partidas</h1>
            <p class="c-page-subtitle">Partidas reais que recebem palpites antes do horario de inicio.</p>
        </div>
        <?= tipsNav('matches') ?>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <h3>Nova partida</h3>
            <?php if (empty($competitions)): ?>
                <p>Crie uma competicao antes de cadastrar partidas.</p>
            <?php else: ?>
                <form method="post" class="tips-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">

                    <div class="tips-form-grid">
                        <label>
                            Competicao
                            <select name="competition_id" required>
                                <?php foreach ($competitions as $competition): ?>
                                    <option value="<?= (int)$competition['id'] ?>">
                                        <?= htmlspecialchars((string)$competition['name']) ?>
                                        <?= $competition['season'] ? ' - ' . htmlspecialchars((string)$competition['season']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            Campeonato/torneio
                            <input type="text" name="championship_name" placeholder="Ex: Brasileirão">
                        </label>
                        <label>
                            Rodada
                            <input type="number" name="round_number" min="1" value="1">
                        </label>
                        <label>
                            Data e hora
                            <input type="datetime-local" name="match_datetime" required>
                        </label>
                        <label>
                            Mandante
                            <input type="text" name="home_team" placeholder="Time da casa" required>
                        </label>
                        <label>
                            Visitante
                            <input type="text" name="away_team" placeholder="Time visitante" required>
                        </label>
                    </div>

                    <button class="c-btn-primary" type="submit">Cadastrar partida</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Lista</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead>
                    <tr><th>Partida</th><th>Competicao</th><th>Rodada</th><th>Data</th><th>Placar</th><th>Status</th><th>Ajuste rapido</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($matches as $match): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$match['home_team']) ?> x <?= htmlspecialchars((string)$match['away_team']) ?></strong><br><small><?= htmlspecialchars((string)($match['championship_name'] ?? '-')) ?></small></td>
                            <td><?= htmlspecialchars((string)$match['competition_name']) ?></td>
                            <td><?= (int)$match['round_number'] ?></td>
                            <td><?= htmlspecialchars((string)$match['match_datetime']) ?></td>
                            <td><?= $match['home_score'] === null ? '-' : (int)$match['home_score'] . ' x ' . (int)$match['away_score'] ?></td>
                            <td><span class="c-badge <?= tipsBadgeClass((string)$match['status']) ?>"><?= htmlspecialchars(tipsStatusLabel((string)$match['status'])) ?></span></td>
                            <td>
                                <form method="post" class="tips-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="result">
                                    <input type="hidden" name="id" value="<?= (int)$match['id'] ?>">
                                    <input type="number" name="home_score" min="0" value="<?= $match['home_score'] === null ? 0 : (int)$match['home_score'] ?>">
                                    <span>x</span>
                                    <input type="number" name="away_score" min="0" value="<?= $match['away_score'] === null ? 0 : (int)$match['away_score'] ?>">
                                    <select name="status">
                                        <?php foreach (['scheduled' => 'Agendada', 'locked' => 'Bloqueada', 'finished' => 'Finalizada', 'cancelled' => 'Cancelada'] as $status => $label): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= $status === (string)$match['status'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="c-btn-secondary" type="submit">Salvar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($matches)): ?>
                        <tr><td colspan="7">Nenhuma partida cadastrada ainda.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
