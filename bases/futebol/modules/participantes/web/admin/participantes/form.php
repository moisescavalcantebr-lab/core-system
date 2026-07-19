<?php
$isEdit = !empty($participant);
$userEmail = (string)($participant['email'] ?? '');
$userUsername = (string)($participant['username'] ?? '');
?>

<div class="c-card">
    <div class="c-form-grid">
        <label>
            Nome
            <input type="text" name="name" value="<?= htmlspecialchars((string)($participant['name'] ?? '')) ?>" required>
        </label>

        <label>
            Apelido
            <input type="text" name="nickname" maxlength="30" value="<?= htmlspecialchars((string)($participant['nickname'] ?? '')) ?>">
        </label>

        <label>
            E-mail
            <input type="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" required>
        </label>

        <label>
            Usuario
            <input type="text" name="username" maxlength="30" pattern="[a-z0-9._-]{3,30}" value="<?= htmlspecialchars($userUsername) ?>" required>
        </label>

        <label>
            Senha <?= $isEdit ? '(preencha apenas para alterar)' : '' ?>
            <input type="password" name="password" minlength="4" <?= $isEdit ? '' : 'required' ?>>
        </label>

        <label>
            WhatsApp
            <input type="text" name="whatsapp" value="<?= htmlspecialchars((string)($participant['whatsapp'] ?? '')) ?>">
        </label>

        <label>
            Nascimento
            <input type="date" name="birth_date" min="1900-01-01" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars((string)($participant['birth_date'] ?? '')) ?>">
        </label>

        <label>
            Status
            <select name="status">
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
        <textarea name="notes" rows="4"><?= htmlspecialchars((string)($participant['notes'] ?? '')) ?></textarea>
    </label>

    <button type="submit" class="c-btn-primary">Salvar</button>
</div>
