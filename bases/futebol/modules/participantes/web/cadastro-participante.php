<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/admin/participantes/helpers.php';

$title = 'Cadastro de ' . participantLabel();
$enabled = participantPublicRegistrationEnabled();

ob_start();
?>

<div class="c-auth-layout">
    <div class="c-auth-card c-participant-register-card">
        <h1 class="c-auth-title">Cadastro de <?= htmlspecialchars(participantLabel()) ?></h1>

        <?php if (!$enabled): ?>
            <p class="c-auth-subtitle">Cadastro nao liberado no momento.</p>
            <div class="c-auth-link">
                <a href="<?= PROJECT_URL ?>/admin/login.php">Voltar para o login</a>
            </div>
        <?php else: ?>
            <p class="c-auth-subtitle">Preencha seus dados para criar seu acesso ao projeto.</p>

            <?php flash_show(); ?>

            <form method="post" action="<?= PROJECT_URL ?>/cadastro-participante-store.php" class="c-auth-form">
                <?= csrf_field(); ?>

                <div class="c-auth-input">
                    <input type="text" name="name" placeholder="Nome completo" required>
                </div>

                <div class="c-auth-input">
                    <input type="text" name="nickname" maxlength="30" placeholder="Apelido (opcional)">
                </div>

                <div class="c-auth-input">
                    <input type="email" name="email" placeholder="E-mail" required>
                </div>

                <div class="c-auth-input">
                    <input type="text" name="username" maxlength="30" pattern="[a-z0-9._-]{3,30}" placeholder="Usuario" required>
                </div>

                <div class="c-auth-input">
                    <input type="password" name="password" minlength="4" placeholder="Senha" required>
                </div>

                <div class="c-auth-input">
                    <input type="text" name="whatsapp" placeholder="WhatsApp">
                </div>

                <div class="c-auth-input c-auth-field">
                    <label for="birth_date">Data de nascimento</label>
                    <input type="date" id="birth_date" name="birth_date" min="1900-01-01" max="<?= date('Y-m-d') ?>">
                </div>

                <button type="submit" class="c-auth-btn c-btn-block">Enviar cadastro</button>
            </form>

            <div class="c-auth-link">
                <a href="<?= PROJECT_URL ?>/admin/login.php">Ja tenho acesso</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.c-participant-register-card {
    max-width: 460px;
    padding: 28px 24px 24px;
}

.c-participant-register-card .c-auth-form {
    gap: 9px;
}

.c-participant-register-card .c-auth-input input {
    width: 100%;
    border: 1px solid rgba(148, 163, 184, .45);
    background: rgba(2, 6, 23, .72);
    color: inherit;
    min-height: 38px;
    padding: 0 12px;
}

.c-participant-register-card .c-auth-field {
    text-align: left;
}

.c-participant-register-card .c-auth-field label {
    display: block;
    margin: 0 0 5px;
    font-size: .78rem;
    color: var(--muted);
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_auth.php';
