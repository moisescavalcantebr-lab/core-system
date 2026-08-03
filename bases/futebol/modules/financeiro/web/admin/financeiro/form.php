<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($title) ?></h1>
            <p class="c-page-subtitle">Dados do lancamento financeiro</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/financeiro/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form action="<?= htmlspecialchars($formAction) ?>" method="POST" enctype="multipart/form-data" class="c-card finance-entry-form">

            <?= csrf_field(); ?>

            <div class="c-form-grid">
                <div class="c-form-group" id="financeCategoryGroup">
                    <label>Categoria</label>
                    <select name="category_id" class="c-input" id="financeCategory">
                        <option value="" data-type="both" data-model="simple">Sem categoria</option>
                        <?php foreach ($categories as $category): ?>
                            <option
                                value="<?= (int)$category['id'] ?>"
                                data-type="<?= htmlspecialchars($category['type']) ?>"
                                data-model="<?= htmlspecialchars((string)($category['form_model'] ?? 'simple')) ?>"
                                <?= (string)($entry['category_id'] ?? '') === (string)$category['id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(financeCategoryLabel($category)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group" id="financeTypeGroup">
                    <label>Tipo</label>
                    <select name="type" class="c-input" id="financeType">
                        <?php
                        $usesParticipants = isset($usesParticipants) ? (bool)$usesParticipants : financeUsesParticipants($pdo);
                        $entryMode = ($entry['source'] ?? '') === 'balance_deposit' ? 'balance_deposit' : ($entry['type'] ?? 'income');
                        ?>
                        <option value="income" <?= $entryMode === 'income' ? 'selected' : '' ?>>Entrada</option>
                        <option value="expense" <?= ($entry['type'] ?? '') === 'expense' ? 'selected' : '' ?>>Saida</option>
                    </select>
                </div>
            </div>

            <?php if (empty($entry['id'])): ?>
                <div class="finance-model-hint" id="financeModelHint" style="display:none;">
                    <strong id="financeModelTitle">Lancamento simples</strong>
                    <span id="financeModelText">Use para entradas e saidas comuns.</span>
                </div>
            <?php endif; ?>

            <div class="c-form-group" id="financeTitleGroup">
                <label>Titulo</label>
                <input type="text" name="title" class="c-input" value="<?= htmlspecialchars((string)$entry['title']) ?>" required>
            </div>

            <?php if ($usesParticipants): ?>
                <div class="c-card" style="margin:16px 0;">
                    <h3 id="financePartyTitle">Parte relacionada</h3>

                    <div class="c-form-grid">
                        <div class="c-form-group" id="financePartyTypeGroup">
                            <label id="financePartyTypeLabel">Tipo</label>
                            <select name="party_type" class="c-input" id="financePartyType">
                                <option value="admin" <?= ($entry['party_type'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="supplier" <?= ($entry['party_type'] ?? '') === 'supplier' ? 'selected' : '' ?>>Fornecedor</option>
                                <option value="customer" <?= ($entry['party_type'] ?? '') === 'customer' ? 'selected' : '' ?>>Cliente</option>
                                <option value="member" <?= ($entry['party_type'] ?? '') === 'member' ? 'selected' : '' ?>>Membro</option>
                                <option value="user" <?= ($entry['party_type'] ?? '') === 'user' ? 'selected' : '' ?>><?= htmlspecialchars(financeUserLabel()) ?></option>
                                <option value="other" <?= empty($entry['party_type']) || ($entry['party_type'] ?? '') === 'other' ? 'selected' : '' ?>>Outro</option>
                            </select>
                        </div>

                        <div class="c-form-group" id="financeUserGroup">
                            <label><?= htmlspecialchars(financeUserLabel()) ?></label>
                            <select name="party_id" class="c-input">
                                <option value="">Selecione</option>
                                <?php foreach (($projectUsers ?? []) as $projectUser): ?>
                                    <option value="<?= (int)$projectUser['id'] ?>" <?= (string)($entry['party_id'] ?? '') === (string)$projectUser['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($projectUser['name'] ?: ($projectUser['username'] ?: $projectUser['email'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="c-form-group" id="financePartyNameGroup">
                            <label>Nome</label>
                            <input type="text" name="party_name" class="c-input" value="<?= htmlspecialchars((string)($entry['party_name'] ?? '')) ?>">
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="party_type" value="other">
            <?php endif; ?>

            <div class="c-form-group">
                <label>Descricao</label>
                <textarea name="description" class="c-input" rows="4"><?= htmlspecialchars((string)($entry['description'] ?? '')) ?></textarea>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Valor</label>
                    <input type="number" name="amount" class="c-input" min="0" step="0.01" value="<?= htmlspecialchars((string)$entry['amount']) ?>" required>
                </div>

                <div class="c-form-group" id="financeStatusGroup">
                    <label>Status</label>
                    <select name="status" class="c-input" id="financeStatus">
                        <option value="pending" <?= ($entry['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendente</option>
                        <option value="paid" <?= ($entry['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Pago</option>
                        <option value="canceled" <?= ($entry['status'] ?? '') === 'canceled' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Forma de pagamento</label>
                    <select name="payment_method" class="c-input">
                        <option value="">Nao informado</option>
                        <?php foreach (financePaymentMethodOptions() as $methodValue => $methodLabel): ?>
                            <option value="<?= htmlspecialchars($methodValue) ?>" <?= (string)($entry['payment_method'] ?? '') === $methodValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($methodLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Comprovante (opcional)</label>
                    <input type="file" name="receipt" class="c-input" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <?php if (!empty($entry['receipt_path'])): ?>
                        <small>
                            <a href="<?= PROJECT_URL ?>/<?= htmlspecialchars((string)$entry['receipt_path']) ?>" target="_blank" rel="noopener noreferrer">
                                Ver comprovante salvo
                            </a>
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($advancedCategories)): ?>
                <div class="c-form-group">
                    <label>Tags</label>
                    <input type="text" name="tags" class="c-input" value="<?= htmlspecialchars((string)($entry['tags'] ?? '')) ?>" placeholder="Ex.: Pizza, Delivery, BTC">
                    <small>Use virgula para separar detalhes livres do lancamento.</small>
                </div>
            <?php endif; ?>

            <div class="c-form-grid">
                <div class="c-form-group" id="financeDueDateGroup">
                    <label id="financeDueDateLabel">Vencimento</label>
                    <input type="date" name="due_date" class="c-input" value="<?= htmlspecialchars((string)($entry['due_date'] ?? '')) ?>">
                </div>

                <div class="c-form-group" id="financePaidAtGroup">
                    <label id="financePaidAtLabel">Data de pagamento</label>
                    <input type="date" name="paid_at" class="c-input" value="<?= htmlspecialchars((string)($entry['paid_at'] ?? '')) ?>">
                </div>
            </div>

            <?php if (empty($entry['id'])): ?>
                <div class="c-card finance-model-fields" id="financeInstallmentFields" style="margin:16px 0; display:none;">
                    <h3>Compra parcelada</h3>
                    <p class="c-muted">Informe o valor total e o primeiro vencimento. O sistema divide em parcelas mensais pendentes.</p>

                    <div class="c-form-grid">
                        <div class="c-form-group">
                            <label>Quantidade de parcelas</label>
                            <input type="number" name="installments_total" class="c-input" min="2" max="120" value="2">
                        </div>
                    </div>
                </div>

                <div class="c-card finance-model-fields" id="financeRecurringFields" style="margin:16px 0; display:none;">
                    <h3>Lancamento recorrente</h3>
                    <p class="c-muted">Informe o valor mensal e o primeiro vencimento. O sistema replica em lancamentos mensais pendentes.</p>

                    <div class="c-form-grid">
                        <div class="c-form-group">
                            <label>Quantidade de meses</label>
                            <input type="number" name="recurrence_count" class="c-input" min="2" max="120" value="12">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <button type="submit" class="c-btn-secondary">
                <?= htmlspecialchars($submitLabel) ?>
            </button>

        </form>

        <?php if (!empty($entry['id'])): ?>
            <form action="<?= PROJECT_URL ?>/admin/financeiro/delete.php?id=<?= (int)$entry['id'] ?>" method="POST" class="c-card" onsubmit="return confirm('Excluir este lancamento?');">
                <?= csrf_field(); ?>
                <button type="submit" class="c-btn-secondary">
                    Excluir Lancamento
                </button>
            </form>
        <?php endif; ?>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const usesParticipants = <?= $usesParticipants ? 'true' : 'false' ?>;
    const category = document.getElementById('financeCategory');
    const categoryGroup = document.getElementById('financeCategoryGroup');
    const typeGroup = document.getElementById('financeTypeGroup');
    const type = document.getElementById('financeType');
    const titleGroup = document.getElementById('financeTitleGroup');
    const titleInput = titleGroup ? titleGroup.querySelector('input') : null;
    const partyTitle = document.getElementById('financePartyTitle');
    const partyType = document.getElementById('financePartyType');
    const partyTypeGroup = document.getElementById('financePartyTypeGroup');
    const userGroup = document.getElementById('financeUserGroup');
    const partyNameGroup = document.getElementById('financePartyNameGroup');
    const statusSelect = document.getElementById('financeStatus');
    const dueDateGroup = document.getElementById('financeDueDateGroup');
    const dueDateLabel = document.getElementById('financeDueDateLabel');
    const paidAtGroup = document.getElementById('financePaidAtGroup');
    const paidAtLabel = document.getElementById('financePaidAtLabel');
    const installmentFields = document.getElementById('financeInstallmentFields');
    const recurringFields = document.getElementById('financeRecurringFields');
    const modelHint = document.getElementById('financeModelHint');
    const modelTitle = document.getElementById('financeModelTitle');
    const modelText = document.getElementById('financeModelText');

    if (!category || !typeGroup || !type) return;

    function syncTypeField() {
        const selected = category.options[category.selectedIndex];
        const categoryType = selected ? selected.dataset.type : 'both';

        if (categoryType === 'income' || categoryType === 'expense') {
            type.value = categoryType;
            typeGroup.style.display = 'none';
            return;
        }

        typeGroup.style.display = '';
    }

    function syncPartyFields() {
        const selectedType = type.value;
        const isBalanceDeposit = selectedType === 'balance_deposit';
        const selectedCategory = category.options[category.selectedIndex];
        const formModel = selectedCategory ? (selectedCategory.dataset.model || 'simple') : 'simple';
        const selectedStatus = statusSelect ? statusSelect.value : 'pending';
        const showPaidDate = isBalanceDeposit || selectedStatus === 'paid';

        if (titleGroup) {
            titleGroup.style.display = isBalanceDeposit ? 'none' : '';
        }

        if (titleInput) {
            titleInput.required = !isBalanceDeposit;
        }

        if (partyTitle) {
            partyTitle.textContent = isBalanceDeposit
                ? 'Saldo do projeto'
                : (selectedType === 'expense' ? 'Quem recebeu?' : 'Quem pagou?');
        }

        if (isBalanceDeposit && partyType) {
            partyType.value = 'other';
        }

        const partyValue = partyType ? partyType.value : 'other';

        if (categoryGroup) {
            categoryGroup.style.display = isBalanceDeposit ? 'none' : '';
        }

        if (partyTypeGroup) {
            partyTypeGroup.style.display = isBalanceDeposit ? 'none' : '';
        }

        if (userGroup) {
            userGroup.style.display = !isBalanceDeposit && usesParticipants && partyValue === 'user' ? '' : 'none';
        }

        if (partyNameGroup) {
            partyNameGroup.style.display = isBalanceDeposit || partyValue === 'user' ? 'none' : '';
        }

        if (statusSelect && isBalanceDeposit) {
            statusSelect.value = 'paid';
        }

        if (dueDateGroup) {
            dueDateGroup.style.display = isBalanceDeposit ? 'none' : '';
        }

        if (paidAtGroup) {
            paidAtGroup.style.display = showPaidDate ? '' : 'none';
        }

        if (dueDateLabel) {
            dueDateLabel.textContent = selectedType === 'income'
                ? 'Data prevista para recebimento'
                : 'Data de vencimento';
        }

        if (paidAtLabel) {
            paidAtLabel.textContent = isBalanceDeposit
                ? 'Data'
                : (selectedType === 'income' ? 'Data do recebimento' : 'Data do pagamento');
        }

        if (dueDateLabel && !isBalanceDeposit) {
            if (formModel === 'installment' || formModel === 'recurring') {
                dueDateLabel.textContent = 'Primeiro vencimento';
            }
        }

        if (modelHint && modelTitle && modelText) {
            modelHint.style.display = isBalanceDeposit ? 'none' : '';

            if (formModel === 'installment') {
                modelTitle.textContent = 'Modelo parcelado';
                modelText.textContent = 'Ideal para compras, financiamentos e parcelamentos. O valor total sera dividido em lancamentos mensais.';
            } else if (formModel === 'recurring') {
                modelTitle.textContent = 'Modelo recorrente';
                modelText.textContent = 'Ideal para aluguel, assinaturas e contas fixas. O valor informado sera repetido mensalmente.';
            } else {
                modelTitle.textContent = 'Modelo simples';
                modelText.textContent = 'Use para entradas e saidas comuns em um unico lancamento.';
            }
        }

        if (installmentFields) {
            installmentFields.style.display = !isBalanceDeposit && formModel === 'installment' ? '' : 'none';
        }

        if (recurringFields) {
            recurringFields.style.display = !isBalanceDeposit && formModel === 'recurring' ? '' : 'none';
        }
    }

    category.addEventListener('change', () => {
        syncTypeField();
        syncPartyFields();
    });

    type.addEventListener('change', syncPartyFields);

    if (statusSelect) {
        statusSelect.addEventListener('change', syncPartyFields);
    }

    if (partyType) {
        partyType.addEventListener('change', syncPartyFields);
    }

    syncTypeField();
    syncPartyFields();
});
</script>

<style>
.finance-entry-form,
.finance-entry-form * {
    box-sizing: border-box;
    min-width: 0;
}

.finance-entry-form .c-input {
    width: 100%;
    max-width: 100%;
}

.finance-entry-form input[type="date"],
.finance-entry-form input[type="file"] {
    appearance: none;
    -webkit-appearance: none;
}

.finance-entry-form input[type="file"].c-input {
    overflow: hidden;
}

.finance-model-hint {
    margin: -4px 0 14px;
    padding: 10px 12px;
    border: 1px solid color-mix(in srgb, var(--primary-color) 22%, var(--border-color));
    background: color-mix(in srgb, var(--primary-color) 8%, transparent);
    color: var(--text-secondary);
    border-radius: 6px;
    display: flex;
    gap: 8px;
    align-items: baseline;
    flex-wrap: wrap;
}

.finance-model-hint strong {
    color: var(--text-primary);
}

@media (max-width: 700px) {
    .finance-entry-form {
        padding: 14px;
    }

    .finance-entry-form .c-card {
        padding: 12px;
    }
}
</style>

