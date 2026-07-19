<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

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

$pdo->exec("
    CREATE TABLE IF NOT EXISTS match_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        match_id INT NOT NULL,
        player_id INT NOT NULL,
        status ENUM('present','excused_absence','no_response','confirmed_absent','justified_absent') NOT NULL DEFAULT 'no_response',
        points DECIMAL(4,1) NOT NULL DEFAULT 0.0,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_match_player_attendance (match_id, player_id),
        INDEX(match_id),
        INDEX(player_id),
        INDEX(status),
        INDEX(points)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$notice = (string)($_GET['notice'] ?? '');
$noticeMessages = [
    'saved' => 'Ajustes salvos.',
];

$stmt = $pdo->prepare("
    SELECT
        p.id AS player_id,
        p.name AS player_name,
        pp.code AS position_code,
        mc.status AS confirmation_status,
        ma.status AS attendance_status,
        ma.notes AS attendance_notes
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    INNER JOIN match_confirmations mc ON mc.match_id = ? AND mc.player_id = p.id AND mc.status = 'confirmed'
    LEFT JOIN match_attendance ma ON ma.match_id = ? AND ma.player_id = p.id
    WHERE p.status = 'active'
    ORDER BY
        CASE mc.status WHEN 'confirmed' THEN 0 WHEN 'declined' THEN 1 ELSE 2 END,
        pp.sort_order ASC,
        p.name ASC
");
$stmt->execute([$id, $id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

function attendanceDefaultStatus(?string $confirmationStatus): string
{
    return match ($confirmationStatus) {
        'confirmed' => 'present',
        'declined' => 'excused_absence',
        default => 'no_response',
    };
}

function attendanceConfirmationLabel(?string $status): string
{
    return match ($status) {
        'confirmed' => 'Confirmou',
        'declined' => 'Avisou que nao vai',
        default => 'Sem resposta',
    };
}

$title = 'Ajustar Presenca';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Ajustar presença</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/partidas/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php if (isset($noticeMessages[$notice])): ?>
            <div class="c-alert c-alert--success"><?= htmlspecialchars($noticeMessages[$notice]) ?></div>
        <?php endif; ?>

        <div class="c-dashboard-grid c-attendance-summary">
            <div class="c-dashboard-card c-card--info">
                <h4>Competição</h4>
                <div class="c-metric c-metric--text"><?= htmlspecialchars($match['competition_name'] ?? '-') ?></div>
            </div>
            <div class="c-dashboard-card c-card--neutral">
                <h4>Placar</h4>
                <div class="c-metric"><?= (int)($match['score_a'] ?? 0) ?> x <?= (int)($match['score_b'] ?? 0) ?></div>
            </div>
            <div class="c-dashboard-card c-card--warning">
                <h4>Cartões</h4>
                <div class="c-metric c-metric--text">A <?= (int)($match['yellow_cards_a'] ?? 0) ?> · V <?= (int)($match['red_cards_a'] ?? 0) ?></div>
            </div>
        </div>

        <form action="<?= PROJECT_URL ?>/admin/partidas/attendance_finish.php?id=<?= (int)$match['id'] ?>" method="POST" class="c-card">
            <?= csrf_field(); ?>
            <h3>Confirmados</h3>
            <p>Todos entram como presente por padrão. Ajuste apenas quem confirmou e não compareceu ou teve justificativa.</p>

            <?php if (empty($players)): ?>
                <p>Nenhum jogador confirmado para esta partida.</p>
            <?php else: ?>
                <div class="c-attendance-list">
                    <?php foreach ($players as $player): ?>
                        <?php
                            $playerId = (int)$player['player_id'];
                            $selectedStatus = (string)($player['attendance_status'] ?? attendanceDefaultStatus($player['confirmation_status'] ?? null));
                        ?>
                        <div class="c-attendance-row">
                            <div>
                                <strong><?= htmlspecialchars($player['player_name']) ?></strong>
                                <span><?= htmlspecialchars(($player['position_code'] ?? '-') . ' · ' . attendanceConfirmationLabel($player['confirmation_status'] ?? null)) ?></span>
                            </div>
                            <select name="attendance[<?= $playerId ?>][status]">
                                <option value="present" <?= $selectedStatus === 'present' ? 'selected' : '' ?>>Presente (+1)</option>
                                <option value="excused_absence" <?= $selectedStatus === 'excused_absence' ? 'selected' : '' ?>>Avisou que nao vai (+0,5)</option>
                                <option value="no_response" <?= $selectedStatus === 'no_response' ? 'selected' : '' ?>>Sem resposta (0)</option>
                                <option value="confirmed_absent" <?= $selectedStatus === 'confirmed_absent' ? 'selected' : '' ?>>Confirmou e faltou (-1)</option>
                                <option value="justified_absent" <?= $selectedStatus === 'justified_absent' ? 'selected' : '' ?>>Falta justificada (0)</option>
                            </select>
                            <input
                                type="text"
                                name="attendance[<?= $playerId ?>][notes]"
                                value="<?= htmlspecialchars((string)($player['attendance_notes'] ?? '')) ?>"
                                placeholder="Observação">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="c-attendance-actions">
                <button class="c-btn-secondary" name="action" value="save">Salvar ajuste</button>
                <button class="c-btn-secondary c-attendance-finish" name="action" value="finish" onclick="return confirm('Salvar presença e finalizar esta partida?');">Salvar e finalizar</button>
            </div>
        </form>
    </div>
</div>

<style>
.c-attendance-summary {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.c-attendance-summary .c-dashboard-card {
    min-height: 76px;
    padding: 12px;
}

.c-attendance-summary .c-metric {
    font-size: 20px;
}

.c-attendance-list {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.c-attendance-row {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) minmax(180px, .8fr) minmax(180px, .8fr);
    gap: 8px;
    align-items: center;
    border: 1px solid rgba(148, 163, 184, .22);
    background: rgba(15, 23, 42, .34);
    padding: 8px;
}

.c-attendance-row strong,
.c-attendance-row span {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-attendance-row span {
    color: rgba(226, 232, 240, .64);
    font-size: 11px;
}

.c-attendance-row select,
.c-attendance-row input {
    width: 100%;
    min-height: 34px;
    border: 1px solid rgba(148, 163, 184, .28);
    background: rgba(2, 6, 23, .62);
    color: #e5e7eb;
    padding: 7px;
}

.c-attendance-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 12px;
}

.c-attendance-finish {
    border-color: rgba(34, 197, 94, .55);
    background: rgba(34, 197, 94, .16);
    color: #dcfce7;
}

@media (max-width: 760px) {
    .c-attendance-summary {
        grid-template-columns: 1fr;
    }

    .c-attendance-row {
        grid-template-columns: 1fr;
    }

    .c-attendance-actions .c-btn-secondary {
        width: 100%;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
