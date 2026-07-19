<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$title = 'Partidas';
unset($_SESSION['flash']);

$notice = (string)($_GET['notice'] ?? '');
$noticeMessages = [
    'score_saved' => 'Resultado salvo.',
    'finished' => 'Partida finalizada.',
    'invalid' => 'Partida invalida.',
    'not_found' => 'Partida nao encontrada.',
];

function ensureMatchCardColumns(PDO $pdo): void
{
    $columns = [
        'yellow_cards_a' => "ALTER TABLE matches ADD COLUMN yellow_cards_a INT NOT NULL DEFAULT 0 AFTER score_b",
        'yellow_cards_b' => "ALTER TABLE matches ADD COLUMN yellow_cards_b INT NOT NULL DEFAULT 0 AFTER yellow_cards_a",
        'red_cards_a' => "ALTER TABLE matches ADD COLUMN red_cards_a INT NOT NULL DEFAULT 0 AFTER yellow_cards_b",
        'red_cards_b' => "ALTER TABLE matches ADD COLUMN red_cards_b INT NOT NULL DEFAULT 0 AFTER red_cards_a",
    ];

    foreach ($columns as $column => $sql) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'matches'
                  AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column]);

            if ((int)$stmt->fetchColumn() === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            continue;
        }
    }
}

function ensureMatchAttendanceSchema(PDO $pdo): void
{
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
}

ensureMatchCardColumns($pdo);
ensureMatchAttendanceSchema($pdo);

$currentMatch = $pdo->query("
    SELECT m.*, e.name AS event_name, c.name AS competition_name
    FROM matches m
    LEFT JOIN match_events e ON e.id = m.event_id
    LEFT JOIN competitions c ON c.id = m.competition_id
    WHERE m.status = 'live'
    ORDER BY COALESCE(m.match_date, m.created_at) DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$currentMatch) {
    $currentMatch = null;
}

$statusLabels = [
    'scheduled' => 'Agendada',
    'live' => 'Em andamento',
    'finished' => 'Finalizada',
    'canceled' => 'Cancelada',
];

function matchStatusBadge(?string $status): string
{
    return match ($status) {
        'live' => 'c-badge--success',
        'scheduled' => 'c-badge--info',
        'finished' => 'c-badge--neutral',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Partidas</h1>
            <p class="c-page-subtitle">Acompanhamento básico da partida em andamento</p>
        </div>

        <?php if ($currentMatch): ?>
            <div class="c-live-top-menu">
                <?php if (!empty($currentMatch['competition_id'])): ?>
                    <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$currentMatch['competition_id'] ?>" class="c-btn-secondary">
                        Ver competição
                    </a>
                <?php endif; ?>
                <a href="<?= PROJECT_URL ?>/admin/partidas/lineup.php?id=<?= (int)$currentMatch['id'] ?>" class="c-btn-secondary">
                    Escalação
                </a>
                <a href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$currentMatch['id'] ?>" class="c-btn-secondary">
                    Ver partida
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="c-page-content">
        <?php if (isset($noticeMessages[$notice])): ?>
            <div class="c-alert <?= in_array($notice, ['invalid', 'not_found'], true) ? 'c-alert--error' : 'c-alert--success' ?>">
                <?= htmlspecialchars($noticeMessages[$notice]) ?>
            </div>
        <?php endif; ?>

        <div class="c-card">
            <h3>Partida em andamento</h3>

            <?php if (!$currentMatch): ?>
                <p>Nenhuma partida em andamento.</p>
            <?php else: ?>
                <div class="c-live-match">
                    <div class="c-live-meta-card">
                        <div class="c-live-competition-info">
                            <span>Competição</span>
                            <strong><?= htmlspecialchars($currentMatch['competition_name'] ?? $currentMatch['event_name'] ?? '-') ?></strong>
                        </div>
                        <div class="c-live-status-info">
                            <span>Status</span>
                            <strong>
                                <span class="c-badge <?= matchStatusBadge($currentMatch['status'] ?? null) ?>">
                                    <?= htmlspecialchars($statusLabels[$currentMatch['status']] ?? $currentMatch['status']) ?>
                                </span>
                            </strong>
                        </div>
                        <div class="c-live-finish-slot">
                            <span>Ação rápida</span>
                            <?php if (($currentMatch['status'] ?? '') !== 'finished'): ?>
                                <button
                                    form="liveScoreForm"
                                    name="action"
                                    value="adjust"
                                    class="c-btn-secondary c-live-adjust-button">
                                    Ajustar e finalizar
                                </button>
                                <button
                                    form="liveScoreForm"
                                    name="action"
                                    value="finish"
                                    class="c-btn-secondary c-live-finish-button"
                                    onclick="return confirm('Finalizar esta partida?');">
                                    Finalizar partida
                                </button>
                            <?php else: ?>
                                <strong>Partida já finalizada</strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form id="liveScoreForm" class="c-live-score" action="<?= PROJECT_URL ?>/admin/partidas/live_update.php?id=<?= (int)$currentMatch['id'] ?>" method="POST">
                        <?= csrf_field(); ?>
                        <strong><?= htmlspecialchars($currentMatch['participant_a']) ?></strong>
                        <input type="number" name="score_a" min="0" value="<?= htmlspecialchars((string)($currentMatch['score_a'] ?? 0)) ?>" aria-label="Placar <?= htmlspecialchars($currentMatch['participant_a']) ?>">
                        <em>x</em>
                        <input type="number" name="score_b" min="0" value="<?= htmlspecialchars((string)($currentMatch['score_b'] ?? 0)) ?>" aria-label="Placar <?= htmlspecialchars($currentMatch['participant_b'] ?? 'adversario') ?>">
                        <strong><?= htmlspecialchars($currentMatch['participant_b'] ?? '-') ?></strong>

                        <div class="c-live-cards">
                            <div class="c-live-card-team">
                                <span>Cartões do meu time</span>
                                <label>
                                    Amarelos
                                    <input type="number" name="yellow_cards_a" min="0" value="<?= (int)($currentMatch['yellow_cards_a'] ?? 0) ?>">
                                </label>
                                <label>
                                    Vermelhos
                                    <input type="number" name="red_cards_a" min="0" value="<?= (int)($currentMatch['red_cards_a'] ?? 0) ?>">
                                </label>
                            </div>
                            <input type="hidden" name="yellow_cards_b" value="<?= (int)($currentMatch['yellow_cards_b'] ?? 0) ?>">
                            <input type="hidden" name="red_cards_b" value="<?= (int)($currentMatch['red_cards_b'] ?? 0) ?>">

                            <div class="c-live-update-card">
                                <span>Salvar alterações</span>
                                <button class="c-btn-secondary c-live-update-button" name="action" value="score">
                                    Atualizar
                                </button>
                            </div>
                        </div>

                    </form>

                </div>

            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-live-match {
    display: grid;
    grid-template-columns: minmax(240px, .9fr) minmax(420px, 2fr);
    gap: 12px;
    align-items: stretch;
}

.c-live-top-menu {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}

.c-live-match > div {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 12px;
}

.c-live-meta-card {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: center;
}

.c-live-meta-card > div:nth-child(2) {
    text-align: right;
}

.c-live-finish-slot {
    grid-column: 1 / -1;
    border-top: 1px solid rgba(148, 163, 184, .18);
    padding-top: 12px;
}

.c-live-match span,
.c-live-match em {
    display: block;
    color: rgba(226, 232, 240, .72);
    font-style: normal;
}

.c-live-score {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 58px 20px 58px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
    text-align: center;
}

.c-live-score input {
    display: grid;
    height: 42px;
    border: 1px solid rgba(148, 163, 184, .32);
    background: rgba(76, 139, 245, .12);
    color: #fff;
    font-size: 22px;
    font-weight: 800;
    text-align: center;
    width: 100%;
}

.c-live-score strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-live-cards {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 160px;
    gap: 8px;
    align-items: stretch;
}

.c-live-card-team {
    border: 1px solid rgba(96, 165, 250, .22);
    background: linear-gradient(135deg, rgba(15, 23, 42, .36), rgba(30, 41, 59, .28));
    padding: 10px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) repeat(2, 86px);
    gap: 10px;
    align-items: end;
    text-align: left;
}

.c-live-card-team > span {
    font-weight: 800;
    color: var(--text-primary);
}

.c-live-card-team label {
    display: grid;
    gap: 4px;
    color: rgba(226, 232, 240, .72);
    font-size: 11px;
}

.c-live-card-team input {
    height: 32px;
    font-size: 15px;
    font-weight: 800;
}

.c-live-card-team label:first-of-type input {
    border-color: rgba(234, 179, 8, .38);
    background: rgba(234, 179, 8, .12);
}

.c-live-card-team label:last-of-type input {
    border-color: rgba(239, 68, 68, .42);
    background: rgba(239, 68, 68, .12);
}

.c-live-update-card {
    border: 1px solid rgba(59, 130, 246, .36);
    background: rgba(37, 99, 235, .1);
    padding: 10px;
    display: grid;
    gap: 8px;
    align-content: center;
}

.c-live-update-button {
    width: 100%;
    min-height: 36px;
    margin: 0;
    border-color: rgba(96, 165, 250, .62);
    background: rgba(59, 130, 246, .22);
    color: #dbeafe;
    font-weight: 900;
}

.c-live-update-button:hover {
    background: rgba(59, 130, 246, .34);
}

.c-live-finish-button {
    width: 100%;
    margin-top: 8px;
    border-color: rgba(34, 197, 94, .55);
    background: rgba(34, 197, 94, .16);
    color: #dcfce7;
    font-weight: 800;
}

.c-live-adjust-button {
    display: block;
    width: 100%;
    margin-top: 8px;
    text-align: center;
}

.c-live-finish-button:hover {
    background: rgba(34, 197, 94, .26);
}

.c-match-create-shortcut {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    max-width: 560px;
    margin-top: 14px;
}

.c-match-create-shortcut .c-form-group {
    margin: 0;
}

@media (max-width: 980px) {
    .c-live-match {
        grid-template-columns: 1fr;
    }

    .c-live-meta-card {
        display: contents;
    }

    .c-live-competition-info,
    .c-live-status-info,
    .c-live-finish-slot {
        border: 1px solid rgba(148, 163, 184, .24);
        background: rgba(15, 23, 42, .34);
        padding: 12px;
    }

    .c-live-competition-info {
        order: 1;
    }

    .c-live-status-info {
        order: 2;
        text-align: left;
    }

    .c-live-score {
        order: 3;
    }

    .c-live-card-team {
        order: 4;
    }

    .c-live-update-card {
        order: 5;
    }

    .c-live-finish-slot {
        order: 6;
        border-top: 1px solid rgba(148, 163, 184, .24);
    }

    .c-live-cards,
    .c-live-card-team {
        grid-template-columns: 1fr;
    }

    .c-live-top-menu {
        justify-content: flex-start;
        width: 100%;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
