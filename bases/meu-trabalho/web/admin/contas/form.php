<?php $entry += ['kind'=>'payable','due_date'=>$today]; ?>
<form method="post" enctype="multipart/form-data" action="<?= contasEscape($url.'?view=edit'.($id ? '&id='.$id : '')) ?>">
<?= csrf_field() ?><input type="hidden" name="operation" value="save">
<section class="a-section"><h2><?= $id ? 'Editar conta' : 'Nova conta' ?></h2><div class="a-form-grid">
<label>Tipo<select class="c-input" name="kind"><?php foreach(contasKinds() as $key=>$label): ?><option value="<?= $key ?>" <?= $entry['kind'] === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<label>Descricao<input class="c-input" name="title" maxlength="150" value="<?= contasEscape($entry['title'] ?? '') ?>" required></label>
<label>Pessoa ou empresa<input class="c-input" name="counterparty" maxlength="150" value="<?= contasEscape($entry['counterparty'] ?? '') ?>"></label>
<label>Categoria<input class="c-input" name="category" list="account-categories" maxlength="80" value="<?= contasEscape($entry['category'] ?? '') ?>"></label><datalist id="account-categories"><?php foreach($categories as $category): ?><option value="<?= contasEscape($category) ?>"><?php endforeach; ?></datalist>
<label>Valor da conta<input class="c-input" name="amount" inputmode="decimal" maxlength="30" value="<?= contasEscape($entry['amount'] ?? '') ?>" required></label><label>Vencimento<input class="c-input" type="date" name="due_date" min="1900-01-01" max="2200-12-31" value="<?= contasEscape($entry['due_date']) ?>" required></label>
</div><label class="a-notes">Observacoes<textarea class="c-input" name="notes" rows="3" maxlength="4000"><?= contasEscape($entry['notes'] ?? '') ?></textarea></label></section>
<section class="a-section"><h2>Documento</h2><label>PDF (opcional, ate 2 MB)<input class="c-input" type="file" name="account_pdf" accept=".pdf,application/pdf"></label><?php if(!empty($entry['attachment_name'])): ?><p><a href="<?= contasEscape($url.'?pdf='.$id) ?>"><?= contasEscape($entry['attachment_name']) ?></a></p><label class="a-check"><input type="checkbox" name="remove_pdf" value="1"> Remover documento</label><?php endif; ?></section>
<input type="hidden" name="form_complete" value="1"><div class="a-actions" style="padding-top:22px"><button class="c-btn-secondary">Salvar conta</button><a class="c-btn-secondary" href="<?= contasEscape($url) ?>">Voltar</a></div></form>
