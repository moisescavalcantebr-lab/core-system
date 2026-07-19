<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/../financeiro/helpers.php';

requireProjectAuth();

$user = projectUser();

$stmt = $pdo->prepare("
    SELECT p.*, pp.name AS position_name
    FROM players p
    LEFT JOIN player_positions pp ON pp.id = p.position_id
    WHERE p.user_id = ?
    LIMIT 1
");
$stmt->execute([(int)$user['id']]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(403);
    exit('Seu usuario ainda nao esta vinculado a um jogador.');
}

$balance = financePlayerBalance($pdo, (int)$player['id']);
$pixKey = financeSetting($pdo, 'pix_key');
$pixKeyType = financeSetting($pdo, 'pix_key_type', 'random');
$pixReceiverName = financeSetting($pdo, 'pix_receiver_name');

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' AND source = 'wallet_deposit' THEN amount ELSE 0 END), 0) AS deposited_total,
        COALESCE(SUM(CASE WHEN source IN ('wallet_payment', 'match_fee') THEN amount ELSE 0 END), 0) AS used_total
    FROM finance_entries
    WHERE party_type = 'player'
      AND party_id = ?
      AND status = 'paid'
");
$stmt->execute([(int)$player['id']]);
$walletSummary = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['deposited_total' => 0, 'used_total' => 0];

$stmt = $pdo->prepare("
    SELECT *
    FROM finance_wallet_requests
    WHERE player_id = ?
    ORDER BY created_at DESC, id DESC
    LIMIT 10
");
$stmt->execute([(int)$player['id']]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT e.*, c.name AS category_name
    FROM finance_entries e
    LEFT JOIN finance_categories c ON c.id = e.category_id
    WHERE e.party_type = 'player'
      AND e.party_id = ?
      AND e.status = 'paid'
    ORDER BY COALESCE(e.paid_at, e.created_at) DESC, e.id DESC
    LIMIT 20
");
$stmt->execute([(int)$player['id']]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Meu Financeiro';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Meu Financeiro</h1>
            <p class="c-page-subtitle">Saldo e solicitações de depósito</p>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-dashboard-grid c-wallet-summary">
            <div class="c-dashboard-card c-card--info">
                <h4>Saldo atual</h4>
                <div class="c-metric"><?= financeMoney($balance) ?></div>
            </div>

            <div class="c-dashboard-card">
                <h4>Conferência</h4>
                <div class="c-wallet-mini-summary">
                    <div>
                        <span>Depositado</span>
                        <strong><?= financeMoney((float)$walletSummary['deposited_total']) ?></strong>
                    </div>
                    <div>
                        <span>Utilizado</span>
                        <strong><?= financeMoney((float)$walletSummary['used_total']) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <form action="<?= PROJECT_URL ?>/admin/financeiro/wallet_request_store.php" method="POST" enctype="multipart/form-data" class="c-wallet-form">
            <?= csrf_field(); ?>

            <div class="c-card">
                <h3>Adicionar saldo</h3>

                <?php if ($pixKey !== ''): ?>
                    <div class="c-wallet-pix">
                        <h3>Pix</h3>
                        <?php if ($pixReceiverName !== ''): ?>
                            <p><strong>Recebedor:</strong> <?= htmlspecialchars($pixReceiverName) ?></p>
                        <?php endif; ?>
                        <p><strong>Tipo:</strong> <?= htmlspecialchars(match ($pixKeyType) {
                            'email' => 'E-mail',
                            'phone' => 'Telefone',
                            'document' => 'CPF/CNPJ',
                            default => 'Aleatória',
                        }) ?></p>
                        <div class="c-wallet-pix-key">
                            <p><strong>Chave:</strong> <span data-pix-key><?= htmlspecialchars($pixKey) ?></span></p>
                            <button type="button" class="c-btn-secondary c-wallet-copy-btn" data-copy-pix="<?= htmlspecialchars($pixKey, ENT_QUOTES, 'UTF-8') ?>">
                                Copiar chave
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="c-wallet-pix">
                        <p>Chave Pix ainda não configurada pelo administrador.</p>
                    </div>
                <?php endif; ?>

            </div>

            <div class="c-card">
                <h3>Enviar comprovante</h3>

                <div class="c-form-group">
                    <label>Valor</label>
                    <input type="number" name="amount" class="c-input" min="0.01" step="0.01" required>
                </div>

                <div class="c-form-group">
                    <label>Comprovante</label>
                    <input type="file" name="receipt" class="c-input" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                </div>

                <button class="c-btn-secondary">
                    Enviar solicitação
                </button>
            </div>
        </form>

        <style>
        .c-wallet-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 14px;
        }

        .c-wallet-summary {
            grid-template-columns: minmax(180px, 280px) minmax(220px, 360px);
        }

        .c-wallet-mini-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .c-wallet-mini-summary span {
            display: block;
            color: var(--text-secondary);
            font-size: 11px;
            margin-bottom: 5px;
        }

        .c-wallet-mini-summary strong {
            display: block;
            font-size: 16px;
        }

        @media (max-width: 640px) {
            .c-wallet-summary {
                grid-template-columns: 1fr 1fr;
            }
        }

        .c-wallet-form .c-card {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin: 0;
        }

        .c-wallet-pix {
            border: 1px solid rgba(148, 163, 184, .24);
            background: rgba(15, 23, 42, .34);
            padding: 12px;
        }

        .c-wallet-pix h3 {
            margin-top: 0;
        }

        .c-wallet-pix p {
            margin: 8px 0 0;
        }

        .c-wallet-pix-key {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
            gap: 10px;
        }

        .c-wallet-pix-key span {
            overflow-wrap: anywhere;
        }

        .c-wallet-copy-btn {
            white-space: nowrap;
        }

        .c-wallet-form button {
            align-self: flex-start;
            margin-top: auto;
        }

        .c-wallet-form .c-wallet-copy-btn {
            margin-top: 8px;
        }

        @media (max-width: 760px) {
            .c-wallet-form {
                grid-template-columns: 1fr;
            }

            .c-wallet-pix-key {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
        }
        </style>

        <script>
        document.querySelectorAll('[data-copy-pix]').forEach((button) => {
            button.addEventListener('click', async () => {
                const key = button.getAttribute('data-copy-pix') || '';
                const originalText = button.textContent;

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(key);
                    } else {
                        const input = document.createElement('input');
                        input.value = key;
                        input.style.position = 'fixed';
                        input.style.opacity = '0';
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        input.remove();
                    }

                    button.textContent = 'Chave copiada';
                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 1800);
                } catch (error) {
                    button.textContent = 'Não foi possível copiar';
                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 2200);
                }
            });
        });
        </script>

        <div class="c-card">
            <h3>Solicitações</h3>

            <?php if (empty($requests)): ?>
                <p>Nenhuma solicitação enviada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Enviado em</th>
                                <th>Motivo</th>
                                <th>Comprovante</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?= financeMoney((float)$request['amount']) ?></td>
                                    <td>
                                        <span class="c-badge <?= financeWalletStatusBadge($request['status']) ?>">
                                            <?= financeWalletStatusLabel($request['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars((string)$request['created_at']) ?></td>
                                    <td><?= htmlspecialchars($request['notes'] ?? '-') ?></td>
                                    <td>
                                        <?php if (!empty($request['receipt_path'])): ?>
                                            <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/<?= htmlspecialchars($request['receipt_path']) ?>" target="_blank">
                                                Ver
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Histórico</h3>

            <?php if (empty($entries)): ?>
                <p>Nenhum movimento aprovado.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Categoria</th>
                                <th>Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td><?= htmlspecialchars($entry['title']) ?></td>
                                    <td><?= financeEntryTypeLabel($entry) ?></td>
                                    <td><?= htmlspecialchars($entry['category_name'] ?? '-') ?></td>
                                    <td><?= financeMoney((float)$entry['amount']) ?></td>
                                    <td><?= htmlspecialchars($entry['paid_at'] ?? $entry['created_at'] ?? '-') ?></td>
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
