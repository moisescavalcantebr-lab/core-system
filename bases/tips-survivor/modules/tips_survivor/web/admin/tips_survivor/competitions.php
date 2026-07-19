<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireAdmin();
tipsEnsureSchema($pdo);

$title = 'Competicoes - Tips Survivor';
$settings = tipsSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $season = trim((string)($_POST['season'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $startsAt = trim((string)($_POST['starts_at'] ?? ''));
        $endsAt = trim((string)($_POST['ends_at'] ?? ''));
        $initialLives = max(1, min(9, (int)($_POST['initial_lives'] ?? $settings['initial_lives'])));
        $maxLives = max($initialLives, min(9, (int)($_POST['max_lives'] ?? $settings['max_lives'])));
        $pointsPerExtraLife = max(0, (int)($_POST['points_per_extra_life'] ?? $settings['points_per_extra_life']));
        $tokensOnStart = max(0, (int)($_POST['tokens_on_start'] ?? $settings['tokens_on_start']));
        $tokenConsumptionMode = in_array((string)($_POST['token_consumption_mode'] ?? ''), ['per_round', 'per_day'], true)
            ? (string)$_POST['token_consumption_mode']
            : (string)$settings['token_consumption_mode'];
        $tokenConsumptionAmount = max(0, (int)($_POST['token_consumption_amount'] ?? $settings['token_consumption_amount']));

        if ($name === '') {
            flash('error', 'Informe o nome da competicao.');
            redirect(PROJECT_URL . '/admin/tips_survivor/competitions.php');
        }

        if (!in_array($status, ['draft', 'open', 'active'], true)) {
            $status = 'draft';
        }

        $slug = tipsSlug($name . ($season !== '' ? '-' . $season : ''));
        $exists = $pdo->prepare('SELECT COUNT(*) FROM tips_competitions WHERE slug = ?');
        $exists->execute([$slug]);

        if ((int)$exists->fetchColumn() > 0) {
            flash('error', 'Ja existe uma competicao com este nome/temporada.');
            redirect(PROJECT_URL . '/admin/tips_survivor/competitions.php');
        }

        $stmt = $pdo->prepare("
            INSERT INTO tips_competitions (
                name, slug, description, season, initial_lives, max_lives,
                points_per_extra_life, tokens_on_start, token_consumption_mode,
                token_consumption_amount, status, starts_at, ends_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name,
            $slug,
            $description !== '' ? $description : null,
            $season !== '' ? $season : null,
            $initialLives,
            $maxLives,
            $pointsPerExtraLife,
            $tokensOnStart,
            $tokenConsumptionMode,
            $tokenConsumptionAmount,
            $status,
            $startsAt !== '' ? str_replace('T', ' ', $startsAt) . ':00' : null,
            $endsAt !== '' ? str_replace('T', ' ', $endsAt) . ':00' : null,
        ]);

        flash('success', 'Competicao criada.');
        redirect(PROJECT_URL . '/admin/tips_survivor/competitions.php');
    }

    if ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');

        if ($id > 0 && in_array($status, ['draft', 'open', 'active', 'finished', 'cancelled'], true)) {
            $stmt = $pdo->prepare('UPDATE tips_competitions SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $id]);
            flash('success', 'Status atualizado.');
        }

        redirect(PROJECT_URL . '/admin/tips_survivor/competitions.php');
    }
}

$competitions = tipsRecentCompetitions($pdo, 50);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Competicoes</h1>
            <p class="c-page-subtitle">Estrutura survivor com vidas, pontos e tokens internos.</p>
        </div>
        <?= tipsNav('competitions') ?>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <h3>Nova competicao</h3>
            <form method="post" class="tips-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">

                <div class="tips-form-grid">
                    <label>
                        Nome
                        <input type="text" name="name" placeholder="Ex: Survivor Brasileirão" required>
                    </label>
                    <label>
                        Temporada
                        <input type="text" name="season" placeholder="2026">
                    </label>
                    <label>
                        Status inicial
                        <select name="status">
                            <option value="draft">Rascunho</option>
                            <option value="open">Aberta</option>
                            <option value="active">Ativa</option>
                        </select>
                    </label>
                    <label>
                        Inicio
                        <input type="datetime-local" name="starts_at">
                    </label>
                    <label>
                        Fim
                        <input type="datetime-local" name="ends_at">
                    </label>
                    <label>
                        Vidas iniciais
                        <input type="number" name="initial_lives" min="1" max="9" value="<?= (int)$settings['initial_lives'] ?>">
                    </label>
                    <label>
                        Maximo de vidas
                        <input type="number" name="max_lives" min="1" max="9" value="<?= (int)$settings['max_lives'] ?>">
                    </label>
                    <label>
                        Tokens iniciais
                        <input type="number" name="tokens_on_start" min="0" value="<?= (int)$settings['tokens_on_start'] ?>">
                    </label>
                    <label>
                        Consumo
                        <select name="token_consumption_mode">
                            <option value="per_round" <?= $settings['token_consumption_mode'] === 'per_round' ? 'selected' : '' ?>>Por rodada</option>
                            <option value="per_day" <?= $settings['token_consumption_mode'] === 'per_day' ? 'selected' : '' ?>>Por dia</option>
                        </select>
                    </label>
                    <label>
                        Tokens por consumo
                        <input type="number" name="token_consumption_amount" min="0" value="<?= (int)$settings['token_consumption_amount'] ?>">
                    </label>
                    <label>
                        Pontos por vida extra
                        <input type="number" name="points_per_extra_life" min="0" value="<?= (int)$settings['points_per_extra_life'] ?>">
                    </label>
                </div>

                <label>
                    Descricao
                    <textarea name="description" rows="3" placeholder="Resumo interno da competicao"></textarea>
                </label>

                <button class="c-btn-primary" type="submit">Criar competicao</button>
            </form>
        </div>

        <div class="c-card">
            <h3>Lista</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead>
                    <tr><th>Nome</th><th>Temporada</th><th>Status</th><th>Vidas</th><th>Tokens iniciais</th><th>Participantes</th><th>Acoes</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($competitions as $competition): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$competition['name']) ?></strong><br><small><?= htmlspecialchars((string)$competition['slug']) ?></small></td>
                            <td><?= htmlspecialchars((string)($competition['season'] ?? '-')) ?></td>
                            <td><span class="c-badge <?= tipsBadgeClass((string)$competition['status']) ?>"><?= htmlspecialchars(tipsStatusLabel((string)$competition['status'])) ?></span></td>
                            <td><?= (int)$competition['initial_lives'] ?>/<?= (int)$competition['max_lives'] ?></td>
                            <td><?= (int)$competition['tokens_on_start'] ?></td>
                            <td><?= (int)$competition['participants_count'] ?></td>
                            <td>
                                <div class="tips-actions">
                                    <?php foreach (['draft' => 'Rascunho', 'open' => 'Abrir', 'active' => 'Ativar', 'finished' => 'Finalizar', 'cancelled' => 'Cancelar'] as $status => $label): ?>
                                        <?php if ($status === (string)$competition['status']) { continue; } ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="status">
                                            <input type="hidden" name="id" value="<?= (int)$competition['id'] ?>">
                                            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                                            <button class="c-btn-secondary" type="submit"><?= htmlspecialchars($label) ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($competitions)): ?>
                        <tr><td colspan="7">Nenhuma competicao cadastrada ainda.</td></tr>
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
