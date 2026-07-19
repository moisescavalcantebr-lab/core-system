<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT m.*, c.name AS competition_name
    FROM matches m
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$match) {
    http_response_code(404);
    exit('Partida nao encontrada.');
}

$stmt = $pdo->prepare("
    SELECT l.*, p.name AS player_name, pp.code AS position_code, pp.name AS position_name
    FROM match_confirmation_logs l
    INNER JOIN players p ON p.id = l.player_id
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    WHERE l.match_id = ?
    ORDER BY l.created_at DESC, l.id DESC
");
$stmt->execute([$id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabels = [
    'confirmed' => 'Confirmou',
    'declined' => 'Não vai',
];

$title = 'Histórico de Confirmações';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Histórico de Confirmações</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/partidas/lineup.php?id=<?= (int)$match['id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <div class="c-card">
            <?php if (empty($logs)): ?>
                <p>Nenhuma ação registrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th>Posição</th>
                                <th>Ação</th>
                                <th>Data/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($log['player_name']) ?></strong></td>
                                    <td><?= htmlspecialchars(trim((string)($log['position_code'] ?? '') . ' ' . (string)($log['position_name'] ?? '-'))) ?></td>
                                    <td>
                                        <span class="c-badge <?= $log['status'] === 'confirmed' ? 'c-badge--success' : 'c-badge--danger' ?>">
                                            <?= htmlspecialchars($statusLabels[$log['status']] ?? $log['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars((string)$log['created_at']) ?></td>
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
