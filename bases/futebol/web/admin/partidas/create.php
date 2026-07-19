<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';
require __DIR__ . '/plan_fallback.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

$competitionId = (int)($_GET['competition_id'] ?? 0);
$competition = null;

if ($competitionId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($competition && in_array($competition['status'] ?? '', ['finished', 'canceled'], true)) {
        flash('error', 'Esta competicao esta finalizada ou cancelada e nao permite novas partidas.');
        redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . (int)$competition['id']);
    }
}

$teamName = 'Meu Time';
try {
    $teamName = (string)($pdo->query("SELECT name FROM team_profile WHERE id = 1 LIMIT 1")->fetchColumn() ?: $teamName);
} catch (Throwable $e) {
    $teamName = 'Meu Time';
}

$competitionContext = (string)($competition['context'] ?? 'external');
$competitionType = (string)($competition['type'] ?? '');
$isFriendlyMatch = $competitionContext === 'external' && $competitionType === 'friendly';
$isChampionshipMatch = $competitionContext === 'external' && $competitionType === 'championship';
$participantAValue = $competitionContext === 'internal' ? 'Time 1' : $teamName;
$participantBValue = $competitionContext === 'internal' ? 'Time 2' : 'Time adversário';
$financeEnabled = projectPlanAllows('finance_enabled', true);
$liveEnabled = projectPlanAllows('live_match_enabled', false);

$title = 'Criar Partida';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Criar Partida</h1>
            <p class="c-page-subtitle"><?= $competition ? htmlspecialchars((string)$competition['name']) : 'Partida avulsa' ?></p>
        </div>

        <a href="<?= $competition ? PROJECT_URL . '/admin/competicoes/view.php?id=' . (int)$competition['id'] : PROJECT_URL . '/admin/partidas/index.php' ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/partidas/store.php" method="POST" class="c-card">
            <?= csrf_field(); ?>
            <input type="hidden" name="competition_id" value="<?= (int)$competitionId ?>">

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Time 1</label>
                    <input type="text" name="participant_a" class="c-input" value="<?= htmlspecialchars($participantAValue) ?>" required>
                </div>

                <div class="c-form-group">
                    <label>Time 2</label>
                    <input type="text" name="participant_b" class="c-input" value="<?= htmlspecialchars($participantBValue) ?>" required>
                </div>

                <?php if (!$isFriendlyMatch): ?>
                    <div class="c-form-group">
                        <label>Rodada/Fase</label>
                        <input type="text" name="round_name" class="c-input">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="round_name" value="">
                <?php endif; ?>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Data e hora</label>
                    <input type="datetime-local" name="match_date" class="c-input" required>
                </div>

                <div class="c-form-group">
                    <label>Local</label>
                    <input type="text" name="venue" class="c-input">
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="scheduled">Agendada</option>
                        <?php if ($liveEnabled): ?>
                            <option value="live">Em andamento</option>
                        <?php endif; ?>
                        <option value="finished">Finalizada</option>
                        <option value="canceled">Cancelada</option>
                    </select>
                </div>
            </div>

            <div class="c-form-group">
                <label>Modo de escalação</label>
                <select name="lineup_mode" class="c-input">
                    <?php if ($competitionContext === 'internal'): ?>
                        <option value="automatic">Automático</option>
                    <?php elseif ($isFriendlyMatch || $isChampionshipMatch): ?>
                        <option value="team_roster">Meu Time</option>
                    <?php else: ?>
                        <option value="team_roster">Meu Time</option>
                        <option value="arrival_order">Ordem de confirmação</option>
                    <?php endif; ?>
                </select>
            </div>

            <?php if ($isFriendlyMatch): ?>
                <input type="hidden" name="match_fee" value="0">
            <?php else: ?>
                <div class="c-form-group">
                    <label>Valor da partida</label>
                    <?php if ($financeEnabled): ?>
                        <input type="number" name="match_fee" class="c-input" min="0" step="0.01" value="0.00">
                        <small>Use 0 para partida grátis.</small>
                    <?php else: ?>
                        <input type="hidden" name="match_fee" value="0">
                        <input type="text" class="c-input" value="Grátis" disabled>
                        <small>Financeiro disponivel a partir do Plano Start.</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="c-form-group">
                <label>Observações</label>
                <textarea name="notes" class="c-input" rows="3"></textarea>
            </div>

            <button class="c-btn-secondary">Criar Partida</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
