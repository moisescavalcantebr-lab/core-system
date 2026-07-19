<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/fields.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.*, c.name AS competition_name, c.context AS competition_context
    FROM classification_tables t
    LEFT JOIN competitions c ON c.id = t.competition_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    http_response_code(404);
    exit('Classificacao nao encontrada');
}

$availableFields = classificationAvailableFields();
$activeFields = classificationDecodeFields($table['active_fields'] ?? '[]');

if (($table['competition_context'] ?? '') === 'internal' && !empty($table['competition_id'])) {
    classificationRebuildInternalCompetition($pdo, (int)$table['competition_id']);
}

$stmt = $pdo->prepare("SELECT * FROM classification_rows WHERE table_id = ?");
$stmt->execute([$id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sortField = $table['sort_field'] ?? 'position';
$sortDirection = $table['sort_direction'] ?? 'asc';

usort($rows, function ($a, $b) use ($sortField, $sortDirection) {
    $aData = json_decode((string)$a['data_json'], true) ?: [];
    $bData = json_decode((string)$b['data_json'], true) ?: [];

    $aValue = $sortField === 'name' ? $a['name'] : ($aData[$sortField] ?? null);
    $bValue = $sortField === 'name' ? $b['name'] : ($bData[$sortField] ?? null);

    if (is_numeric($aValue) && is_numeric($bValue)) {
        $result = ((float)$aValue <=> (float)$bValue);
    } else {
        $result = strcmp((string)$aValue, (string)$bValue);
    }

    return $sortDirection === 'desc' ? -$result : $result;
});

$title = 'Ver Classificacao';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars((string)($table['competition_name'] ?: $table['name'])) ?></h1>
            <p class="c-page-subtitle">Tabela de classificacao</p>
        </div>

        <div>
            <?php if (!empty($table['competition_id'])): ?>
                <a href="<?= PROJECT_URL ?>/admin/competicoes/view.php?id=<?= (int)$table['competition_id'] ?>" class="c-btn-secondary">
                    Voltar
                </a>
            <?php else: ?>
                <a href="<?= PROJECT_URL ?>/admin/classificacao/index.php" class="c-btn-secondary">
                    Voltar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-card">
            <h3>Classificacao</h3>

            <?php if (empty($rows)): ?>
                <p>Nenhum dado cadastrado.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <?php foreach ($activeFields as $field): ?>
                                    <?php if (!isset($availableFields[$field])) continue; ?>
                                    <th><?= htmlspecialchars($availableFields[$field]['label']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $rowData = json_decode((string)$row['data_json'], true) ?: []; ?>
                                <tr>
                                    <?php foreach ($activeFields as $field): ?>
                                        <?php if (!isset($availableFields[$field])) continue; ?>
                                        <td><?= htmlspecialchars(classificationFieldValue($rowData, $field)) ?></td>
                                    <?php endforeach; ?>
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
