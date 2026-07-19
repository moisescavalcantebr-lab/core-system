<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($title) ?></h1>
            <p class="c-page-subtitle">Dados principais do jogador</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/jogadores/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <form action="<?= htmlspecialchars($formAction) ?>" method="POST" class="c-card" enctype="multipart/form-data">

            <?= csrf_field(); ?>

            <?php
                $playerAccessAvailable = playerAccessFeatureEnabled();
                $playerFinanceAccessAvailable = playerAccessFeatureEnabled();
                $playerHasAccess = !empty($player['user_id']) && (string)($player['user_status'] ?? '') === 'active';
            ?>

            <?php if ($playerAccessAvailable): ?>
                <div class="c-form-group">
                    <label>
                        <input type="checkbox" name="player_access_enabled" value="1" <?= $playerHasAccess ? 'checked' : '' ?> id="playerAccessToggle">
                        Vincular acesso do jogador
                    </label>
                    <small>Disponivel a partir do Start. Para conta existente, informe a senha da conta para confirmar o vinculo.</small>
                </div>
            <?php else: ?>
                <div class="c-form-group">
                    <span class="c-badge c-badge--neutral">Acesso: Admin</span>
                    <small>No Gratis, o jogador fica ativo apenas para gerenciamento do admin. Acesso do jogador libera no Start.</small>
                </div>
            <?php endif; ?>

            <?php if (!empty($player['id']) && !empty($player['user_id'])): ?>
                <div class="c-form-group">
                    <label>
                        <input type="checkbox" name="remove_user_link" value="1">
                        Remover vinculo com usuario
                    </label>
                </div>
            <?php endif; ?>

            <div class="c-form-group" id="playerNameField">
                <label>Nome</label>
                <input type="text" name="name" class="c-input" value="<?= htmlspecialchars((string)$player['name']) ?>">
            </div>

            <div class="c-form-group">
                <label>Apelido no elenco</label>
                <input
                    type="text"
                    name="nickname"
                    class="c-input"
                    value="<?= htmlspecialchars((string)($player['nickname'] ?? '')) ?>"
                    maxlength="<?= playerNicknameLimit() ?>"
                    placeholder="Opcional. Ex.: Moises"
                >
                <small>Nome curto usado nos cards e avatares. Se ficar vazio, usamos o primeiro nome.</small>
            </div>

            <div class="c-form-group">
                <label>Avatar</label>
                <?php if (!empty($player['avatar'])): ?>
                    <div class="c-player-form-avatar-preview">
                        <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars((string)$player['avatar']) ?>" alt="Avatar atual">
                        <label>
                            <input type="checkbox" name="remove_avatar" value="1">
                            Remover avatar
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" name="avatar" class="c-input" accept="image/jpeg,image/png,image/webp">
                <small>Imagem opcional para aparecer no card do jogador.</small>
            </div>

            <div class="c-form-grid" id="playerAccessFields" <?= $playerAccessAvailable ? '' : 'style="display:none;"' ?>>
                <div class="c-form-group">
                    <label>Usuário</label>
                    <input
                        type="text"
                        name="username"
                        class="c-input"
                        value="<?= htmlspecialchars((string)($player['username'] ?? '')) ?>"
                        maxlength="30"
                        pattern="[a-z0-9._-]{3,30}"
                        title="Use 3 a 30 caracteres: letras minusculas, numeros, ponto, hifen ou underline. Sem espacos."
                    >
                </div>

                <div class="c-form-group">
                    <label>Senha <?= empty($player['id']) ? '' : '(preencha para alterar)' ?></label>
                    <input type="password" name="password" class="c-input" placeholder="<?= empty($player['id']) ? 'Padrao: 1234' : '' ?>">
                    <small>Se o usuário já existir, informe a senha atual dele para confirmar o vínculo.</small>
                </div>
            </div>

            <div class="c-form-group">
                <label>WhatsApp</label>
                <input type="text" name="whatsapp" class="c-input" value="<?= htmlspecialchars((string)($player['whatsapp'] ?? '')) ?>">
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Posição</label>
                    <select name="position_id" class="c-input">
                        <option value="">Selecione a posição</option>
                        <?php
                        $groupedPositions = [];
                        foreach ($positions as $position) {
                            $groupLabel = $position['group_label'] ?? 'Outras';
                            $groupedPositions[$groupLabel][] = $position;
                        }
                        ?>
                        <?php foreach ($groupedPositions as $groupLabel => $groupPositions): ?>
                            <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
                                <?php foreach ($groupPositions as $position): ?>
                                    <option value="<?= (int)$position['id'] ?>" <?= (string)($player['position_id'] ?? '') === (string)$position['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($position['code'] ?? '') . ' - ' . $position['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Situação no elenco</label>
                    <select name="roster_status" class="c-input">
                        <option value="titular" <?= ($player['roster_status'] ?? 'titular') === 'titular' ? 'selected' : '' ?>>Titular</option>
                        <option value="reserva" <?= ($player['roster_status'] ?? '') === 'reserva' ? 'selected' : '' ?>>Reserva</option>
                    </select>
                    <small>Define se o jogador entra no time titular ou fica como reserva. Inativos continuam fora desta tela.</small>
                </div>

                <div class="c-form-group">
                    <label>Numero da camisa</label>
                    <select name="shirt_number" class="c-input">
                        <option value="">Selecione a camisa</option>
                        <?php foreach (($shirtNumbers ?? range(1, 99)) as $number): ?>
                            <option value="<?= (int)$number ?>" <?= (string)($player['shirt_number'] ?? '') === (string)$number ? 'selected' : '' ?>>
                                <?= (int)$number ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="c-form-grid">
                <div class="c-form-group">
                    <label>Nascimento</label>
                    <input type="date" name="birth_date" class="c-input" value="<?= htmlspecialchars((string)($player['birth_date'] ?? '')) ?>">
                </div>

                <div class="c-form-group">
                    <label>Pe dominante</label>
                    <select name="dominant_foot" class="c-input">
                        <option value="">Nao informado</option>
                        <option value="right" <?= ($player['dominant_foot'] ?? '') === 'right' ? 'selected' : '' ?>>Direito</option>
                        <option value="left" <?= ($player['dominant_foot'] ?? '') === 'left' ? 'selected' : '' ?>>Esquerdo</option>
                        <option value="both" <?= ($player['dominant_foot'] ?? '') === 'both' ? 'selected' : '' ?>>Ambos</option>
                    </select>
                </div>
            </div>

            <div class="c-form-group">
                <label>Status</label>
                <select name="status" class="c-input">
                    <option value="active" <?= ($player['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= ($player['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>

            <div class="c-form-group">
                <label>
                    <input type="checkbox" name="promote_finance" value="1" <?= ($player['user_role'] ?? '') === 'FINANCE' ? 'checked' : '' ?> <?= $playerFinanceAccessAvailable ? '' : 'disabled' ?>>
                    Acesso financeiro
                </label>
                <?php if (!$playerFinanceAccessAvailable): ?>
                    <small>Recursos financeiros do jogador ficam disponiveis a partir do Start.</small>
                <?php endif; ?>
            </div>

            <div class="c-form-group">
                <label>Observacoes</label>
                <textarea name="notes" class="c-input" rows="5"><?= htmlspecialchars((string)($player['notes'] ?? '')) ?></textarea>
            </div>

            <button type="submit" class="c-btn-secondary">
                <?= htmlspecialchars($submitLabel) ?>
            </button>

        </form>

        <?php if (!empty($player['id'])): ?>
            <form action="<?= PROJECT_URL ?>/admin/jogadores/delete.php?id=<?= (int)$player['id'] ?>" method="POST" class="c-card" onsubmit="return confirm('Excluir este jogador?');">
                <?= csrf_field(); ?>
                <button type="submit" class="c-btn-secondary">
                    Excluir Jogador
                </button>
            </form>
        <?php endif; ?>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const accessFields = document.getElementById('playerAccessFields');
    const accessToggle = document.getElementById('playerAccessToggle');

    if (!accessFields) {
        return;
    }

    const syncAccessFields = function () {
        const accessEnabled = !accessToggle || accessToggle.checked;
        accessFields.style.display = accessEnabled ? '' : 'none';
    };

    if (accessToggle) {
        accessToggle.addEventListener('change', syncAccessFields);
    }

    syncAccessFields();
});
</script>

<style>
.c-player-form-avatar-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.c-player-form-avatar-preview img {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, .75);
}
</style>

