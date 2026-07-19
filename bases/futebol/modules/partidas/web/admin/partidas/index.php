<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/lineup_helpers.php';

requireProjectAdmin();
matchLineupEnsureSchema($pdo);

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
    SELECT m.*, e.name AS event_name, c.name AS competition_name, c.type AS competition_type, c.context AS competition_context
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

$nextMatch = null;
if (!$currentMatch) {
    $nextMatch = $pdo->query("
        SELECT m.*, e.name AS event_name, c.name AS competition_name, c.type AS competition_type, c.context AS competition_context
        FROM matches m
        LEFT JOIN match_events e ON e.id = m.event_id
        LEFT JOIN competitions c ON c.id = m.competition_id
        WHERE m.status = 'scheduled'
        ORDER BY COALESCE(m.match_date, m.created_at) ASC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: null;
}

$currentIsFriendlyExternal = $currentMatch
    && ($currentMatch['competition_context'] ?? '') === 'external'
    && ($currentMatch['competition_type'] ?? '') === 'friendly';
$friendlyCardsEnabled = function_exists('getSetting')
    ? getSetting('friendly_cards_enabled', '1') !== '0'
    : true;
$liveCardsEnabled = !$currentIsFriendlyExternal || $friendlyCardsEnabled;

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
    <div class="c-live-compact-header">
        <?php if ($currentMatch): ?>
            <span class="c-live-pill">
                <span>Partida</span>
                <b><span class="c-live-status-full">Em andamento</span><span class="c-live-status-short">Ao vivo</span></b>
                <strong><?= htmlspecialchars($currentMatch['participant_a']) ?></strong>
                <em>x</em>
                <strong><?= htmlspecialchars($currentMatch['participant_b'] ?? '-') ?></strong>
            </span>
        <?php elseif ($nextMatch): ?>
            <span class="c-live-pill">
                <span>Proxima</span>
                <b>Agendada</b>
                <strong><?= htmlspecialchars($nextMatch['participant_a']) ?></strong>
                <em>x</em>
                <strong><?= htmlspecialchars($nextMatch['participant_b'] ?? '-') ?></strong>
            </span>
        <?php else: ?>
            <span class="c-live-pill">
                <span>Partida</span>
                <b>Nenhuma em andamento</b>
            </span>
        <?php endif; ?>
    </div>

    <div class="c-page-content">
        <?php if (isset($noticeMessages[$notice])): ?>
            <div class="c-alert <?= in_array($notice, ['invalid', 'not_found'], true) ? 'c-alert--error' : 'c-alert--success' ?>">
                <?= htmlspecialchars($noticeMessages[$notice]) ?>
            </div>
        <?php endif; ?>

        <div class="c-live-shell">
            <?php if (!$currentMatch): ?>
                <?php if ($nextMatch): ?>
                    <div class="c-next-match-card">
                        <div class="c-next-match-main">
                            <span>Proxima partida</span>
                            <strong><?= htmlspecialchars($nextMatch['participant_a']) ?> <small>x</small> <?= htmlspecialchars($nextMatch['participant_b'] ?? '-') ?></strong>
                        </div>
                        <div class="c-next-match-info">
                            <div>
                                <span>Competição</span>
                                <strong><?= htmlspecialchars($nextMatch['competition_name'] ?? $nextMatch['event_name'] ?? '-') ?></strong>
                            </div>
                            <div>
                                <span>Data e hora</span>
                                <strong>
                                    <?= !empty($nextMatch['match_date']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$nextMatch['match_date']))) : '-' ?>
                                </strong>
                            </div>
                            <div>
                                <span>Local</span>
                                <strong><?= htmlspecialchars((string)($nextMatch['venue'] ?? '-')) ?></strong>
                            </div>
                        </div>
                        <form action="<?= PROJECT_URL ?>/admin/partidas/start.php?id=<?= (int)$nextMatch['id'] ?>" method="POST" class="c-next-match-action">
                            <?= csrf_field(); ?>
                            <button class="c-btn-secondary c-live-finish-button" onclick="return confirm('Iniciar esta partida?');">
                                Iniciar partida
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>Nenhuma partida agendada.</p>
                <?php endif; ?>
            <?php else: ?>
                <form id="liveScoreForm" class="c-live-match" action="<?= PROJECT_URL ?>/admin/partidas/live_update.php?id=<?= (int)$currentMatch['id'] ?>" method="POST">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="score_a" value="<?= (int)($currentMatch['score_a'] ?? 0) ?>">
                        <input type="hidden" name="score_b" value="<?= (int)($currentMatch['score_b'] ?? 0) ?>">
                        <input type="hidden" name="yellow_cards_a" value="<?= $liveCardsEnabled ? (int)($currentMatch['yellow_cards_a'] ?? 0) : 0 ?>">
                        <input type="hidden" name="yellow_cards_b" value="<?= $liveCardsEnabled ? (int)($currentMatch['yellow_cards_b'] ?? 0) : 0 ?>">
                        <input type="hidden" name="red_cards_a" value="<?= $liveCardsEnabled ? (int)($currentMatch['red_cards_a'] ?? 0) : 0 ?>">
                        <input type="hidden" name="red_cards_b" value="<?= $liveCardsEnabled ? (int)($currentMatch['red_cards_b'] ?? 0) : 0 ?>">

                        <section class="c-live-panel c-live-panel--score">
                            <div class="c-live-scoreboard">
                                <div class="c-live-team">
                                    <span><?= htmlspecialchars($currentMatch['participant_a']) ?></span>
                                    <div class="c-live-counter">
                                        <button type="button" class="c-counter-btn" data-live-step="score_a" data-step="-1" aria-label="Diminuir placar do meu time">-</button>
                                        <strong data-live-value="score_a"><?= (int)($currentMatch['score_a'] ?? 0) ?></strong>
                                        <button type="button" class="c-counter-btn" data-live-step="score_a" data-step="1" aria-label="Aumentar placar do meu time">+</button>
                                    </div>
                                </div>
                                <em>x</em>
                                <div class="c-live-team">
                                    <span><?= htmlspecialchars($currentMatch['participant_b'] ?? '-') ?></span>
                                    <div class="c-live-counter">
                                        <button type="button" class="c-counter-btn" data-live-step="score_b" data-step="-1" aria-label="Diminuir placar adversario">-</button>
                                        <strong data-live-value="score_b"><?= (int)($currentMatch['score_b'] ?? 0) ?></strong>
                                        <button type="button" class="c-counter-btn" data-live-step="score_b" data-step="1" aria-label="Aumentar placar adversario">+</button>
                                    </div>
                                </div>
                            </div>

                            <?php if ($liveCardsEnabled): ?>
                                <div class="c-live-card-controls">
                                    <div class="c-live-card-control c-live-card-control--yellow">
                                        <span>Amarelos</span>
                                        <div class="c-live-counter">
                                            <button type="button" class="c-counter-btn" data-live-step="yellow_cards_a" data-step="-1" aria-label="Diminuir amarelos">-</button>
                                            <strong data-live-value="yellow_cards_a"><?= (int)($currentMatch['yellow_cards_a'] ?? 0) ?></strong>
                                            <button type="button" class="c-counter-btn" data-live-step="yellow_cards_a" data-step="1" aria-label="Aumentar amarelos">+</button>
                                        </div>
                                    </div>
                                    <div class="c-live-card-control c-live-card-control--red">
                                        <span>Vermelhos</span>
                                        <div class="c-live-counter">
                                            <button type="button" class="c-counter-btn" data-live-step="red_cards_a" data-step="-1" aria-label="Diminuir vermelhos">-</button>
                                            <strong data-live-value="red_cards_a"><?= (int)($currentMatch['red_cards_a'] ?? 0) ?></strong>
                                            <button type="button" class="c-counter-btn" data-live-step="red_cards_a" data-step="1" aria-label="Aumentar vermelhos">+</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="c-live-save-row">
                                <span class="c-live-save-state" data-live-state>Salvo</span>
                                <button class="c-btn-secondary c-live-update-button" name="action" value="score">
                                    Atualizar
                                </button>
                            </div>
                        </section>

                        <section class="c-live-panel c-live-panel--meta">
                            <div class="c-live-meta-list">
                                <div>
                                    <span>Competição</span>
                                    <strong><?= htmlspecialchars($currentMatch['competition_name'] ?? $currentMatch['event_name'] ?? '-') ?></strong>
                                </div>
                                <div>
                                    <span>Status</span>
                                    <strong>
                                        <span class="c-badge <?= matchStatusBadge($currentMatch['status'] ?? null) ?>">
                                            <?= htmlspecialchars($statusLabels[$currentMatch['status']] ?? $currentMatch['status']) ?>
                                        </span>
                                    </strong>
                                </div>
                            </div>
                            <?php if (($currentMatch['status'] ?? '') !== 'finished'): ?>
                                <?php if (!$currentIsFriendlyExternal): ?>
                                    <button
                                        name="action"
                                        value="adjust"
                                        class="c-btn-secondary c-live-adjust-button">
                                        Ajustar e finalizar
                                    </button>
                                <?php endif; ?>
                                <button
                                    name="action"
                                    value="finish"
                                    class="c-btn-secondary c-live-finish-button"
                                    onclick="return confirm('Finalizar esta partida?');">
                                    Finalizar partida
                                </button>
                            <?php else: ?>
                                <strong>Partida ja finalizada</strong>
                            <?php endif; ?>
                            <div class="c-live-inline-links">
                                <?php if (!empty($currentMatch['competition_id'])): ?>
                                    <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$currentMatch['competition_id'] ?>" class="c-btn-secondary">
                                        Competição
                                    </a>
                                <?php endif; ?>
                                <?php if (!$currentIsFriendlyExternal): ?>
                                    <a href="<?= PROJECT_URL ?>/admin/partidas/lineup.php?id=<?= (int)$currentMatch['id'] ?>" class="c-btn-secondary">
                                        Escalação
                                    </a>
                                <?php endif; ?>
                                <a href="<?= PROJECT_URL ?>/admin/partidas/show.php?id=<?= (int)$currentMatch['id'] ?>" class="c-btn-secondary">
                                    Detalhes
                                </a>
                            </div>
                        </section>

                        <section class="c-live-panel c-live-panel--start">
                            <span>Start</span>
                            <strong>Recursos avancados</strong>
                            <p>Espaco reservado para eventos, estatisticas e controles extras.</p>
                        </section>
                    </form>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($currentMatch): ?>
<script>
(() => {
    const form = document.getElementById('liveScoreForm');
    if (!form) return;

    const state = form.querySelector('[data-live-state]');
    let saveTimer = null;
    let controller = null;

    const setState = (text, mode = '') => {
        if (!state) return;
        state.textContent = text;
        state.dataset.mode = mode;
    };

    const syncValue = (name, value) => {
        const input = form.elements[name];
        const output = form.querySelector(`[data-live-value="${name}"]`);
        if (input) input.value = String(value);
        if (output) output.textContent = String(value);
    };

    const save = () => {
        if (controller) controller.abort();
        controller = new AbortController();

        const data = new FormData(form);
        data.set('action', 'score');
        setState('Salvando...', 'saving');

        fetch(form.action, {
            method: 'POST',
            body: data,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'fetch',
            },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error('Falha ao salvar');
                return response.json();
            })
            .then((payload) => {
                if (!payload.ok) throw new Error(payload.message || 'Falha ao salvar');
                setState('Salvo', 'saved');
            })
            .catch((error) => {
                if (error.name === 'AbortError') return;
                setState('Nao salvo', 'error');
            });
    };

    form.querySelectorAll('[data-live-step]').forEach((button) => {
        button.addEventListener('click', () => {
            const name = button.dataset.liveStep;
            const input = form.elements[name];
            if (!name || !input) return;

            const step = Number(button.dataset.step || 0);
            const next = Math.max(0, Number(input.value || 0) + step);
            syncValue(name, next);

            window.clearTimeout(saveTimer);
            saveTimer = window.setTimeout(save, 1000);
        });
    });
})();
</script>
<?php endif; ?>

<style>
.c-live-compact-header {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 16px;
}

.c-live-pill {
    min-height: 34px;
    display: inline-grid;
    grid-template-columns: auto auto auto auto auto;
    gap: 10px;
    align-items: center;
    padding: 0 12px;
    border: 1px solid rgba(139, 92, 246, .45);
    background: rgba(139, 92, 246, .08);
    color: var(--text-primary);
}

.c-live-pill span {
    color: var(--text-secondary);
    font-size: 11px;
}

.c-live-pill b,
.c-live-pill strong {
    font-size: 12px;
    font-weight: 800;
}

.c-live-status-short {
    display: none;
}

.c-live-pill em {
    color: rgba(226, 232, 240, .72);
    font-style: normal;
    font-weight: 800;
}

@media (max-width: 620px) {
    .c-live-compact-header {
        justify-content: flex-start;
    }

    .c-live-pill {
        width: 100%;
        grid-template-columns: auto 14px auto;
        justify-content: center;
        gap: 6px 8px;
    }

    .c-live-status-full {
        display: none;
    }

    .c-live-status-short {
        display: inline;
    }

    .c-live-pill > span {
        grid-column: 1 / 3;
        grid-row: 1;
        justify-self: start;
    }

    .c-live-pill b {
        grid-column: 3;
        grid-row: 1;
        justify-self: end;
        white-space: nowrap;
    }

    .c-live-pill strong:first-of-type {
        grid-column: 1;
        grid-row: 2;
        white-space: nowrap;
    }

    .c-live-pill em {
        grid-column: 2;
        grid-row: 2;
        text-align: center;
    }

    .c-live-pill strong:last-of-type {
        grid-column: 3;
        grid-row: 2;
        white-space: nowrap;
    }
}

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

.c-live-shell {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 14px;
}

.c-live-match {
    display: grid;
    grid-template-columns: minmax(260px, .95fr) minmax(260px, 1fr) minmax(220px, .72fr);
    gap: 12px;
    align-items: stretch;
    margin-top: 12px;
}

.c-live-panel {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 12px;
}

.c-live-panel--score,
.c-live-panel--meta,
.c-live-panel--start {
    display: grid;
    gap: 12px;
    align-content: start;
}

.c-live-scoreboard {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 34px minmax(0, 1fr);
    gap: 8px;
    align-items: center;
}

.c-live-team {
    display: grid;
    gap: 8px;
    min-width: 0;
    text-align: center;
}

.c-live-team span,
.c-live-meta-list span,
.c-live-panel--start span,
.c-live-card-control span {
    display: block;
    color: rgba(226, 232, 240, .72);
    font-size: 12px;
}

.c-live-team span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--text-primary);
    font-weight: 800;
}

.c-live-scoreboard em {
    color: #fff;
    font-style: normal;
    text-align: center;
    font-size: 18px;
    font-weight: 900;
    line-height: 38px;
}

.c-live-counter {
    display: grid;
    grid-template-columns: 34px minmax(48px, 1fr) 34px;
    align-items: center;
    gap: 6px;
}

.c-live-counter strong {
    border: 1px solid rgba(148, 163, 184, .32);
    background: rgba(76, 139, 245, .12);
    color: #fff;
    font-size: 24px;
    font-weight: 800;
    line-height: 38px;
    text-align: center;
}

.c-counter-btn {
    height: 38px;
    border: 1px solid rgba(148, 163, 184, .34);
    background: rgba(30, 41, 59, .78);
    color: #fff;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 900;
}

.c-counter-btn:hover {
    border-color: rgba(96, 165, 250, .62);
    background: rgba(59, 130, 246, .22);
}

.c-live-card-controls {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.c-live-card-control {
    border: 1px solid rgba(148, 163, 184, .22);
    background: rgba(2, 6, 23, .2);
    padding: 10px;
    display: grid;
    gap: 8px;
}

.c-live-card-control--yellow .c-live-counter strong {
    border-color: rgba(234, 179, 8, .38);
    background: rgba(234, 179, 8, .12);
}

.c-live-card-control--red .c-live-counter strong {
    border-color: rgba(239, 68, 68, .42);
    background: rgba(239, 68, 68, .12);
}

.c-live-save-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.c-live-save-state {
    min-width: 86px;
    border: 1px solid rgba(34, 197, 94, .25);
    background: rgba(34, 197, 94, .1);
    color: #bbf7d0;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 800;
}

.c-live-update-button {
    min-height: 30px;
    margin: 0;
    padding-inline: 14px;
}

.c-live-save-state[data-mode="saving"] {
    border-color: rgba(234, 179, 8, .35);
    background: rgba(234, 179, 8, .1);
    color: #fde68a;
}

.c-live-save-state[data-mode="error"] {
    border-color: rgba(239, 68, 68, .35);
    background: rgba(239, 68, 68, .1);
    color: #fecaca;
}

.c-live-meta-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.c-live-meta-list div {
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(2, 6, 23, .18);
    padding: 10px;
}

.c-live-meta-list strong {
    display: block;
    margin-top: 5px;
}

.c-live-finish-button {
    width: 100%;
    margin-top: 0;
}

.c-live-adjust-button {
    width: 100%;
    margin-top: 0;
}

.c-live-inline-links {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 2px;
}

.c-live-inline-links .c-btn-secondary {
    flex: 1 1 110px;
    margin: 0;
    text-align: center;
}

.c-next-match-card {
    display: grid;
    grid-template-columns: minmax(220px, .9fr) minmax(360px, 1.5fr) minmax(180px, .55fr);
    gap: 12px;
    align-items: stretch;
}

.c-next-match-main,
.c-next-match-info > div,
.c-next-match-action {
    border: 1px solid rgba(148, 163, 184, .24);
    background: rgba(15, 23, 42, .34);
    padding: 12px;
}

.c-next-match-main {
    display: grid;
    gap: 8px;
    align-content: center;
}

.c-next-match-main span,
.c-next-match-info span {
    display: block;
    color: rgba(226, 232, 240, .72);
    font-size: 12px;
}

.c-next-match-main strong {
    font-size: 18px;
}

.c-next-match-main small {
    color: rgba(226, 232, 240, .72);
    font-size: 13px;
}

.c-next-match-info {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.c-next-match-info strong {
    display: block;
    margin-top: 5px;
}

.c-next-match-action {
    display: grid;
    align-content: center;
}

.c-live-panel--start {
    color: rgba(226, 232, 240, .72);
}

.c-live-panel--start strong {
    color: var(--text-primary);
}

.c-live-panel--start p {
    margin: 0;
    line-height: 1.45;
}

@media (max-width: 980px) {
    .c-live-match {
        grid-template-columns: 1fr;
    }

    .c-next-match-card {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 620px) {
    .c-live-shell {
        padding: 10px;
    }

    .c-live-scoreboard {
        grid-template-columns: minmax(0, 1fr) 28px minmax(0, 1fr);
    }

    .c-live-counter {
        grid-template-columns: 38px minmax(0, 1fr) 38px;
    }

    .c-live-card-controls,
    .c-live-meta-list {
        grid-template-columns: 1fr 1fr;
    }

    .c-live-save-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .c-next-match-info {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 420px) {
    .c-live-card-controls,
    .c-live-meta-list {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
