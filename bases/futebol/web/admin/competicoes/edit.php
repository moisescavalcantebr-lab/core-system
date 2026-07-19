<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/plan_fallback.php';
require __DIR__ . '/competition_helpers.php';

requireProjectAdmin();

function competitionRelatedCount(PDO $pdo, int $competitionId): int
{
    $total = 0;

    foreach (['competition_participants', 'statistic_records', 'matches'] as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE competition_id = ?");
            $stmt->execute([$competitionId]);
            $total += (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }
    }

    return $total;
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM competitions WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$competition = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$competition) {
    http_response_code(404);
    exit('Competicao nao encontrada.');
}

if (competitionIsDefaultFriendly($competition)) {
    flash('error', 'O Amistoso padrão não pode ser editado.');
    redirect(PROJECT_URL . '/admin/competicoes/view.php?id=' . $id);
}

$hasRelatedData = competitionRelatedCount($pdo, $id) > 0;

$title = 'Editar Competicao';
$formAction = PROJECT_URL . '/admin/competicoes/update.php?id=' . $id;
$submitLabel = 'Salvar Competicao';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Editar Competicao</h1>
            <p class="c-page-subtitle"><?= htmlspecialchars((string)$competition['name']) ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/competicoes/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>
        <?php if ($hasRelatedData): ?>
            <div class="c-card">
                Esta competicao ja possui dados cadastrados. Apenas nome e temporada podem ser alterados.
            </div>
        <?php endif; ?>
        <?php require __DIR__ . '/form.php'; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
