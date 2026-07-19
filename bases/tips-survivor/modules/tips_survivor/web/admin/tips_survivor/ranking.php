<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireAdmin();
tipsEnsureSchema($pdo);

$title = 'Ranking - Tips Survivor';
$rows = $pdo->query("
    SELECT cu.*, c.name AS competition_name
    FROM tips_competition_users cu
    INNER JOIN tips_competitions c ON c.id = cu.competition_id
    ORDER BY cu.status = 'active' DESC, cu.lives DESC, cu.points DESC, cu.joined_at ASC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Ranking</h1>
            <p class="c-page-subtitle">Vidas, pontos e status dos participantes por competicao.</p>
        </div>
        <?= tipsNav('ranking') ?>
    </div>

    <div class="c-page-content">
        <div class="c-card">
            <h3>Ranking geral inicial</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Competicao</th><th>Usuario</th><th>Vidas</th><th>Pontos</th><th>Tokens</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$row['competition_name']) ?></td>
                            <td>#<?= (int)$row['user_id'] ?></td>
                            <td><?= (int)$row['lives'] ?></td>
                            <td><?= (int)$row['points'] ?></td>
                            <td><?= (int)$row['tokens_generated'] ?></td>
                            <td><span class="c-badge <?= tipsBadgeClass((string)$row['status']) ?>"><?= htmlspecialchars(tipsStatusLabel((string)$row['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6">Nenhum participante ranqueado ainda.</td></tr>
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
