<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$projectId = (int)($_GET['id'] ?? 0);

/* =========================
PROJETO
========================= */

$stmt = $pdo->prepare("
SELECT p.*, b.name AS base_name, pl.name AS plan_name, COALESCE(pp.billing_cycle, pl.billing_cycle) AS billing_cycle
FROM projects p
LEFT JOIN bases b ON b.id = p.base_id
LEFT JOIN plans pl ON pl.id = p.plan_id
LEFT JOIN plan_prices pp ON pp.id = p.plan_price_id
WHERE p.id = :id
");

$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die('Projeto não encontrado.');
}

/* =========================
STATUS
========================= */

$projectInstalled = $project['status'] === 'active';
$deletionRequested = !empty($project['deletion_requested_at']);
$deletionReady = $deletionRequested
    && !empty($project['deletion_scheduled_at'])
    && strtotime((string)$project['deletion_scheduled_at']) <= time();
$deletionDaysLeft = null;

if ($deletionRequested && !$deletionReady && !empty($project['deletion_scheduled_at'])) {
    $secondsLeft = strtotime((string)$project['deletion_scheduled_at']) - time();
    $deletionDaysLeft = max(0, (int)ceil($secondsLeft / 86400));
}

/* =========================
CLIENTE
========================= */

$clientConnected = false;

$projectConfigPath = ROOT_PATH . '/' . ltrim($project['path'], '/') . '/app/config/database.php';

if (file_exists($projectConfigPath)) {

    try {
        $dbConf = require $projectConfigPath;

        $dsn = "mysql:host={$dbConf['host']};dbname={$dbConf['name']};charset={$dbConf['charset']}";
        $p = new PDO($dsn, $dbConf['user'], $dbConf['pass']);

        $count = $p->query("
            SELECT COUNT(*)
            FROM project_users
            WHERE role='ADMIN'
        ")->fetchColumn();

        if ($count > 0) {
            $clientConnected = true;
        }
    } catch (Throwable $e) {
    }
}

/* =========================
LOGS
========================= */

$logs = $pdo->prepare("
SELECT *
FROM project_logs
WHERE project_id=:id
ORDER BY id DESC
LIMIT 10
");

$logs->execute(['id' => $projectId]);
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);

/* =========================
STEPS
========================= */

$steps = [
    'created' => true,
    'cloned' => is_dir(ROOT_PATH . '/' . ltrim($project['path'], '/')),
    'database' => $projectInstalled,
    'client' => $clientConnected
];

$baseName = (string)($project['base_name'] ?? '-');
$planName = (string)($project['plan_name'] ?? '-');
$billingCycleLabel = $project['billing_cycle'] === 'free'
    ? 'Plano Gratuito'
    : ((string)($project['expires_at'] ?? '-'));
$statusLabel = ucfirst((string)$project['status']);
$billingLabel = ucfirst((string)$project['billing_status']);
$statusBadgeClass = $project['status'] === 'active' ? 'c-badge--success' : 'c-badge--warning';
$billingBadgeClass = $project['billing_status'] === 'active' ? 'c-badge--success' : 'c-badge--danger';
$walletBalance = coreWalletBalance($pdo, (int)$project['id']);

$title = 'Projeto';

ob_start();
?>

<div class="c-page c-project-view">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($project['name']) ?></h1>
            <p class="c-page-subtitle">Gerenciamento do projeto</p>
        </div>

        <div class="c-page-actions">

            <a class="c-btn-secondary" href="/web/admin/projects/index.php">
                Voltar
            </a>

            <a class="c-btn-secondary"
                href="/app/actions/projects/sync_preview.php?id=<?= $project['id'] ?>">
                Sincronizar
            </a>

            <a class="c-btn-secondary"
                href="/web/admin/projects/modules_sync_preview.php?id=<?= $project['id'] ?>">
                Sincronizar Módulos
            </a>

            <a class="c-btn-secondary"
                href="<?= htmlspecialchars($project['path']) ?>/web/"
                target="_blank">
                Abrir
            </a>

        </div>

    </div>

    <div class="c-page-content">

        <!-- INFO -->
        <?php if ($deletionRequested): ?>
            <div class="c-card c-project-alert-card">
                <div>
                    <h3>Exclusão agendada</h3>
                    <p>
                    O cliente solicitou a desativação da conta em
                        <strong><?= htmlspecialchars((string)$project['deletion_requested_at']) ?></strong>.
                    </p>
                    <p>
                        Exclusão definitiva:
                        <strong><?= htmlspecialchars((string)$project['deletion_scheduled_at']) ?></strong>
                        <?= $deletionReady ? '(liberada)' : '(faltam ' . $deletionDaysLeft . ' dia(s))' ?>.
                    </p>
                </div>

                <?php if ($deletionReady): ?>
                    <form method="post"
                          action="/app/actions/projects/delete_full.php"
                          onsubmit="return confirm('Excluir definitivamente arquivos, banco e registro do projeto?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$project['id'] ?>">
                        <button class="c-btn-danger">
                            Excluir definitivamente
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="c-card c-project-summary-card">

            <div class="c-project-summary-grid">

                <div class="c-project-summary-item">
                    <span>Slug</span>
                    <strong><?= htmlspecialchars((string)$project['slug']) ?></strong>
                </div>

                <div class="c-project-summary-item">
                    <span>Base</span>
                    <strong><?= htmlspecialchars($baseName) ?></strong>
                </div>

                <div class="c-project-summary-item">
                    <span>Plano</span>
                    <strong><?= htmlspecialchars($planName) ?></strong>
                </div>

                <div class="c-project-summary-item">
                    <span>Status</span>
                    <span class="c-badge <?= $statusBadgeClass ?>">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </div>

                <div class="c-project-summary-item">
                    <span>Billing</span>
                    <span class="c-badge <?= $billingBadgeClass ?>">
                        <?= htmlspecialchars($billingLabel) ?>
                    </span>
                </div>

                <div class="c-project-summary-item">
                    <span>Expiração</span>
                    <strong><?= htmlspecialchars($billingCycleLabel) ?></strong>
                </div>

                <div class="c-project-summary-item">
                    <span>Carteira</span>
                    <strong><?= htmlspecialchars(coreWalletMoney($walletBalance)) ?></strong>
                </div>

            </div>

        </div>

        <!-- GRID 3 COLUNAS -->
        <div class="c-project-main-grid">

            <!-- CLIENTE -->
            <div class="c-card c-project-panel">

                <h3>Cliente</h3>

                <?php if (!$projectInstalled): ?>

                    <p class="c-text-warning">Projeto não instalado.</p>

                <?php elseif ($clientConnected): ?>

                    <p><strong><?= htmlspecialchars($project['owner_name']) ?></strong></p>
                    <p><?= htmlspecialchars($project['owner_email']) ?></p>
                    <span class="c-badge c-badge--success">Conectado</span>

                <?php else: ?>

                    <p><strong><?= htmlspecialchars($project['owner_name']) ?></strong></p>
                    <p><?= htmlspecialchars($project['owner_email']) ?></p>

                    <form method="post" action="/app/actions/projects/send_access.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $project['id'] ?>">
                        <button class="c-btn-secondary">Enviar acesso</button>
                    </form>

                <?php endif; ?>

            </div>

            <!-- STATUS -->
            <div class="c-card c-project-panel">

                <h3>Status</h3>

                <div class="c-project-steps">

                    <div class="<?= $steps['created'] ? 'is-done' : '' ?>">
                        <span></span>
                        <strong>Criado</strong>
                    </div>
                    <div class="<?= $steps['cloned'] ? 'is-done' : '' ?>">
                        <span></span>
                        <strong>Clonado</strong>
                    </div>
                    <div class="<?= $steps['database'] ? 'is-done' : '' ?>">
                        <span></span>
                        <strong>Banco</strong>
                    </div>
                    <div class="<?= $steps['client'] ? 'is-done' : '' ?>">
                        <span></span>
                        <strong>Cliente</strong>
                    </div>

                </div>

            </div>

            <!-- AÇÕES -->
            <div class="c-card c-project-panel c-project-actions-card">

                <h3>Ações</h3>

                <?php if (!$deletionRequested): ?>
                    <form method="post" action="/app/actions/projects/toggle_status.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $project['id'] ?>">
                        <button class="c-btn-secondary c-btn-block">
                            <?= $project['status'] == 'active' ? 'Bloquear' : 'Ativar' ?>
                        </button>
                    </form>

                    <br>
                <?php endif; ?>

                <form method="post" action="/app/actions/projects/toggle_billing.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $project['id'] ?>">
                    <button class="c-btn-secondary c-btn-block">
                        Alterar Billing
                    </button>
                </form>

                <br>

                <a class="c-btn-secondary c-btn-block" href="/web/admin/projects/upgrade.php?id=<?= (int)$project['id'] ?>">
                    Alterar Plano
                </a>

                <a class="c-btn-danger c-btn-block" href="/web/admin/projects/force_delete.php?id=<?= (int)$project['id'] ?>">
                    Excluir projeto
                </a>

                <?php if ($deletionRequested): ?>
                    <span class="c-badge c-badge--warning">
                        Exclusão em andamento
                    </span>
                <?php else: ?>
                    <p class="c-text-muted">
                        Para clientes, a exclusão continua sendo solicitada nas configurações da conta. Este botão é apenas para limpeza administrativa.
                    </p>
                <?php endif; ?>

            </div>

        </div>

        <!-- LOGS -->
        <div class="c-card c-project-logs-card">

            <h3>Logs Recentes</h3>

            <?php if (empty($logs)): ?>

                <p>Nenhum log encontrado.</p>

            <?php else: ?>

                <table class="c-table">

                    <thead>
                        <tr>
                            <th>Ação</th>
                            <th>Mensagem</th>
                            <th>Nível</th>
                            <th>Data</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($logs as $log): ?>

                            <tr>

                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td><?= htmlspecialchars($log['message']) ?></td>

                                <td>
                                    <span class="c-badge
<?= $log['level'] == 'error' ? 'c-badge--danger' : ($log['level'] == 'warning' ? 'c-badge--warning' : 'c-badge--success') ?>">
                                        <?= $log['level'] ?>
                                    </span>
                                </td>

                                <td><?= $log['created_at'] ?></td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

    </div>

</div>

<style>
.c-project-view .c-page-content {
    display: grid;
    gap: 14px;
}

.c-project-summary-card,
.c-project-panel,
.c-project-logs-card,
.c-project-alert-card {
    border-color: color-mix(in srgb, var(--border-color) 82%, var(--text-secondary));
}

.c-project-summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
}

.c-project-summary-item {
    min-width: 0;
    padding: 4px 0;
}

.c-project-summary-item span,
.c-project-summary-item strong {
    display: block;
}

.c-project-summary-item > span:first-child {
    margin-bottom: 7px;
    color: var(--text-secondary);
    font-size: 11px;
}

.c-project-summary-item strong {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.c-project-main-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(280px, 0.95fr);
    gap: 14px;
}

.c-project-panel {
    min-height: 190px;
}

.c-project-panel h3,
.c-project-logs-card h3,
.c-project-alert-card h3 {
    margin-top: 0;
}

.c-project-steps {
    display: grid;
    gap: 12px;
}

.c-project-steps div {
    display: grid;
    grid-template-columns: 18px 1fr;
    align-items: center;
    gap: 9px;
    color: var(--text-secondary);
    font-weight: 700;
}

.c-project-steps span {
    width: 11px;
    height: 11px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
}

.c-project-steps .is-done {
    color: var(--text-primary);
}

.c-project-steps .is-done span {
    border-color: color-mix(in srgb, var(--success-color, #22c55e) 70%, var(--border-color));
    background: color-mix(in srgb, var(--success-color, #22c55e) 45%, transparent);
}

.c-project-actions-card {
    display: grid;
    align-content: start;
    gap: 10px;
}

.c-project-actions-card form,
.c-project-actions-card p {
    margin: 0;
}

.c-project-actions-card br {
    display: none;
}

.c-project-alert-card {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    border-color: color-mix(in srgb, var(--warning-color, #f59e0b) 45%, var(--border-color));
    background: color-mix(in srgb, var(--warning-color, #f59e0b) 10%, var(--bg-card));
}

.c-project-alert-card p {
    margin-bottom: 0;
}

@media (max-width: 1120px) {
    .c-project-summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .c-project-main-grid {
        grid-template-columns: 1fr;
    }

    .c-project-panel {
        min-height: 0;
    }
}

@media (max-width: 680px) {
    .c-project-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-project-alert-card {
        display: grid;
    }
}
</style>

<?php
$content = ob_get_clean();

/* =========================
SIDEBAR
========================= */

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Informações</h3>
<p><strong>Base:</strong> ' . htmlspecialchars($project['base_name']) . '</p>
<p><strong>Slug:</strong> ' . htmlspecialchars($project['slug']) . '</p>
<p><strong>Status:</strong> ' . htmlspecialchars($project['status']) . '</p>
<p><strong>Billing:</strong> ' . htmlspecialchars($project['billing_status']) . '</p>

</div>

<br>

<div class="c-card">

<h3>Avisos</h3>
<p>A instalação do banco agora acontece automaticamente na criação do projeto.</p>
<p>Use Sincronizar para estrutura da base e Sincronizar Módulos para recursos instalados na base.</p>

</div>
';

require APP_PATH . '/views/layout_admin.php';
