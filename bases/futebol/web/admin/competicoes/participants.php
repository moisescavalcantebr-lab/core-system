<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    http_response_code(404);
    exit('Competicao nao encontrada.');
}

$players = [];

try {
    $players = $pdo->query("SELECT id, name FROM players ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $players = [];
}

$stmt = $pdo->prepare("
    SELECT cp.*, p.name AS player_name
    FROM competition_participants cp
    LEFT JOIN players p ON p.id = cp.player_id
    WHERE cp.competition_id = ?
    ORDER BY cp.sort_order ASC, cp.id ASC
");
$stmt->execute([$id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$context = (string)($competition['context'] ?? 'external');
$contextLabel = $context === 'internal' ? 'Interna' : 'Externa';
$participantTypeOptions = $context === 'internal'
    ? [
        'player' => 'Jogador',
        'internal_team' => 'Equipe interna',
    ]
    : [
        'team' => 'Adversário',
    ];

$participantTypeLabels = [
    'team' => 'Time',
    'player' => 'Jogador',
    'internal_team' => 'Equipe interna',
];

$title = 'Participantes';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Participantes</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($competition['name']) ?> · <?= htmlspecialchars($contextLabel) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/competicoes/participant_store.php?id=<?= (int)$competition['id'] ?>" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Tipo</label>
                    <select name="participant_type" class="c-input" id="participantType">
                        <?php foreach ($participantTypeOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group" id="playerField" style="<?= $context === 'internal' ? '' : 'display:none;' ?>">
                    <label>Jogador</label>
                    <select name="player_id" class="c-input">
                        <option value="">Selecione</option>
                        <?php foreach ($players as $player): ?>
                            <option value="<?= (int)$player['id'] ?>"><?= htmlspecialchars($player['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label><?= $context === 'internal' ? 'Nome da equipe interna' : 'Nome do adversário' ?></label>
                    <input type="text" name="name" class="c-input">
                </div>
            </div>

            <button class="c-btn-secondary">Adicionar Participante</button>
        </form>

        <div class="c-card">
            <h3>Participantes</h3>

            <?php if (empty($participants)): ?>
                <p>Nenhum participante cadastrado.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant): ?>
                                <tr>
                                    <td><?= htmlspecialchars($participant['player_name'] ?? $participant['name']) ?></td>
                                    <td><?= htmlspecialchars($participantTypeLabels[$participant['participant_type']] ?? $participant['participant_type']) ?></td>
                                    <td><span class="c-badge c-badge--neutral"><?= htmlspecialchars($participant['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('participantType');
    const playerField = document.getElementById('playerField');

    if (!type || !playerField) {
        return;
    }

    const syncParticipantFields = function () {
        playerField.style.display = type.value === 'player' ? '' : 'none';
    };

    type.addEventListener('change', syncParticipantFields);
    syncParticipantFields();
});
</script>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
