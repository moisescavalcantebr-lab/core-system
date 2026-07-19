<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/fields.php';

requireProjectAdmin();

$title = 'Classificacao';

$stmt = $pdo->query("
    SELECT t.*, c.name AS competition_name,
        (SELECT COUNT(*) FROM classification_rows r WHERE r.table_id = t.id) AS total_rows
    FROM classification_tables t
    LEFT JOIN competitions c ON c.id = t.competition_id
    ORDER BY t.id DESC
");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Classificacao</h1>
            <p class="c-page-subtitle">Rankings e tabelas com campos configuraveis</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/classificacao/create.php" class="c-btn-secondary">
            Nova Tabela
        </a>
    </div>

    <div class="c-page-content">

        <div class="c-card">

            <?php if (empty($tables)): ?>

                <p>Nenhuma classificacao criada.</p>

            <?php else: ?>

                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Competicao</th>
                                <th>Campos</th>
                                <th>Linhas</th>
                                <th>Status</th>
                                <th>Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <?php $fields = classificationDecodeFields($table['active_fields'] ?? '[]'); ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($table['competition_name'] ?? $table['name']) ?></strong></td>
                                    <td><?= count($fields) ?></td>
                                    <td><?= (int)$table['total_rows'] ?></td>
                                    <td>
                                        <span class="c-badge <?= $table['status'] === 'active' ? 'c-badge--success' : 'c-badge--neutral' ?>">
                                            <?= $table['status'] === 'active' ? 'Ativa' : 'Inativa' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/classificacao/rows.php?id=<?= (int)$table['id'] ?>">
                                            Linhas
                                        </a>
                                        <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/classificacao/edit.php?id=<?= (int)$table['id'] ?>">
                                            Editar
                                        </a>
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

