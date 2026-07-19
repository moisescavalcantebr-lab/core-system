<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectAdmin();

$title = participantLabel(true);
$enabled = participantPublicRegistrationEnabled();
$publicRegistrationUrl = PROJECT_URL . '/cadastro-participante.php';
$participantSingular = participantLabel();
$participantPlural = participantLabel(true);

$counts = $pdo->query("
    SELECT
        SUM(status = 'active') AS active_total,
        SUM(status = 'inactive') AS inactive_total,
        SUM(status = 'pending') AS pending_total
    FROM participants
")->fetch(PDO::FETCH_ASSOC) ?: ['active_total' => 0, 'inactive_total' => 0, 'pending_total' => 0];

$stmt = $pdo->query("
    SELECT p.*, u.email, u.username, u.avatar, u.status AS user_status
    FROM participants p
    LEFT JOIN project_users u ON u.id = p.user_id
    ORDER BY p.status = 'active' DESC, p.name ASC
");
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars(participantLabel(true)) ?></h1>
            <p class="c-page-subtitle">Cadastro de <?= htmlspecialchars(strtolower($participantPlural)) ?> vinculado aos usuarios do projeto</p>
        </div>

        <form action="<?= participantAdminUrl('registration_toggle.php') ?>" method="POST" style="display:inline;">
            <?= csrf_field(); ?>
            <input type="hidden" name="enabled" value="<?= $enabled ? '0' : '1' ?>">
            <button type="submit" class="c-btn-secondary">
                <?= $enabled ? 'Bloquear Cadastro' : 'Liberar Cadastro' ?>
            </button>
        </form>

        <?php if ($enabled): ?>
            <a href="<?= htmlspecialchars($publicRegistrationUrl) ?>" target="_blank" class="c-btn-secondary">
                Link de Cadastro
            </a>
        <?php endif; ?>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-kpi-grid">
            <div class="c-kpi-card">
                <span>Ativos</span>
                <strong><?= (int)($counts['active_total'] ?? 0) ?></strong>
            </div>
            <div class="c-kpi-card">
                <span>Pendentes</span>
                <strong><?= (int)($counts['pending_total'] ?? 0) ?></strong>
            </div>
            <div class="c-kpi-card">
                <span>Inativos</span>
                <strong><?= (int)($counts['inactive_total'] ?? 0) ?></strong>
            </div>
        </div>

        <div class="c-card">
            <?php if (empty($participants)): ?>
                <p>Nenhum <?= htmlspecialchars(strtolower($participantSingular)) ?> cadastrado.</p>
            <?php else: ?>
                <div class="c-participant-grid">
                    <?php foreach ($participants as $participant): ?>
                        <?php $displayName = participantDisplayName($participant['nickname'] ?? null, (string)$participant['name']); ?>
                        <div class="c-participant-card">
                            <?= participantAvatar($participant['avatar'] ?? null, $displayName) ?>
                            <strong title="<?= htmlspecialchars((string)$participant['name']) ?>"><?= htmlspecialchars($displayName) ?></strong>
                            <span><?= htmlspecialchars((string)($participant['email'] ?? '-')) ?></span>
                            <span class="c-badge <?= participantStatusBadge((string)$participant['status']) ?>">
                                <?= htmlspecialchars(participantStatusLabel((string)$participant['status'])) ?>
                            </span>
                            <div class="c-participant-actions">
                                <a class="c-btn-secondary" href="<?= participantAdminUrl('edit.php?id=' . (int)$participant['id']) ?>">Editar</a>
                                <form action="<?= participantAdminUrl('toggle.php?id=' . (int)$participant['id']) ?>" method="POST">
                                    <?= csrf_field(); ?>
                                    <button type="submit" class="c-btn-secondary">
                                        <?= $participant['status'] === 'active' ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.c-participant-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: 12px;
}

.c-participant-card {
    border: 1px solid rgba(148, 163, 184, .28);
    padding: 14px;
    min-height: 170px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: flex-start;
}

.c-participant-avatar {
    width: 54px;
    height: 54px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(59, 130, 246, .95), rgba(34, 197, 94, .85));
    color: #fff;
    font-weight: 800;
    object-fit: cover;
}

.c-participant-card span:not(.c-badge) {
    color: var(--muted);
    font-size: .82rem;
    overflow-wrap: anywhere;
}

.c-participant-actions {
    margin-top: auto;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
