<?php
if (!$entry && $records) {
    foreach (['employer', 'employee_name', 'registration', 'job_title', 'department', 'admission_date', 'base_salary'] as $field) {
        $entry[$field] = $records[0][$field];
    }
}
$entry += ['reference_month' => date('Y-m'), 'receipt_type' => 'monthly', 'sequence_number' => 1, 'employee_name' => projectUser()['name'] ?? '', 'base_salary' => '0.00'];
$entry['reference_month'] = substr((string)$entry['reference_month'], 0, 7);
if (!$items) {
    $items = [['description' => '', 'item_type' => 'income', 'amount' => ''], ['description' => '', 'item_type' => 'deduction', 'amount' => '']];
}
?>
<form method="post" enctype="multipart/form-data" action="<?= holeriteEscape($url . '?view=edit' . ($id ? '&id=' . $id : '')) ?>" id="holerite-form">
    <?= csrf_field() ?><input type="hidden" name="operation" value="save">
    <section class="h-section">
        <h2><?= $id ? 'Editar holerite' : 'Novo holerite' ?></h2>
        <div class="h-form-grid">
            <label>Competencia<input class="c-input" type="month" name="reference_month" value="<?= holeriteEscape($entry['reference_month']) ?>" min="1900-01" max="2200-12" required></label>
            <label>Tipo<select class="c-input" name="receipt_type"><?php foreach (holeriteTypes() as $key => $label): ?><option value="<?= $key ?>" <?= $key === $entry['receipt_type'] ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
            <label>Sequencia<input class="c-input" type="number" name="sequence_number" min="1" max="99" value="<?= holeriteEscape($entry['sequence_number']) ?>" required></label>
            <label>Salario-base contratual<input class="c-input" name="base_salary" inputmode="decimal" maxlength="30" value="<?= holeriteEscape($entry['base_salary']) ?>" required></label>
            <label>Emissao<input class="c-input" type="date" name="issued_date" value="<?= holeriteEscape($entry['issued_date'] ?? '') ?>"></label>
            <label>Pagamento informado<input class="c-input" type="date" name="payment_date" value="<?= holeriteEscape($entry['payment_date'] ?? '') ?>"></label>
            <label class="h-check"><input type="checkbox" name="received" value="1" <?= !empty($entry['received']) ? 'checked' : '' ?>> Recebimento confirmado</label>
        </div>
    </section>
    <section class="h-section">
        <h2>Vinculo profissional</h2>
        <div class="h-form-grid">
            <?php foreach (['employer' => ['Empresa', 150], 'employee_name' => ['Nome', 150], 'registration' => ['Matricula', 60], 'job_title' => ['Cargo', 150], 'department' => ['Departamento', 150]] as $field => [$label, $limit]): ?>
                <label><?= $label ?><input class="c-input" name="<?= $field ?>" maxlength="<?= $limit ?>" value="<?= holeriteEscape($entry[$field] ?? '') ?>" <?= in_array($field, ['employer', 'employee_name'], true) ? 'required' : '' ?>></label>
            <?php endforeach; ?>
            <label>Admissao<input class="c-input" type="date" name="admission_date" value="<?= holeriteEscape($entry['admission_date'] ?? '') ?>"></label>
        </div>
    </section>
    <section class="h-section">
        <div class="h-section-head"><h2>Proventos e descontos</h2><button class="c-btn-secondary" type="button" id="holerite-add">Adicionar item</button></div>
        <div id="holerite-items">
        <?php foreach ($items as $i => $item): ?>
            <div class="h-item" data-item>
                <label>Codigo<input class="c-input" name="items[<?= $i ?>][event_code]" maxlength="30" value="<?= holeriteEscape($item['event_code'] ?? '') ?>"></label>
                <label class="h-item-description">Descricao<input class="c-input" name="items[<?= $i ?>][description]" maxlength="150" value="<?= holeriteEscape($item['description'] ?? '') ?>"></label>
                <label>Referencia<input class="c-input" name="items[<?= $i ?>][reference_value]" maxlength="40" value="<?= holeriteEscape($item['reference_value'] ?? '') ?>"></label>
                <label>Tipo<select class="c-input" name="items[<?= $i ?>][item_type]" data-kind><option value="income" <?= ($item['item_type'] ?? '') === 'income' ? 'selected' : '' ?>>Provento</option><option value="deduction" <?= ($item['item_type'] ?? '') === 'deduction' ? 'selected' : '' ?>>Desconto</option></select></label>
                <label>Valor<input class="c-input" name="items[<?= $i ?>][amount]" inputmode="decimal" maxlength="30" value="<?= holeriteEscape($item['amount'] ?? '') ?>" data-amount></label>
                <button class="c-btn-secondary" type="button" data-remove>Remover</button>
            </div>
        <?php endforeach; ?>
        </div>
        <p class="h-error" id="holerite-item-error" role="alert" hidden></p>
        <div class="h-metrics h-totals" aria-live="polite"><div><span>Proventos</span><strong id="holerite-gross">-</strong></div><div><span>Descontos</span><strong id="holerite-deductions">-</strong></div><div><span>Liquido</span><strong id="holerite-net">-</strong></div></div>
    </section>
    <section class="h-section">
        <h2>Bases informadas</h2>
        <div class="h-form-grid"><?php foreach (['inss_base' => 'Base INSS', 'irrf_base' => 'Base IRRF', 'fgts_base' => 'Base FGTS', 'irrf_recomp_base' => 'Base IRRF recomposta'] as $field => $label): ?>
            <label><?= $label ?><input class="c-input" name="<?= $field ?>" inputmode="decimal" maxlength="30" value="<?= holeriteEscape($entry[$field] ?? '') ?>"></label>
        <?php endforeach; ?></div>
        <label class="h-notes">Observacoes<textarea class="c-input" name="notes" rows="3" maxlength="4000"><?= holeriteEscape($entry['notes'] ?? '') ?></textarea></label>
    </section>
    <section class="h-section">
        <h2>Documento</h2>
        <label>PDF do holerite (ate 2 MB)<input class="c-input" type="file" name="holerite_pdf" accept=".pdf,application/pdf"></label>
        <?php if (!empty($entry['attachment_name'])): ?>
            <p><a href="<?= holeriteEscape($url . '?pdf=' . $id) ?>"><?= holeriteEscape($entry['attachment_name']) ?></a></p>
            <label class="h-check"><input type="checkbox" name="remove_pdf" value="1"> Remover PDF anexado</label>
        <?php endif; ?>
    </section>
    <input type="hidden" name="form_complete" value="1">
    <div class="h-actions"><button class="c-btn-secondary" type="submit">Salvar holerite</button><a class="c-btn-secondary" href="<?= holeriteEscape($url) ?>">Cancelar</a></div>
</form>
