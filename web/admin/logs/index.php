<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$stmt = $pdo->query("
    SELECT l.*, p.name AS project_name
    FROM project_logs l
    LEFT JOIN projects p ON p.id = l.project_id
    ORDER BY l.id DESC
    LIMIT 100
");

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Logs';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Logs</h1>
            <p class="c-page-subtitle">Atividades recentes do sistema</p>
        </div>

    </div>

    <div class="c-page-content">

        <div class="c-card">
            <p>Registro de eventos e ações do sistema.</p>
        </div>

        <?php if (empty($logs)): ?>

            <div class="c-card">
                Nenhum log encontrado.
            </div>

        <?php else: ?>

            <div class="c-table-wrapper">

                <table class="c-table">

    <thead>
        <tr>
            <th>Ação</th>
            <th>Projeto</th>
            <th>Nível</th>
            <th>Mensagem</th>
            <th>Data</th>
        </tr>
    </thead>

    <tbody>

    <?php foreach ($logs as $log): ?>

        <?php
        $levelClass = match($log['level']) {
            'error'   => 'c-badge--danger',
            'warning' => 'c-badge--warning',
            default   => 'c-badge--success'
        };
        ?>

        <tr>

            <td class="c-log-action">
                <?= htmlspecialchars($log['action']) ?>
            </td>

            <td class="c-log-project">
                <?= htmlspecialchars($log['project_name'] ?? 'N/A') ?>
            </td>

            <td>
                <span class="c-badge <?= $levelClass ?>">
                    <?= htmlspecialchars($log['level']) ?>
                </span>
            </td>

            <td class="c-log-message">
                <?= htmlspecialchars($log['message']) ?>
            </td>

            <td class="c-log-date">
                <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php
$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Informações</h3>

<p>
Logs registram ações do sistema como erros,
execuções e eventos importantes.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';