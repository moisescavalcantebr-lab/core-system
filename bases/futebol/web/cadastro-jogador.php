<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/admin/jogadores/positions_helper.php';

playerEnsureDefaultPositions($pdo);

$title = 'Cadastro de Jogador';
$accessAvailable = playerAccessFeatureEnabled();
$enabled = $accessAvailable && getSetting('player_public_registration_enabled', '0') === '1';
$activeCount = $enabled ? (int)$pdo->query("SELECT COUNT(*) FROM players WHERE status = 'active'")->fetchColumn() : 0;
$activeLimit = playerActiveLimit();
$positions = [];
$shirtNumbers = [];

if ($enabled && $activeCount < $activeLimit) {
    $positions = playerAvailablePositions($pdo);
    $shirtNumbers = playerAvailableShirtNumbers($pdo);
}

$groupedPositions = [];
foreach ($positions as $position) {
    $groupLabel = $position['group_label'] ?? 'Outras';
    $groupedPositions[$groupLabel][] = $position;
}

ob_start();
?>

<div class="c-auth-layout">
    <div class="c-auth-card c-player-register-card">
        <h1 class="c-auth-title">Cadastro de Jogador</h1>

        <?php if (!$accessAvailable): ?>
            <p class="c-auth-subtitle">
                Cadastro de jogador disponivel a partir do Plano Start.
            </p>

            <div class="c-auth-link">
                <a href="<?= PROJECT_URL ?>/admin/login.php">Voltar para o login</a>
            </div>
        <?php elseif (!$enabled): ?>
            <p class="c-auth-subtitle">
                Cadastro não liberado no momento.
            </p>

            <div class="c-auth-link">
                <a href="<?= PROJECT_URL ?>/admin/login.php">Voltar para o login</a>
            </div>
        <?php elseif ($activeCount >= $activeLimit || empty($positions)): ?>
            <p class="c-auth-subtitle">
                Cadastro indisponível no momento. Não há posições disponíveis.
            </p>

            <div class="c-auth-link">
                <a href="<?= PROJECT_URL ?>/admin/login.php">Voltar para o login</a>
            </div>
        <?php else: ?>
            <p class="c-auth-subtitle">
                Preencha seus dados para entrar no elenco. O administrador poderá desativar o acesso quando necessário.
            </p>

            <?php flash_show(); ?>

            <form method="post" action="<?= PROJECT_URL ?>/cadastro-jogador-store.php" class="c-auth-form">
                <?= csrf_field(); ?>

                <div class="c-auth-input">
                    <input type="text" name="name" placeholder="Nome completo" required>
                </div>

                <div class="c-auth-input">
                    <input type="text" name="nickname" maxlength="<?= playerNicknameLimit() ?>" placeholder="Apelido no elenco (opcional)">
                </div>

                <div class="c-auth-input">
                    <input
                        type="text"
                        name="username"
                        placeholder="Usuário"
                        maxlength="30"
                        pattern="[a-z0-9._-]{3,30}"
                        title="Use 3 a 30 caracteres: letras minusculas, numeros, ponto, hifen ou underline. Sem espacos."
                        required
                    >
                </div>

                <div class="c-auth-input">
                    <input type="password" name="password" placeholder="Senha" minlength="4" required>
                </div>

                <div class="c-auth-input">
                    <input type="text" name="whatsapp" placeholder="WhatsApp">
                </div>

                <div class="c-auth-input c-auth-field">
                    <label for="birth_date">Data de nascimento</label>
                    <input type="date" id="birth_date" name="birth_date" aria-label="Data de nascimento">
                </div>

                <div class="c-auth-input">
                    <select name="position_id" required>
                        <option value="">Subposicao disponivel</option>
                        <?php foreach ($groupedPositions as $groupLabel => $groupPositions): ?>
                            <optgroup label="<?= htmlspecialchars((string)$groupLabel) ?>">
                                <?php foreach ($groupPositions as $position): ?>
                                    <option value="<?= (int)$position['id'] ?>">
                                        <?= htmlspecialchars(($position['code'] ?? '') . ' - ' . $position['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-auth-input">
                    <select name="shirt_number" required>
                        <option value="">Numero da camisa</option>
                        <?php foreach ($shirtNumbers as $number): ?>
                            <option value="<?= (int)$number ?>"><?= (int)$number ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-auth-input">
                    <select name="dominant_foot">
                        <option value="">Pe dominante</option>
                        <option value="right">Direito</option>
                        <option value="left">Esquerdo</option>
                        <option value="both">Ambos</option>
                    </select>
                </div>

                <button type="submit" class="c-auth-btn c-btn-block">
                    Enviar cadastro
                </button>
            </form>

            <div class="c-auth-link">
                <a href="<?= PROJECT_URL ?>/admin/login.php">Já tenho acesso</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.c-player-register-card {
    max-width: 460px;
    padding: 28px 24px 24px;
}

.c-player-register-card .c-auth-title {
    font-size: 1.45rem;
}

.c-player-register-card .c-auth-subtitle {
    margin-bottom: 18px;
}

.c-player-register-card .c-auth-form {
    gap: 9px;
}

.c-player-register-card .c-auth-input input,
.c-player-register-card .c-auth-input select {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, .45);
    background: rgba(2, 6, 23, .72);
    color: inherit;
    min-height: 38px;
    padding: 0 12px;
    font-size: .88rem;
}

.c-player-register-card .c-auth-input input[type="date"] {
    appearance: none;
    -webkit-appearance: none;
    min-width: 0;
    line-height: 1.2;
}

.c-player-register-card .c-auth-field {
    text-align: left;
}

.c-player-register-card .c-auth-field label {
    display: block;
    margin: 0 0 5px 2px;
    color: var(--text-secondary);
    font-size: .76rem;
    font-weight: 700;
}

.c-player-register-card .c-auth-btn {
    min-height: 42px;
    margin-top: 2px;
}

.c-player-register-card .c-auth-link {
    margin-top: 14px;
}

@media (max-width: 480px) {
    .c-auth-layout {
        align-items: flex-start;
        padding: 18px 12px;
    }

    .c-player-register-card {
        max-width: 100%;
        padding: 22px 18px 20px;
    }

    .c-player-register-card .c-auth-title {
        font-size: 1.75rem;
        line-height: 1.08;
    }

    .c-player-register-card .c-auth-subtitle {
        margin-bottom: 16px;
        font-size: .9rem;
    }

    .c-player-register-card .c-auth-input input,
    .c-player-register-card .c-auth-input select {
        min-height: 36px;
        padding: 0 11px;
        font-size: .9rem;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';
