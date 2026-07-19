<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM matches WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    exit('Partida nao encontrada.');
}

$competition = null;

if (!empty($match['competition_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$match['competition_id']]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
}

$competitionContext = (string)($competition['context'] ?? 'external');
$competitionType = (string)($competition['type'] ?? '');
$isFriendlyMatch = $competitionContext === 'external' && $competitionType === 'friendly';
$currentLineupMode = (string)($match['lineup_mode'] ?? 'team_roster');
$liveEnabled = projectPlanAllows('live_match_enabled', false);
$financeEnabled = projectPlanAllows('finance_enabled', true);

$dateValue = '';
if (!empty($match['match_date'])) {
    $dateValue = str_replace(' ', 'T', substr((string)$match['match_date'], 0, 16));
}

$title = 'Editar Partida';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Editar Partida</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></p>
        </div>

        <div>
            <form action="<?= PROJECT_URL ?>/admin/partidas/delete.php?id=<?= (int)$match['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Apagar esta partida?');">
                <?= csrf_field(); ?>
                <button class="c-btn-secondary">Apagar</button>
            </form>
            <a href="<?= $competition ? PROJECT_URL . '/admin/competicoes/view.php?id=' . (int)$competition['id'] : PROJECT_URL . '/admin/partidas/index.php' ?>" class="c-btn-secondary">
                Voltar
            </a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/partidas/update.php?id=<?= (int)$match['id'] ?>" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Time 1</label>
                    <input type="text" name="participant_a" class="c-input" value="<?= htmlspecialchars((string)$match['participant_a']) ?>" required>
                </div>

                <div class="c-form-group">
                    <label>Time 2</label>
                    <input type="text" name="participant_b" class="c-input" value="<?= htmlspecialchars((string)($match['participant_b'] ?? '')) ?>" required>
                </div>

                <?php if (!$isFriendlyMatch): ?>
                    <div class="c-form-group">
                        <label>Rodada/Fase</label>
                        <input type="text" name="round_name" class="c-input" value="<?= htmlspecialchars((string)($match['round_name'] ?? '')) ?>">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="round_name" value="">
                <?php endif; ?>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Data e hora</label>
                    <input type="datetime-local" name="match_date" class="c-input" value="<?= htmlspecialchars($dateValue) ?>">
                </div>

                <div class="c-form-group">
                    <label>Local</label>
                    <input type="text" name="venue" class="c-input" value="<?= htmlspecialchars((string)($match['venue'] ?? '')) ?>">
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="scheduled" <?= ($match['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Agendada</option>
                        <?php if ($liveEnabled || ($match['status'] ?? '') === 'live'): ?>
                            <option value="live" <?= ($match['status'] ?? '') === 'live' ? 'selected' : '' ?>>Em andamento</option>
                        <?php endif; ?>
                        <option value="finished" <?= ($match['status'] ?? '') === 'finished' ? 'selected' : '' ?>>Finalizada</option>
                        <option value="canceled" <?= ($match['status'] ?? '') === 'canceled' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
            </div>

            <div class="c-form-group">
                <label>Modo de escalação</label>
                <select name="lineup_mode" class="c-input">
                    <?php if ($competitionContext === 'internal'): ?>
                        <option value="automatic" <?= $currentLineupMode === 'automatic' ? 'selected' : '' ?>>Automático</option>
                    <?php else: ?>
                        <option value="team_roster" <?= $currentLineupMode === 'team_roster' ? 'selected' : '' ?>>Seguir Meu Time</option>
                        <option value="arrival_order" <?= $currentLineupMode === 'arrival_order' ? 'selected' : '' ?>>Ordem de confirmação</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="c-form-group">
                <label>Valor da partida</label>
                <?php if ($financeEnabled): ?>
                    <input type="number" name="match_fee" class="c-input" min="0" step="0.01" value="<?= htmlspecialchars(number_format((float)($match['match_fee'] ?? 0), 2, '.', '')) ?>">
                    <small>Use 0 para partida grátis.</small>
                <?php else: ?>
                    <input type="hidden" name="match_fee" value="0">
                    <input type="text" class="c-input" value="Grátis" disabled>
                <?php endif; ?>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Placar A</label>
                    <input type="number" name="score_a" class="c-input" min="0" value="<?= htmlspecialchars((string)($match['score_a'] ?? '')) ?>">
                </div>

                <div class="c-form-group">
                    <label>Placar B</label>
                    <input type="number" name="score_b" class="c-input" min="0" value="<?= htmlspecialchars((string)($match['score_b'] ?? '')) ?>">
                </div>
            </div>

            <div class="c-form-group">
                <label>Observações</label>
                <textarea name="notes" class="c-input" rows="3"><?= htmlspecialchars((string)($match['notes'] ?? '')) ?></textarea>
            </div>

            <button class="c-btn-secondary">Salvar Partida</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
