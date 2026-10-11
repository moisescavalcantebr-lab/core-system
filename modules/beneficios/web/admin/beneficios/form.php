<?php
if (!$entry) {
    $entry = ['employer' => $records[0]['employer'] ?? $links[0]['employer'] ?? '', 'registration' => $records[0]['registration'] ?? $links[0]['registration'] ?? ''];
}
$entry += ['reference_month' => date('Y-m'), 'benefit_type' => 'transport'];
?>
<form method="post" enctype="multipart/form-data" action="<?= holeriteEscape($url . '?view=edit' . ($id ? '&id=' . $id : '')) ?>" id="benefit-form">
    <?= csrf_field() ?><input type="hidden" name="operation" value="save">
    <section class="h-section"><h2><?= $id ? 'Editar beneficio' : 'Novo beneficio' ?></h2><div class="h-form-grid">
        <label>Empresa<input class="c-input" name="employer" list="benefit-employers" maxlength="150" value="<?= holeriteEscape($entry['employer'] ?? '') ?>" required></label>
        <datalist id="benefit-employers"><?php foreach ($companies as $company): ?><option value="<?= holeriteEscape($company) ?>"><?php endforeach; ?></datalist>
        <label>Matricula<input class="c-input" name="registration" maxlength="60" value="<?= holeriteEscape($entry['registration'] ?? '') ?>"></label>
        <label>Competencia<input class="c-input" type="month" name="reference_month" min="1900-01" max="2200-12" value="<?= holeriteEscape(substr($entry['reference_month'], 0, 7)) ?>" required></label>
        <label>Beneficio<select class="c-input" name="benefit_type"><?php foreach (benefitTypes() as $key => $label): ?><option value="<?= $key ?>" <?= $entry['benefit_type'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Valor previsto (opcional)<input class="c-input" name="expected_amount" inputmode="decimal" maxlength="30" value="<?= holeriteEscape($entry['expected_amount'] ?? '') ?>"></label>
        <label>Valor disponibilizado<input class="c-input" name="credited_amount" inputmode="decimal" maxlength="30" value="<?= holeriteEscape($entry['credited_amount'] ?? '') ?>" required></label>
        <label>Data do credito<input class="c-input" type="date" name="credited_date" value="<?= holeriteEscape($entry['credited_date'] ?? '') ?>"></label>
    </div><label class="h-notes">Observacoes<textarea class="c-input" name="notes" rows="3" maxlength="4000"><?= holeriteEscape($entry['notes'] ?? '') ?></textarea></label></section>
    <section class="h-section"><h2>Comprovante</h2><label>PDF (opcional, ate 2 MB)<input class="c-input" type="file" name="benefit_pdf" accept=".pdf,application/pdf"></label>
        <?php if (!empty($entry['attachment_name'])): ?><p><a href="<?= holeriteEscape($url . '?pdf=' . $id) ?>"><?= holeriteEscape($entry['attachment_name']) ?></a></p><label class="h-check"><input type="checkbox" name="remove_pdf" value="1"> Remover comprovante</label><?php endif; ?>
    </section>
    <input type="hidden" name="form_complete" value="1">
    <div class="h-actions"><button class="c-btn-secondary">Salvar beneficio</button><a class="c-btn-secondary" href="<?= holeriteEscape($url) ?>">Cancelar</a></div>
</form>
