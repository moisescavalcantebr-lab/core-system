<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireUser();
tipsEnsureSchema($pdo);

$user = projectUser();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(403);
    die('Acesso negado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'join') {
        $message = tipsJoinCompetition($pdo, (int)($_POST['competition_id'] ?? 0), $userId);

        if ($message !== '') {
            flash('error', $message);
        } else {
            flash('success', 'Entrada confirmada na competicao.');
        }

        redirect(PROJECT_URL . '/admin/tips_survivor/player.php');
    }
}

$wallet = tipsEnsureUserWallet($pdo, $userId);
tipsRefreshWalletStatus($pdo, $userId);
$wallet = tipsEnsureUserWallet($pdo, $userId);
$activeCompetition = tipsUserActiveCompetition($pdo, $userId);
$openCompetitions = tipsOpenCompetitionsForUser($pdo, $userId);

$title = 'Minha Area - Tips Survivor';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Tips Survivor</h1>
            <p class="c-page-subtitle">Acompanhe competicoes abertas, vidas, pontos e tokens internos.</p>
        </div>
        <?php if (projectUserRole() === 'ADMIN'): ?>
            <?= tipsNav('player') ?>
        <?php endif; ?>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="tips-grid">
            <div class="tips-card">
                <span>Tokens internos</span>
                <strong><?= (int)($wallet['tokens'] ?? 0) ?></strong>
                <small>Sem valor financeiro e sem saque.</small>
            </div>
            <div class="tips-card">
                <span>Status</span>
                <strong><?= htmlspecialchars(tipsStatusLabel((string)($wallet['status'] ?? 'free'))) ?></strong>
                <small>Tokens liberam entrada em competicoes. Sem tokens ativos, a conta volta ao modo inicial.</small>
            </div>
            <div class="tips-card">
                <span>Competicao atual</span>
                <strong><?= $activeCompetition ? htmlspecialchars((string)$activeCompetition['name']) : '-' ?></strong>
                <small><?= $activeCompetition ? ((int)$activeCompetition['lives'] . ' vida(s)') : 'Nenhuma competicao ativa.' ?></small>
            </div>
        </div>

        <?php if ($activeCompetition): ?>
            <div class="c-card">
                <h3><?= htmlspecialchars((string)$activeCompetition['name']) ?></h3>
                <div class="tips-mini-grid">
                    <div><span>Status</span><strong><?= htmlspecialchars(tipsStatusLabel((string)$activeCompetition['status'])) ?></strong></div>
                    <div><span>Vidas</span><strong><?= (int)$activeCompetition['lives'] ?></strong></div>
                    <div><span>Pontos</span><strong><?= (int)$activeCompetition['points'] ?></strong></div>
                    <div><span>Inicio</span><strong><?= htmlspecialchars((string)($activeCompetition['starts_at'] ?? '-')) ?></strong></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="c-card">
            <h3>Competicoes abertas</h3>
            <p>Depois que uma competicao inicia, novas entradas sao bloqueadas. Nesta fase, cada usuario participa de uma competicao ativa por vez.</p>

            <div class="tips-competition-cards">
                <?php foreach ($openCompetitions as $competition): ?>
                    <div class="tips-competition-card">
                        <div>
                            <span class="c-badge c-badge--success">Aberta</span>
                            <h4><?= htmlspecialchars((string)$competition['name']) ?></h4>
                            <p><?= htmlspecialchars((string)($competition['description'] ?? 'Competicao survivor programada.')) ?></p>
                        </div>
                        <div class="tips-mini-grid">
                            <div><span>Inicio</span><strong><?= htmlspecialchars((string)($competition['starts_at'] ?? '-')) ?></strong></div>
                            <div><span>Vidas</span><strong><?= (int)$competition['initial_lives'] ?>/<?= (int)$competition['max_lives'] ?></strong></div>
                            <div><span>Entrada</span><strong><?= (int)$competition['token_consumption_amount'] ?> token</strong></div>
                            <div><span>Participantes</span><strong><?= (int)$competition['participants_count'] ?></strong></div>
                        </div>

                        <?php if ($activeCompetition): ?>
                            <button class="c-btn-secondary" type="button" disabled>Voce ja esta em uma competicao</button>
                        <?php elseif (!empty($competition['joined_id'])): ?>
                            <button class="c-btn-secondary" type="button" disabled>Entrada confirmada</button>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="join">
                                <input type="hidden" name="competition_id" value="<?= (int)$competition['id'] ?>">
                                <button class="c-btn-primary" type="submit">Usar tokens</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($openCompetitions)): ?>
                    <p>Nenhuma competicao aberta para entrada no momento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
