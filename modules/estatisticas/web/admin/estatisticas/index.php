<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$players = [];
$competitions = [];
$types = $pdo->query("SELECT * FROM statistic_types WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

try {
    $players = $pdo->query("SELECT id, name FROM players ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $players = [];
}

try {
    $competitions = $pdo->query("SELECT id, name, context FROM competitions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $competitions = [];
}

$records = $pdo->query("
    SELECT sr.*, p.name AS player_name, st.name AS statistic_name, c.name AS competition_name
    FROM statistic_records sr
    LEFT JOIN players p ON p.id = sr.player_id
    LEFT JOIN statistic_types st ON st.id = sr.statistic_type_id
    LEFT JOIN competitions c ON c.id = sr.competition_id
    ORDER BY sr.id DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query("
    SELECT sr.context, st.name AS statistic_name, COALESCE(SUM(sr.value_number), 0) AS total
    FROM statistic_records sr
    LEFT JOIN statistic_types st ON st.id = sr.statistic_type_id
    GROUP BY sr.context, st.name
    ORDER BY sr.context, st.name
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Estatisticas';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Estatisticas</h1>
            <p class="c-page-subtitle">Dados basicos por jogador e contexto</p>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <h3>Resumo</h3>

            <?php if (empty($summary)): ?>
                <p>Nenhuma estatistica registrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Contexto</th>
                                <th>Estatistica</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary as $row): ?>
                                <tr>
                                    <td><?= $row['context'] === 'internal' ? 'Interna' : 'Externa' ?></td>
                                    <td><?= htmlspecialchars($row['statistic_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars((string)$row['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <form action="<?= PROJECT_URL ?>/admin/estatisticas/store.php" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Contexto</label>
                    <select name="context" class="c-input">
                        <option value="external">Externa</option>
                        <option value="internal">Interna</option>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Jogador</label>
                    <select name="player_id" class="c-input" required>
                        <option value="">Selecione</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= (int)$player['id'] ?>"><?= htmlspecialchars($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Estatistica</label>
                    <select name="statistic_type_id" class="c-input" required>
                        <option value="">Selecione</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Competicao</label>
                    <select name="competition_id" class="c-input">
                        <option value="">Sem competicao</option>
                        <?php foreach ($competitions as $competition): ?>
                            <option value="<?= (int)$competition['id'] ?>">
                                <?= htmlspecialchars($competition['name']) ?> (<?= $competition['context'] === 'internal' ? 'Interna' : 'Externa' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Valor</label>
                    <input type="number" name="value_number" class="c-input" step="0.01" value="1">
                </div>

                <div class="c-form-group">
                    <label>Data</label>
                    <input type="date" name="recorded_at" class="c-input">
                </div>
            </div>

            <button class="c-btn-secondary">Registrar Estatistica</button>
        </form>

        <div class="c-card">
            <h3>Registros</h3>

            <?php if (empty($records)): ?>
                <p>Nenhum registro cadastrado.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Jogador</th>
                                <th>Contexto</th>
                                <th>Competicao</th>
                                <th>Estatistica</th>
                                <th>Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['player_name'] ?? '-') ?></td>
                                    <td><?= $record['context'] === 'internal' ? 'Interna' : 'Externa' ?></td>
                                    <td><?= htmlspecialchars($record['competition_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($record['statistic_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars((string)($record['value_number'] ?? $record['value_text'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars($record['recorded_at'] ?? '-') ?></td>
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
