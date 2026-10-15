<?php
$optionalAccess=participantOptionalAccess();
$hasAccount=!empty($participant['user_id']);
if (isset($_SESSION['participant_form_input'])) {
    $input=$_SESSION['participant_form_input'];
    unset($_SESSION['participant_form_input']);
    if ((int)($input['id'] ?? 0)===(int)($participant['id'] ?? 0)) {
        foreach (['name','nickname','email','username','whatsapp','birth_date','notes','create_access','access_status'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) $participant[$key]=$input[$key];
        }
        if (in_array($input['status'] ?? '',['active','inactive','pending'],true)) $participant['status']=$input['status'];
    }
}
$createAccess=!$optionalAccess || $hasAccount || ($participant['create_access'] ?? '')==='1';
$userEmail = (string)($participant['email'] ?? '');
$userUsername = (string)($participant['username'] ?? '');
?>

<div class="c-card c-participant-form">
    <input type="hidden" name="expected_user_id" value="<?= (int)($participant['user_id'] ?? 0) ?>">
    <div class="c-form-grid">
        <label>
            Nome
            <input class="c-input" type="text" name="name" maxlength="150" value="<?= htmlspecialchars((string)($participant['name'] ?? '')) ?>" required>
        </label>

        <label>
            Apelido
            <input class="c-input" type="text" name="nickname" maxlength="30" value="<?= htmlspecialchars((string)($participant['nickname'] ?? '')) ?>">
        </label>

        <label>
            WhatsApp
            <input class="c-input" type="text" name="whatsapp" maxlength="30" value="<?= htmlspecialchars((string)($participant['whatsapp'] ?? '')) ?>">
        </label>

        <label>
            Nascimento
            <input class="c-input" type="date" name="birth_date" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars((string)($participant['birth_date'] ?? '')) ?>">
        </label>

        <label>
            <?= $optionalAccess?'Status do cadastro':'Status' ?>
            <select class="c-input" name="status">
                <?php foreach (['active' => 'Ativo', 'pending' => 'Pendente', 'inactive' => 'Inativo'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($participant['status'] ?? 'active') === $value ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <label>
        Observacoes
        <textarea class="c-input" name="notes" rows="4"><?= htmlspecialchars((string)($participant['notes'] ?? '')) ?></textarea>
    </label>

    <section class="c-participant-access"><h2>Acesso ao projeto</h2>
    <?php if ($optionalAccess): ?>
        <?php if ($hasAccount): ?><span class="c-badge c-badge--neutral"><?= htmlspecialchars(match ($participant['user_status'] ?? '') { 'active'=>'Acesso ativo','inactive'=>'Acesso inativo','blocked'=>'Acesso bloqueado',default=>'Acesso vinculado' }) ?></span>
        <?php else: ?><label class="c-participant-access-check"><input type="checkbox" id="participant-create-access" name="create_access" value="1" <?= $createAccess?'checked':'' ?>>Criar acesso ao projeto</label><?php endif; ?>
    <?php endif; ?>
    <fieldset class="c-form-grid" id="participant-access-fields" <?= $createAccess?'':'hidden disabled' ?>>
        <label>E-mail<input class="c-input" type="email" name="email" maxlength="150" value="<?= htmlspecialchars($userEmail) ?>" required></label>
        <label>Usuario<input class="c-input" type="text" name="username" maxlength="30" pattern="[a-z0-9._-]{3,30}" value="<?= htmlspecialchars($userUsername) ?>" required></label>
        <label>Senha <?= $hasAccount?'(preencha apenas para alterar)':'' ?><input class="c-input" type="password" name="password" minlength="4" <?= $hasAccount?'':'required' ?> autocomplete="new-password"></label>
        <?php if ($optionalAccess && $hasAccount): ?>
        <input type="hidden" name="expected_access_status" value="<?= htmlspecialchars((string)($participant['user_status'] ?? '')) ?>">
        <label>Status do acesso<select class="c-input" name="access_status"><?php foreach (['active'=>'Ativo','inactive'=>'Inativo','blocked'=>'Bloqueado'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($participant['access_status'] ?? $participant['user_status'] ?? '')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
    </fieldset></section>

    <button type="submit" class="c-btn-secondary">Salvar</button>
</div>
<style>
.c-participant-form .c-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.c-participant-form .c-form-grid label,.c-participant-form>label{display:grid;gap:6px;min-width:0}
.c-participant-form>label{margin-top:14px}
.c-participant-access{margin:20px 0;border-top:1px solid var(--border,#334155);padding-top:14px}
.c-participant-access h2{font-size:18px;margin:0 0 12px}
.c-participant-access fieldset{border:0;padding:0;margin:14px 0;min-width:0}
.c-participant-access fieldset[hidden]{display:none}
.c-participant-access-check{display:flex;gap:8px;align-items:center}
.c-participant-access-check input{width:auto}
@media(max-width:640px){.c-participant-form .c-form-grid{grid-template-columns:1fr}}
</style>
<?php if ($optionalAccess && !$hasAccount): ?><script>
document.getElementById('participant-create-access').addEventListener('change',function(){const fields=document.getElementById('participant-access-fields');fields.hidden=!this.checked;fields.disabled=!this.checked;});
</script><?php endif; ?>
