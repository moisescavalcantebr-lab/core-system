<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

$user = projectUser();

$title = 'Meu Perfil';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Meu Perfil</h1>
            <p class="c-page-subtitle">Gerencie seus dados e segurança</p>
        </div>
    </div>

    <div class="c-page-content">

        <?php flash_show(); ?>

        <form method="post"
              action="<?= PROJECT_URL ?>/admin/profile/save.php"
              enctype="multipart/form-data">

            <?= csrf_field(); ?>

            <div class="c-profile-grid">

                <!-- DADOS -->
                <div class="c-card">

                    <h3>Dados Pessoais</h3>

                    <div class="c-form-group">
                        <label>Nome</label>
                        <input class="c-input" name="name"
                               value="<?= htmlspecialchars($user['name']) ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Email</label>
                        <input class="c-input"
                               value="<?= htmlspecialchars($user['email']) ?>"
                               disabled>
                    </div>

                </div>

                <!-- SENHA -->
                <div class="c-card">

                    <h3>Alterar Senha</h3>

                    <div class="c-form-group">
                        <label>Nova Senha</label>
                        <input class="c-input" type="password" name="password">
                    </div>

                    <div class="c-form-group">
                        <label>Confirmar Senha</label>
                        <input class="c-input" type="password" name="confirm_password">
                    </div>

                </div>

                <!-- AVATAR -->
                <div class="c-card">

                    <h3>Avatar</h3>

                    <?php if (!empty($user['avatar'])): ?>

                        <div class="c-avatar-preview">
                            <img src="<?= PROJECT_URL ?>/<?= htmlspecialchars($user['avatar']) ?>"
                                 style="width:100%;height:100%;border-radius:50%;">
                        </div>

                    <?php else: ?>

                        <div class="c-avatar-preview">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>

                    <?php endif; ?>

                    <div class="c-form-group">
                        <input type="file" name="avatar" class="c-input">
                    </div>
<?php if (!empty($user['avatar'])): ?>
    <label style="font-size:12px;">
        <input type="checkbox" name="remove_avatar"> Remover avatar
    </label>
<?php endif; ?>
                </div>

            </div>

            <div style="margin-top:20px;">
                <button class="c-btn-secondary">
                    Salvar Alterações
                </button>
            </div>

        </form>

    </div>

</div>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;

$rightSidebarContent = '
<div class="c-card">
    <h3>Informações</h3>
    <p>Mantenha seus dados atualizados e seguros.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';