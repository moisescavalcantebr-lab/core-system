<?php
$availableFields = classificationAvailableFields();
$activeFields = classificationDecodeFields($table['active_fields'] ?? '[]');
$selectedCompetitionId = (int)($table['competition_id'] ?? 0);
$isCreate = empty($table['id']);
$competitions = [];

try {
    if ($isCreate) {
        $competitions = $pdo->query("
            SELECT c.id, c.name, c.status
            FROM competitions c
            LEFT JOIN classification_tables t ON t.competition_id = c.id
            WHERE t.id IS NULL
            ORDER BY c.status ASC, c.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.status
            FROM competitions c
            LEFT JOIN classification_tables t
                ON t.competition_id = c.id
               AND t.id <> ?
            WHERE t.id IS NULL
            ORDER BY c.status ASC, c.name ASC
        ");
        $stmt->execute([(int)$table['id']]);
        $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $competitions = [];
}
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($title) ?></h1>
            <p class="c-page-subtitle">Escolha os campos que esta base precisa usar</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/classificacao/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form action="<?= htmlspecialchars($formAction) ?>" method="POST">

            <?= csrf_field(); ?>

            <div class="c-card">
                <h3>Dados</h3>

                <div class="c-form-group">
                    <label>Competicao</label>
                    <select name="competition_id" class="c-input" required>
                        <option value="">Selecione</option>
                        <?php foreach ($competitions as $competition): ?>
                            <option value="<?= (int)$competition['id'] ?>" <?= $selectedCompetitionId === (int)$competition['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($competition['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-grid">
                    <div class="c-form-group">
                        <label>Ordenar por</label>
                        <select name="sort_field" class="c-input">
                            <option value="name" <?= ($table['sort_field'] ?? '') === 'name' ? 'selected' : '' ?>>Nome</option>
                            <?php foreach ($availableFields as $field => $config): ?>
                                <option value="<?= htmlspecialchars($field) ?>" <?= ($table['sort_field'] ?? '') === $field ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($config['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Direcao</label>
                        <select name="sort_direction" class="c-input">
                            <option value="asc" <?= ($table['sort_direction'] ?? '') === 'asc' ? 'selected' : '' ?>>Crescente</option>
                            <option value="desc" <?= ($table['sort_direction'] ?? '') === 'desc' ? 'selected' : '' ?>>Decrescente</option>
                        </select>
                    </div>
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <select name="status" class="c-input">
                        <option value="active" <?= ($table['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativa</option>
                        <option value="inactive" <?= ($table['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativa</option>
                    </select>
                </div>
            </div>

            <div class="c-card">
                <h3>Campos Ativos</h3>

                <div class="c-table-wrapper">
                    <table class="c-table">
                        <tbody>
                            <?php foreach ($availableFields as $field => $config): ?>
                                <tr>
                                    <td style="width:60px;">
                                        <input
                                            type="checkbox"
                                            name="active_fields[]"
                                            value="<?= htmlspecialchars($field) ?>"
                                            <?= in_array($field, $activeFields, true) ? 'checked' : '' ?>
                                        >
                                    </td>
                                    <td><strong><?= htmlspecialchars($config['label']) ?></strong></td>
                                    <td><?= htmlspecialchars($config['type'] === 'number' ? 'Numero' : 'Texto') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="c-btn-secondary">
                <?= htmlspecialchars($submitLabel) ?>
            </button>

        </form>

    </div>

</div>

