<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$title = 'Escalacao';

$total = (int)$pdo->query("SELECT COUNT(*) FROM lineup_sheets")->fetchColumn();
$published = (int)$pdo->query("SELECT COUNT(*) FROM lineup_sheets WHERE status = 'published'")->fetchColumn();
$lineups = $pdo->query("SELECT * FROM lineup_sheets ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Escalacao</h1>
            <p class="c-page-subtitle">Listas, equipes e participantes por evento</p>
        </div>
    </div>

    <div class="c-page-content">
        <div class="c-dashboard-grid">
            <div class="c-dashboard-card c-card--neutral">
                <h4>Total</h4>
                <div class="c-metric"><?= $total ?></div>
            </div>

            <div class="c-dashboard-card c-card--success">
                <h4>Publicadas</h4>
                <div class="c-metric"><?= $published ?></div>
            </div>
        </div>

        <div class="c-card">
            <h3>Escalacoes</h3>

            <?php if (empty($lineups)): ?>
                <p>Nenhuma escalacao cadastrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Grupo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineups as $lineup): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($lineup['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($lineup['group_name'] ?? '-') ?></td>
                                    <td><span class="c-badge c-badge--neutral"><?= htmlspecialchars($lineup['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
