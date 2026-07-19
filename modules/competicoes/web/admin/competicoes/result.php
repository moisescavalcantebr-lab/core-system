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

$stmt = $pdo->prepare("
    SELECT *
    FROM matches
    WHERE competition_id = ?
      AND score_a IS NULL
      AND score_b IS NULL
      AND status <> 'finished'
    ORDER BY COALESCE(match_date, created_at) ASC
");
$stmt->execute([$id]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Adicionar Resultado';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Adicionar Resultado</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$competition['name']) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if (empty($matches)): ?>
            <div class="c-card">
                <p>Nenhuma partida cadastrada para receber resultado.</p>
                <a href="<?= PROJECT_URL ?>/admin/partidas/create.php?competition_id=<?= (int)$competition['id'] ?>" class="c-btn-secondary">
                    Criar Partida
                </a>
            </div>
        <?php else: ?>
            <form action="<?= PROJECT_URL ?>/admin/competicoes/result_store.php?id=<?= (int)$competition['id'] ?>" method="POST" class="c-card">
                <?= csrf_field(); ?>

                <div class="c-form-grid">
                    <div class="c-form-group">
                        <label>Partida</label>
                        <select name="match_id" class="c-input" required>
                            <?php foreach ($matches as $match): ?>
                                <option value="<?= (int)$match['id'] ?>">
                                    <?= htmlspecialchars($match['participant_a'] . ' x ' . ($match['participant_b'] ?? '-')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Gols meu time/equipe A</label>
                        <input type="number" name="score_a" class="c-input" min="0" required>
                    </div>

                    <div class="c-form-group">
                        <label>Gols adversário/equipe B</label>
                        <input type="number" name="score_b" class="c-input" min="0" required>
                    </div>
                </div>

                <button class="c-btn-secondary">Salvar Resultado</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
