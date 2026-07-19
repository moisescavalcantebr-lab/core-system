<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);
creditCardEnsureSchema($pdo);

$title = 'Cartões';
$planEnabled = creditCardPlanEnabled();

$cards = $pdo->query("
    SELECT c.*,
        COALESCE(SUM(CASE WHEN i.status IN ('open','closed') THEN i.total_amount ELSE 0 END), 0) AS open_total
    FROM finance_credit_cards c
    LEFT JOIN finance_credit_card_invoices i ON i.card_id = c.id
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("
    SELECT id, name
    FROM finance_categories
    WHERE status = 'active'
      AND parent_id IS NULL
    ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$invoices = $pdo->query("
    SELECT i.*, c.name AS card_name
    FROM finance_credit_card_invoices i
    INNER JOIN finance_credit_cards c ON c.id = i.card_id
    ORDER BY i.due_date ASC, i.id DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$purchases = $pdo->query("
    SELECT p.*, c.name AS card_name, cat.name AS category_name
    FROM finance_credit_card_purchases p
    INNER JOIN finance_credit_cards c ON c.id = p.card_id
    LEFT JOIN finance_categories cat ON cat.id = p.category_id
    ORDER BY p.purchase_date DESC, p.id DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN i.status IN ('open','closed') THEN i.total_amount ELSE 0 END), 0) AS open_total,
        COALESCE(SUM(CASE WHEN i.status = 'launched' THEN i.total_amount ELSE 0 END), 0) AS launched_total,
        COUNT(CASE WHEN i.status IN ('open','closed') THEN 1 END) AS open_invoices
    FROM finance_credit_card_invoices i
")->fetch(PDO::FETCH_ASSOC) ?: [];

$nextInvoice = $pdo->query("
    SELECT i.*, c.name AS card_name
    FROM finance_credit_card_invoices i
    INNER JOIN finance_credit_cards c ON c.id = i.card_id
    WHERE i.status IN ('open','closed')
    ORDER BY i.due_date ASC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC) ?: null;

$cardComparison = $pdo->query("
    SELECT c.name, COALESCE(SUM(i.total_amount), 0) AS amount
    FROM finance_credit_cards c
    LEFT JOIN finance_credit_card_invoices i ON i.card_id = c.id AND i.status IN ('open','closed')
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY amount DESC, c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Cartões</h1>
            <p class="c-page-subtitle">Compras, parcelas e faturas antes do lançamento no financeiro</p>
        </div>
        <div class="credit-page-actions">
            <a href="#cartoes-registrados" class="c-btn-secondary">Cartões</a>
            <a href="#compras-recentes" class="c-btn-secondary">Compras</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <?php if (!$planEnabled): ?>
            <div class="c-card credit-card-locked">
                <h3>Recurso do Plano Start</h3>
                <p>Cartões de crédito ficam disponíveis a partir do Plano Start.</p>
                <a href="<?= PROJECT_URL ?>/admin/upgrade/index.php" class="c-btn-secondary">Ver upgrade</a>
            </div>
        <?php else: ?>
            <div class="credit-summary">
                <div class="credit-metric">
                    <span>Faturas abertas</span>
                    <strong><?= creditCardMoney((float)($summary['open_total'] ?? 0)) ?></strong>
                    <small><?= (int)($summary['open_invoices'] ?? 0) ?> fatura(s)</small>
                </div>
                <div class="credit-metric">
                    <span>Próxima fatura</span>
                    <strong><?= $nextInvoice ? creditCardMoney((float)$nextInvoice['total_amount']) : '-' ?></strong>
                    <small><?= $nextInvoice ? htmlspecialchars((string)$nextInvoice['card_name']) . ' · ' . date('d/m/Y', strtotime((string)$nextInvoice['due_date'])) : 'Nenhuma fatura aberta' ?></small>
                </div>
                <div class="credit-metric">
                    <span>Lançado no financeiro</span>
                    <strong><?= creditCardMoney((float)($summary['launched_total'] ?? 0)) ?></strong>
                    <small>Faturas já enviadas ao caixa</small>
                </div>
            </div>

            <div class="credit-workbench">
                <div class="credit-workbench-menu">
                    <h3>Ações</h3>
                    <button type="button" class="credit-action-option is-active" data-credit-form="card">
                        <strong>Novo cartão</strong>
                        <span>Cadastre limite, fechamento e vencimento.</span>
                    </button>
                    <button type="button" class="credit-action-option" data-credit-form="purchase">
                        <strong>Nova compra</strong>
                        <span>Registre compras e parcelas no cartão.</span>
                    </button>
                </div>

                <div class="credit-workbench-panel">
                    <form method="post" action="<?= PROJECT_URL ?>/admin/cartao_credito/card_store.php" class="credit-dynamic-form is-active" data-credit-panel="card">
                        <?= csrf_field() ?>
                        <h3>Novo cartão</h3>
                        <div class="credit-form-grid">
                            <div class="c-form-group">
                                <label>Nome</label>
                                <input class="c-input" name="name" required placeholder="Nubank, Itaú...">
                            </div>
                            <div class="c-form-group">
                                <label>Bandeira</label>
                                <input class="c-input" name="brand" placeholder="Visa, Master...">
                            </div>
                            <div class="c-form-group">
                                <label>Final</label>
                                <input class="c-input" name="last_digits" maxlength="4" placeholder="1234">
                            </div>
                            <div class="c-form-group">
                                <label>Limite</label>
                                <input class="c-input" name="limit_amount" placeholder="0,00">
                            </div>
                            <div class="c-form-group">
                                <label>Fechamento</label>
                                <input class="c-input" name="closing_day" type="number" min="1" max="28" value="1">
                            </div>
                            <div class="c-form-group">
                                <label>Vencimento</label>
                                <input class="c-input" name="due_day" type="number" min="1" max="28" value="10">
                            </div>
                        </div>
                        <button class="c-btn-secondary">Cadastrar cartão</button>
                    </form>

                    <form method="post" action="<?= PROJECT_URL ?>/admin/cartao_credito/purchase_store.php" class="credit-dynamic-form" data-credit-panel="purchase">
                        <?= csrf_field() ?>
                        <h3>Nova compra</h3>
                        <div class="credit-form-grid">
                            <div class="c-form-group">
                                <label>Cartão</label>
                                <select class="c-input" name="card_id" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($cards as $card): ?>
                                        <option value="<?= (int)$card['id'] ?>"><?= htmlspecialchars((string)$card['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="c-form-group">
                                <label>Descrição</label>
                                <input class="c-input" name="title" required placeholder="Mercado, combustível...">
                            </div>
                            <div class="c-form-group">
                                <label>Loja</label>
                                <input class="c-input" name="merchant">
                            </div>
                            <div class="c-form-group">
                                <label>Categoria</label>
                                <select class="c-input" name="category_id">
                                    <option value="">Sem categoria</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars((string)$category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="c-form-group">
                                <label>Data</label>
                                <input class="c-input" name="purchase_date" type="date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="c-form-group">
                                <label>Valor total</label>
                                <input class="c-input" name="amount_total" required placeholder="0,00">
                            </div>
                            <div class="c-form-group">
                                <label>Parcelas</label>
                                <input class="c-input" name="installments_total" type="number" min="1" max="60" value="1">
                            </div>
                        </div>
                        <button class="c-btn-secondary">Registrar compra</button>
                    </form>
                </div>
            </div>

            <div class="credit-layout credit-layout--bottom" id="cartoes-registrados">
                <div class="c-card">
                    <h3>Cartões registrados</h3>
                    <div class="credit-card-list">
                        <?php foreach ($cards as $card): ?>
                            <div class="credit-card-item">
                                <div>
                                    <strong><?= htmlspecialchars((string)$card['name']) ?></strong>
                                    <span><?= htmlspecialchars(trim((string)($card['brand'] ?? '') . ' ' . (!empty($card['last_digits']) ? 'final ' . $card['last_digits'] : ''))) ?: 'Cartão ativo' ?></span>
                                </div>
                                <div>
                                    <small>Limite</small>
                                    <strong><?= creditCardMoney((float)$card['limit_amount']) ?></strong>
                                </div>
                                <div>
                                    <small>Fatura aberta</small>
                                    <strong><?= creditCardMoney((float)$card['open_total']) ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($cards)): ?>
                            <p>Nenhum cartão cadastrado.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="c-card">
                    <h3>Comparativo por cartão</h3>
                    <div class="credit-chart-list">
                        <?php
                            $maxAmount = max(array_map(fn($row) => (float)($row['amount'] ?? 0), $cardComparison ?: [['amount' => 0]]));
                        ?>
                        <?php foreach ($cardComparison as $row): ?>
                            <?php $percent = $maxAmount > 0 ? min(100, ((float)$row['amount'] / $maxAmount) * 100) : 0; ?>
                            <div class="credit-bar">
                                <div>
                                    <strong><?= htmlspecialchars((string)$row['name']) ?></strong>
                                    <span><?= creditCardMoney((float)$row['amount']) ?></span>
                                </div>
                                <i style="width:<?= $percent ?>%"></i>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($cardComparison)): ?>
                            <p>Nenhum cartão cadastrado.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="credit-layout credit-layout--bottom">
                <div class="c-card">
                    <div class="credit-section-title">
                        <h3>Últimas faturas</h3>
                        <a href="#cartoes-registrados" class="c-btn-secondary">Ver cartões</a>
                    </div>
                    <div class="c-table-wrap">
                        <table class="c-table">
                            <thead>
                                <tr>
                                    <th>Cartão</th>
                                    <th>Referência</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$invoice['card_name']) ?></td>
                                        <td><?= date('m/Y', strtotime((string)$invoice['reference_month'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime((string)$invoice['due_date'])) ?></td>
                                        <td><?= creditCardMoney((float)$invoice['total_amount']) ?></td>
                                        <td><span class="c-badge c-badge--neutral"><?= htmlspecialchars((string)$invoice['status']) ?></span></td>
                                        <td class="credit-actions">
                                            <?php if ($invoice['status'] === 'open'): ?>
                                                <form method="post" action="<?= PROJECT_URL ?>/admin/cartao_credito/invoice_close.php?id=<?= (int)$invoice['id'] ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="c-btn-secondary">Fechar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (in_array($invoice['status'], ['open', 'closed'], true)): ?>
                                                <form method="post" action="<?= PROJECT_URL ?>/admin/cartao_credito/invoice_launch.php?id=<?= (int)$invoice['id'] ?>">
                                                    <?= csrf_field() ?>
                                                    <button class="c-btn-secondary">Lançar</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($invoices)): ?>
                                    <tr><td colspan="6">Nenhuma fatura gerada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="c-card" id="compras-recentes">
                <div class="credit-section-title">
                    <h3>Compras recentes</h3>
                    <button type="button" class="c-btn-secondary" data-credit-open-form="purchase">Nova compra</button>
                </div>
                <div class="c-table-wrap">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Cartão</th>
                                <th>Compra</th>
                                <th>Categoria</th>
                                <th>Parcelas</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime((string)$purchase['purchase_date'])) ?></td>
                                    <td><?= htmlspecialchars((string)$purchase['card_name']) ?></td>
                                    <td><?= htmlspecialchars((string)$purchase['title']) ?></td>
                                    <td><?= htmlspecialchars((string)($purchase['category_name'] ?? '-')) ?></td>
                                    <td><?= (int)$purchase['installments_total'] ?>x</td>
                                    <td><?= creditCardMoney((float)$purchase['amount_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($purchases)): ?>
                                <tr><td colspan="6">Nenhuma compra registrada.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const options = Array.from(document.querySelectorAll('[data-credit-form]'));
    const panels = Array.from(document.querySelectorAll('[data-credit-panel]'));

    function openPanel(name) {
        options.forEach((option) => {
            option.classList.toggle('is-active', option.dataset.creditForm === name);
        });
        panels.forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.creditPanel === name);
        });
    }

    options.forEach((option) => {
        option.addEventListener('click', () => openPanel(option.dataset.creditForm || 'card'));
    });

    document.querySelectorAll('[data-credit-open-form]').forEach((button) => {
        button.addEventListener('click', () => {
            openPanel(button.dataset.creditOpenForm || 'card');
            document.querySelector('.credit-workbench')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
});
</script>

<style>
.credit-page-actions,
.credit-section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.credit-summary,
.credit-layout {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.credit-layout {
    grid-template-columns: minmax(280px, .75fr) minmax(360px, 1.25fr);
}

.credit-workbench {
    display: grid;
    grid-template-columns: minmax(220px, .45fr) minmax(420px, 1fr);
    gap: 12px;
}

.credit-workbench-menu,
.credit-workbench-panel {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.credit-workbench-menu {
    align-self: start;
}

.credit-action-option {
    width: 100%;
    border: 1px solid var(--border-color);
    background: transparent;
    color: var(--text-secondary);
    border-radius: 7px;
    padding: 12px;
    text-align: left;
    cursor: pointer;
    display: grid;
    gap: 4px;
    margin-top: 10px;
}

.credit-action-option strong {
    color: var(--text-primary);
}

.credit-action-option.is-active {
    border-color: color-mix(in srgb, var(--primary-color) 55%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 12%, transparent);
}

.credit-dynamic-form {
    display: none;
}

.credit-dynamic-form.is-active {
    display: block;
}

.credit-layout--bottom {
    grid-template-columns: minmax(420px, 1.35fr) minmax(280px, .65fr);
}

.credit-metric {
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    padding: 14px;
}

.credit-metric span,
.credit-metric small {
    display: block;
    color: var(--text-secondary);
}

.credit-metric strong {
    display: block;
    margin: 6px 0;
    font-size: 22px;
}

.credit-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.credit-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.credit-actions form {
    margin: 0;
}

.credit-card-list {
    display: grid;
    gap: 10px;
}

.credit-card-item {
    display: grid;
    grid-template-columns: minmax(160px, 1fr) minmax(110px, .45fr) minmax(120px, .5fr);
    gap: 10px;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding: 10px 0;
}

.credit-card-item:last-child {
    border-bottom: 0;
}

.credit-card-item span,
.credit-card-item small {
    display: block;
    color: var(--text-secondary);
}

.credit-chart-list {
    display: grid;
    gap: 12px;
}

.credit-bar {
    display: grid;
    gap: 6px;
}

.credit-bar div {
    display: flex;
    justify-content: space-between;
    gap: 10px;
}

.credit-bar span {
    color: var(--text-secondary);
}

.credit-bar i {
    display: block;
    height: 8px;
    background: linear-gradient(90deg, #38bdf8, #8b5cf6);
}

.credit-card-locked {
    border-color: rgba(139, 92, 246, .35);
    background: rgba(139, 92, 246, .08);
}

@media (max-width: 900px) {
    .credit-summary,
    .credit-layout,
    .credit-layout--bottom,
    .credit-workbench,
    .credit-form-grid {
        grid-template-columns: 1fr;
    }

    .credit-card-item {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
$content = ob_get_clean();
$rightSidebarEnabled = false;

require APP_PATH . '/views/layout_admin.php';
