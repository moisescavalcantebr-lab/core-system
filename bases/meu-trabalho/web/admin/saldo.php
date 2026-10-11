<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../app/bootstrap/project_bootstrap.php';

requireProjectAuth();
requireProjectAdmin();

require_once APP_PATH . '/helpers/core_bridge.php';

$corePdo = projectCorePdo();
$coreProjectId = (int)($project['id'] ?? 0);
$balance = projectCoreWalletBalance($corePdo, $coreProjectId);
$settings = projectCoreSettings($corePdo, ['upgrade_pix_type', 'upgrade_pix_key', 'upgrade_pix_holder', 'upgrade_pix_notes']);
$requests = projectCoreWalletRequests($corePdo, $coreProjectId, 5);
$pendingRequests = projectCoreWalletRequests($corePdo, $coreProjectId, null);
$pendingTotal = 0.0;
$pendingCount = 0;

foreach ($pendingRequests as $request) {
    if (($request['status'] ?? '') === 'pending') {
        $pendingTotal += (float)$request['amount'];
        $pendingCount++;
    }
}

$pixType = trim((string)($settings['upgrade_pix_type'] ?? ''));
$pixKey = trim((string)($settings['upgrade_pix_key'] ?? ''));
$pixHolder = trim((string)($settings['upgrade_pix_holder'] ?? ''));
$pixNotes = trim((string)($settings['upgrade_pix_notes'] ?? ''));

$pixTypeLabel = [
    'phone' => 'Telefone',
    'email' => 'Email',
    'cpf' => 'CPF',
    'cnpj' => 'CNPJ',
    'random' => 'Chave aleatoria',
][$pixType] ?? 'Pix';

function walletStatusBadge(string $status): string
{
    $class = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
    $label = [
        'pending' => 'Pendente',
        'approved' => 'Aprovada',
        'rejected' => 'Rejeitada',
    ][$status] ?? $status;

    return '<span class="c-badge c-badge--' . $class . '">' . htmlspecialchars($label) . '</span>';
}

$title = 'Carteira';
ob_start();
?>

<div class="c-page wallet-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Carteira</h1>
            <p class="c-page-subtitle">Credito central para upgrades e servicos do projeto</p>
        </div>

        <div class="c-page-actions">
            <div class="wallet-balance-pill">
                <span>Credito disponivel</span>
                <strong><?= htmlspecialchars(projectCoreWalletMoney($balance)) ?></strong>
            </div>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="wallet-layout">
            <div class="wallet-left-column">
                <section class="wallet-panel wallet-pending-panel">
                    <span>Solicitacoes pendentes</span>
                    <strong><?= htmlspecialchars(projectCoreWalletMoney($pendingTotal)) ?></strong>
                    <p><?= $pendingCount > 0 ? $pendingCount . ' aguardando revisao do Core.' : 'Nenhuma solicitacao pendente.' ?></p>
                </section>

                <section class="wallet-panel wallet-pix-panel">
                    <h3>Dados do Pix</h3>

                    <?php if (!$corePdo): ?>
                        <p>Nao foi possivel conectar ao Core.</p>
                    <?php elseif ($pixKey === ''): ?>
                        <p>Pix ainda nao configurado no Core.</p>
                    <?php else: ?>
                        <dl class="wallet-pix-list">
                            <div>
                                <dt>Tipo de chave</dt>
                                <dd><?= htmlspecialchars($pixTypeLabel) ?></dd>
                            </div>
                            <div>
                                <dt>Chave Pix</dt>
                                <dd class="wallet-copy-line">
                                    <span data-pix-key><?= htmlspecialchars($pixKey) ?></span>
                                    <button class="c-btn-secondary wallet-copy-button" type="button" data-copy-pix>Copiar</button>
                                </dd>
                            </div>
                            <?php if ($pixHolder !== ''): ?>
                                <div>
                                    <dt>Recebedor</dt>
                                    <dd><?= htmlspecialchars($pixHolder) ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                </section>
            </div>

            <section class="wallet-panel wallet-request-panel">
                <h3>Adicionar credito</h3>
                <p class="wallet-instructions">
                    Faça o Pix usando os dados ao lado, informe o valor enviado e anexe o comprovante.
                    O credito fica disponivel depois da aprovacao no Core.
                </p>

                <?php if ($pixNotes !== ''): ?>
                    <p class="wallet-notes"><?= nl2br(htmlspecialchars($pixNotes)) ?></p>
                <?php endif; ?>

                <?php if (!$corePdo || $pixKey === ''): ?>
                    <p>Solicitacao indisponivel no momento.</p>
                <?php else: ?>
                    <form method="post" action="<?= PROJECT_URL ?>/admin/saldo_store.php" enctype="multipart/form-data" class="wallet-request-form">
                        <?= csrf_field() ?>

                        <div class="c-form-group">
                            <label>Valor</label>
                            <input class="c-input" name="amount" inputmode="decimal" placeholder="0,00" required>
                        </div>

                        <div class="c-form-group">
                            <label>Comprovante</label>
                            <input class="c-input" type="file" name="receipt" accept="image/*,.pdf" required>
                        </div>

                        <button class="c-btn-secondary">Solicitar credito</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="wallet-panel wallet-recent-panel">
                <h3>Solicitacoes recentes</h3>

                <?php if (empty($requests)): ?>
                    <p>Nenhuma solicitacao enviada.</p>
                <?php else: ?>
                    <div class="wallet-request-list">
                        <?php foreach ($requests as $request): ?>
                            <div class="wallet-request-row">
                                <strong><?= htmlspecialchars(projectCoreWalletMoney((float)$request['amount'])) ?></strong>
                                <?= walletStatusBadge((string)$request['status']) ?>
                                <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$request['created_at']))) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="wallet-panel-footer">
                    <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/saldo_historico.php">
                        Ver historico geral
                    </a>
                </div>
            </section>
        </div>
    </div>
</div>

<style>
.wallet-layout {
    display: grid;
    grid-template-columns: minmax(220px, .8fr) minmax(280px, 1fr) minmax(260px, .9fr);
    gap: 12px;
    align-items: start;
}

.wallet-left-column {
    display: grid;
    gap: 12px;
}

.wallet-panel {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
    min-width: 0;
}

.wallet-panel h3,
.wallet-panel p,
.wallet-pix-list,
.wallet-pix-list dd {
    margin: 0;
}

.wallet-panel h3 {
    margin-bottom: 12px;
    font-size: 14px;
}

.wallet-pending-panel span,
.wallet-pix-list dt,
.wallet-request-row span,
.wallet-instructions,
.wallet-notes,
.wallet-panel p {
    color: var(--text-secondary);
    font-size: 12px;
}

.wallet-pending-panel strong {
    display: block;
    margin-top: 6px;
    font-size: 24px;
}

.wallet-pending-panel p {
    margin-top: 10px;
}

.wallet-pix-list {
    display: grid;
    gap: 10px;
}

.wallet-pix-list dt {
    margin-bottom: 4px;
}

.wallet-pix-list dd {
    color: var(--text-primary);
    font-weight: 700;
    overflow-wrap: anywhere;
}

.wallet-copy-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
}

.wallet-copy-button {
    margin: 0;
}

.wallet-instructions {
    line-height: 1.45;
    margin-bottom: 14px !important;
}

.wallet-notes {
    padding: 10px;
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(148, 163, 184, .06);
    margin-bottom: 14px !important;
}

.wallet-request-form {
    display: grid;
    gap: 8px;
}

.wallet-request-form .c-input {
    margin-bottom: 0;
}

.wallet-request-list {
    display: grid;
    gap: 8px;
}

.wallet-request-row {
    display: grid;
    grid-template-columns: minmax(70px, .7fr) auto minmax(100px, 1fr);
    gap: 8px;
    align-items: center;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
    font-size: 12px;
}

.wallet-panel-footer {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}

.wallet-balance-pill {
    min-height: 34px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0 12px;
    border: 1px solid rgba(139, 92, 246, .45);
    background: rgba(139, 92, 246, .08);
    color: var(--text-primary);
}

.wallet-balance-pill span {
    color: var(--text-secondary);
    font-size: 11px;
}

.wallet-balance-pill strong {
    font-size: 13px;
}

@media (max-width: 1040px) {
    .wallet-layout {
        grid-template-columns: 1fr 1fr;
    }

    .wallet-recent-panel {
        grid-column: 1 / -1;
    }
}

@media (max-width: 720px) {
    .wallet-layout {
        grid-template-columns: 1fr;
    }

    .wallet-copy-line,
    .wallet-request-row {
        grid-template-columns: 1fr;
    }

    .wallet-panel-footer {
        justify-content: stretch;
    }

    .wallet-panel-footer .c-btn-secondary,
    .wallet-balance-pill {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.querySelector('[data-copy-pix]');
    const key = document.querySelector('[data-pix-key]');

    if (!button || !key) {
        return;
    }

    button.addEventListener('click', async function () {
        const value = key.textContent.trim();

        try {
            await navigator.clipboard.writeText(value);
            button.textContent = 'Copiado';
            setTimeout(function () {
                button.textContent = 'Copiar';
            }, 1400);
        } catch (error) {
            button.textContent = 'Selecione a chave';
            setTimeout(function () {
                button.textContent = 'Copiar';
            }, 1800);
        }
    });
});
</script>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
