<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$userId = $_SESSION['core_user']['id'];

$stmt = $pdo->prepare("
SELECT name, email, avatar
FROM core_users 
WHERE id = :id
");

$stmt->execute(['id' => $userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

$title = 'Meu Perfil';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Meu Perfil</h1>
            <p class="c-page-subtitle">Atualize seus dados e senha</p>
        </div>
    </div>

    <div class="c-page-content">

        <div class="c-card">
            <p>Atualize suas informações pessoais e senha de acesso.</p>
        </div>

        <form method="post" action="/app/actions/profile/save.php" enctype="multipart/form-data">

            <?= csrf_field(); ?>

            <div class="c-profile-grid">

                <!-- COLUNA 1 -->
                <div class="c-card">

                    <h3>Dados Pessoais</h3>

                    <div class="c-form-group">
                        <label>Nome</label>
                        <input class="c-input" name="name"
                               value="<?= htmlspecialchars($user['name']) ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Email</label>
                        <input class="c-input" type="email" name="email"
                               value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                </div>

                <!-- COLUNA 2 -->
                <div class="c-card">

                    <h3>Alterar Senha</h3>

                    <div class="c-form-group">
                        <label>Senha Atual</label>
                        <input class="c-input" type="password" name="current_password">
                    </div>

                    <div class="c-form-group">
                        <label>Nova Senha</label>
                        <input class="c-input" type="password" name="new_password">
                    </div>

                    <div class="c-form-group">
                        <label>Confirmar Nova Senha</label>
                        <input class="c-input" type="password" name="confirm_password">
                    </div>

                </div>

                <!-- COLUNA 3 -->
                <div class="c-card">
                                       <h3>Avatar</h3>

					<?php if (!empty($user['avatar'])): ?>

    <div class="c-avatar-preview">
        <img src="/storage/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>" style="width:100%;height:100%;border-radius:50%;">
    </div>

<?php else: ?>

    <div class="c-avatar-preview">
        <?= strtoupper(substr($user['name'], 0, 1)) ?>
    </div>

<?php endif; ?>

<div class="c-form-group">
    <label>Avatar</label>
    <input type="file" name="avatar" class="c-input">
</div>

<label style="font-size:12px;">
    <input type="checkbox" name="remove_avatar" value="1">
    Remover avatar
</label>
	
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

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Informações</h3>

<p>
Altere seus dados pessoais e mantenha sua senha segura.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';