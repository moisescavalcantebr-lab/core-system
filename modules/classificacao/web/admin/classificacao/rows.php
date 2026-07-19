<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/fields.php';

requireProjectAdmin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM classification_tables WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    http_response_code(404);
    exit('Classificacao nao encontrada');
}

$availableFields = classificationAvailableFields();
$activeFields = classificationDecodeFields($table['active_fields'] ?? '[]');

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

$title = 'Linhas da Classificacao';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($table['name']) ?></h1>
            <p class="c-page-subtitle">Linhas da classificacao</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/classificacao/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form action="<?= PROJECT_URL ?>/admin/classificacao/row_store.php?id=<?= (int)$table['id'] ?>" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <h3>Nova Linha</h3>

            <div class="c-form-grid">
                <?php foreach ($activeFields as $field): ?>
                    <?php if (!isset($availableFields[$field])) continue; ?>
                    <div class="c-form-group">
                        <label><?= htmlspecialchars($availableFields[$field]['label']) ?></label>
                        <input
                            type="<?= $availableFields[$field]['type'] === 'number' ? 'number' : 'text' ?>"
                            step="any"
                            name="fields[<?= htmlspecialchars($field) ?>]"
                            class="c-input"
                        >
                    </div>
                <?php endforeach; ?>
            </div>

            <button class="c-btn-secondary">Adicionar Linha</button>
        </form>

        <div class="c-card">
            <h3>Tabela</h3>

            <?php if (empty($rows)): ?>
                <p>Nenhuma linha cadastrada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <?php foreach ($activeFields as $field): ?>
                                    <?php if (!isset($availableFields[$field])) continue; ?>
                                    <th><?= htmlspecialchars($availableFields[$field]['label']) ?></th>
                                <?php endforeach; ?>
                                <th>Acoes</th>
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
                                    <td>
                                        <form action="<?= PROJECT_URL ?>/admin/classificacao/row_delete.php?id=<?= (int)$row['id'] ?>&table_id=<?= (int)$table['id'] ?>" method="POST" onsubmit="return confirm('Excluir esta linha?');">
                                            <?= csrf_field(); ?>
                                            <button class="c-btn-secondary">Excluir</button>
                                        </form>
                                    </td>
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

