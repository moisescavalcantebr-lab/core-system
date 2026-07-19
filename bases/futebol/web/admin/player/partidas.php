<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../partidas/lineup_helpers.php';
require __DIR__ . '/../financeiro/helpers.php';

requireProjectAuth();
matchLineupEnsureSchema($pdo);

$user = projectUser();

$stmt = $pdo->prepare("SELECT * FROM players WHERE user_id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

$financeEnabled = !function_exists('projectPlanAllows') || projectPlanAllows('finance_enabled', true);
$balance = $financeEnabled ? financePlayerBalance($pdo, (int)$player['id']) : 0.0;

$stmt = $pdo->prepare("
    SELECT m.*, c.name AS competition_name, mc.status AS confirmation_status, mc.confirmed_at,
           mc.payment_status, mc.payment_amount, mc.paid_at
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    LEFT JOIN match_confirmations mc ON mc.match_id = m.id AND mc.player_id = ?
    WHERE m.status IN ('scheduled','live')
    ORDER BY COALESCE(m.match_date, m.created_at) ASC
    LIMIT 20
");
$stmt->execute([(int)$player['id']]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Minhas Partidas';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Minhas Partidas</h1>
            <p class="c-page-subtitle">Acompanhe convocações, confirme presença e veja a escalação</p>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <?php if (empty($matches)): ?>
                <p>Nenhuma partida aberta para confirmação.</p>
            <?php else: ?>
                <div class="c-player-match-list">
                    <?php foreach ($matches as $match): ?>
                        <div class="c-player-match">
                            <div>
                                <strong><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></strong>
                                <span><?= htmlspecialchars($match['competition_name'] ?? '-') ?></span>
                                <span><?= htmlspecialchars($match['match_date'] ?? '-') ?> · <?= htmlspecialchars($match['venue'] ?? '-') ?></span>
                                <?php if ($financeEnabled): ?>
                                    <span>Valor: <?= (float)($match['match_fee'] ?? 0) > 0 ? financeMoney((float)$match['match_fee']) : 'Grátis' ?></span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if (($match['confirmation_status'] ?? '') === 'confirmed'): ?>
                                    <span class="c-badge c-badge--success">Confirmado</span>
                                    <span>Última ação: <?= htmlspecialchars($match['confirmed_at'] ?? '-') ?></span>
                                <?php elseif (($match['confirmation_status'] ?? '') === 'declined'): ?>
                                    <span class="c-badge c-badge--danger">Não vou</span>
                                    <span>Última ação: <?= htmlspecialchars($match['confirmed_at'] ?? '-') ?></span>
                                <?php else: ?>
                                    <span class="c-badge c-badge--warning">Pendente</span>
                                <?php endif; ?>

                                <?php if ($financeEnabled && (float)($match['match_fee'] ?? 0) > 0): ?>
                                    <?php if (($match['payment_status'] ?? '') === 'paid'): ?>
                                        <span>Pagamento: <?= financeMoney((float)($match['payment_amount'] ?? $match['match_fee'])) ?></span>
                                    <?php else: ?>
                                        <span>Seu saldo: <?= financeMoney($balance) ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="c-player-match-actions">
                                <a href="<?= PROJECT_URL ?>/admin/player/partidas_lineup.php?id=<?= (int)$match['id'] ?>" class="c-btn-secondary">Escalação</a>
                                <?php
                                    $fee = (float)($match['match_fee'] ?? 0);
                                    $paymentStatus = (string)($match['payment_status'] ?? 'not_required');
                                    $requiresPayment = $financeEnabled && $fee > 0 && $paymentStatus !== 'paid';
                                    $hasBalance = $balance + 0.0001 >= $fee;
                                ?>
                                <?php if ($requiresPayment): ?>
                                    <?php if ($hasBalance): ?>
                                        <form action="<?= PROJECT_URL ?>/admin/player/partidas_pay.php?id=<?= (int)$match['id'] ?>" method="POST">
                                            <?= csrf_field(); ?>
                                            <button class="c-btn-secondary">Pagar <?= financeMoney($fee) ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="c-player-match-warning">Saldo insuficiente para confirmar.</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (($match['status'] ?? '') === 'scheduled'): ?>
                                    <form action="<?= PROJECT_URL ?>/admin/player/partidas_confirm.php?id=<?= (int)$match['id'] ?>" method="POST">
                                        <?= csrf_field(); ?>
                                        <?php if (($match['confirmation_status'] ?? '') !== 'confirmed' && !$requiresPayment): ?>
                                            <button class="c-btn-secondary" name="status" value="confirmed">Confirmar</button>
                                        <?php endif; ?>
                                        <?php if (($match['confirmation_status'] ?? '') !== 'declined'): ?>
                                            <button class="c-btn-secondary" name="status" value="declined">Não vou</button>
                                        <?php endif; ?>
                                    </form>
                                <?php else: ?>
                                    <span class="c-player-match-live">Em andamento</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-player-match-list { display:grid; gap:10px; }
.c-player-match { display:grid; grid-template-columns: minmax(0, 1fr) minmax(150px, auto) auto; gap:12px; align-items:center; border:1px solid rgba(148,163,184,.24); background:rgba(15,23,42,.34); padding:12px; }
.c-player-match span { display:block; color:rgba(226,232,240,.72); margin-top:4px; }
.c-player-match-actions, .c-player-match form { display:flex; gap:8px; align-items:center; }
.c-player-match-warning { color:#fbbf24 !important; font-weight:700; }
.c-player-match-live { color:#93c5fd !important; font-weight:800; }
@media (max-width: 800px) { .c-player-match { grid-template-columns:1fr; } }
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
