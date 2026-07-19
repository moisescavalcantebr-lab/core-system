<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$settingsService = new SettingsService($pdo);
$settings = $settingsService->all();
$userId = $_SESSION['core_user']['id'];

$stmt = $pdo->prepare("
    SELECT name, email, avatar
    FROM core_users
    WHERE id = :id
");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$title = 'Configurações';

$themes = array_map(function($file){
    return basename($file, '.css');
}, glob(PUBLIC_PATH . '/assets/css/themes/*.css'));

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configurações</h1>
            <p class="c-page-subtitle">Sistema, aparência e conta do usuário</p>
        </div>
    </div>

    <div class="c-page-content">

        <form id="systemSettingsForm"
              method="post"
              action="/app/actions/settings/save.php"
              enctype="multipart/form-data">
            <?= csrf_field(); ?>
        </form>

        <form id="profileSettingsForm"
              method="post"
              action="/app/actions/profile/save.php"
              enctype="multipart/form-data">
            <?= csrf_field(); ?>
            <input type="hidden" name="redirect_to" value="/web/admin/settings/index.php#perfil">
        </form>

        <div class="c-settings-account-grid">

            <div class="c-settings-column">
                <div class="c-card c-settings-card">
                    <div class="c-settings-card-title">
                        <span>Sistema</span>
                        <small>Identidade principal</small>
                    </div>

                    <div class="c-form-group">
                        <label>Nome do Sistema</label>
                        <input class="c-input"
                               form="systemSettingsForm"
                               name="app_name"
                               value="<?= htmlspecialchars($settings['app_name'] ?? 'CORE') ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Tema do Sistema</label>
                        <select class="c-input" form="systemSettingsForm" name="theme">
                            <?php foreach ($themes as $themeItem): ?>
                                <option value="<?= $themeItem ?>"
                                    <?= ($coreSettings['theme'] ?? '') === $themeItem ? 'selected' : '' ?>>
                                    <?= ucfirst($themeItem) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button class="c-btn-secondary c-settings-save" form="systemSettingsForm">
                        Salvar Sistema
                    </button>
                </div>

                <div class="c-card c-settings-card c-avatar-card">
                    <div class="c-settings-card-title">
                        <span>Avatar</span>
                        <small>Imagem do usuário</small>
                    </div>

                    <?php if (!empty($user['avatar'])): ?>
                        <div class="c-avatar-preview">
                            <img src="/storage/uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>?v=<?= time() ?>" alt="Avatar">
                        </div>
                    <?php else: ?>
                        <div class="c-avatar-preview">
                            <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <div class="c-form-group">
                        <label>Avatar</label>
                        <input type="file" form="profileSettingsForm" name="avatar" class="c-input">
                    </div>

                    <label class="c-check-label">
                        <input type="checkbox" form="profileSettingsForm" name="remove_avatar" value="1">
                        Remover avatar
                    </label>

                    <button class="c-btn-secondary c-settings-save" form="profileSettingsForm">
                        Salvar Avatar
                    </button>
                </div>
            </div>

            <div class="c-settings-column" id="perfil">
                <div class="c-card c-settings-card">
                    <div class="c-settings-card-title">
                        <span>Dados Pessoais</span>
                        <small>Informações da conta</small>
                    </div>

                    <div class="c-form-group">
                        <label>Nome</label>
                        <input class="c-input" form="profileSettingsForm" name="name"
                               value="<?= htmlspecialchars($user['name'] ?? '') ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Email</label>
                        <input class="c-input" form="profileSettingsForm" type="email" name="email"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>

                    <button class="c-btn-secondary c-settings-save" form="profileSettingsForm">
                        Salvar Dados
                    </button>
                </div>

                <div class="c-card c-settings-card">
                    <div class="c-settings-card-title">
                        <span>Alterar Senha</span>
                        <small>Segurança de acesso</small>
                    </div>

                    <div class="c-form-group">
                        <label>Senha Atual</label>
                        <input class="c-input" form="profileSettingsForm" type="password" name="current_password">
                    </div>

                    <div class="c-form-group">
                        <label>Nova Senha</label>
                        <input class="c-input" form="profileSettingsForm" type="password" name="new_password">
                    </div>

                    <div class="c-form-group">
                        <label>Confirmar Nova Senha</label>
                        <input class="c-input" form="profileSettingsForm" type="password" name="confirm_password">
                    </div>

                    <button class="c-btn-secondary c-settings-save" form="profileSettingsForm">
                        Salvar Senha
                    </button>
                </div>
            </div>

            <div class="c-settings-column">
                <div class="c-card c-settings-card c-brand-card">
                    <div class="c-settings-card-title">
                        <span>Marca</span>
                        <small>Logo e favicon</small>
                    </div>

                    <div class="c-form-group">
                        <label>Logo</label>
                        <input type="file" form="systemSettingsForm" name="app_logo" class="c-input">

                        <?php if (!empty($settings['app_logo'])): ?>
                            <div class="c-settings-preview">
                                <img src="/web/assets/uploads/<?= htmlspecialchars($settings['app_logo']) ?>">
                            </div>

                            <label class="c-check-label">
                                <input type="checkbox" form="systemSettingsForm" name="remove_logo" value="1">
                                Remover logo
                            </label>
                        <?php endif; ?>
                    </div>

                    <div class="c-form-group">
                        <label>Favicon</label>
                        <input type="file" form="systemSettingsForm" name="app_favicon" class="c-input">

                        <?php if (!empty($settings['app_favicon'])): ?>
                            <div class="c-settings-preview c-settings-preview--favicon">
                                <img src="/web/assets/uploads/<?= htmlspecialchars($settings['app_favicon']) ?>">
                            </div>

                            <label class="c-check-label">
                                <input type="checkbox" form="systemSettingsForm" name="remove_favicon" value="1">
                                Remover favicon
                            </label>
                        <?php endif; ?>
                    </div>

                    <button class="c-btn-secondary c-settings-save" form="systemSettingsForm">
                        Salvar Marca
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>

<style>
.c-settings-account-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(260px, 1fr));
    gap: 16px;
    align-items: start;
}

.c-settings-column {
    display: grid;
    gap: 16px;
    align-items: start;
}

.c-settings-card {
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.c-settings-card-title {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-bottom: 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
}

.c-settings-card-title span {
    font-size: 14px;
    font-weight: 700;
}

.c-settings-card-title small {
    color: var(--text-secondary);
    font-size: 11px;
}

.c-avatar-card {
    align-content: start;
}

.c-avatar-card .c-avatar-preview {
    margin: 4px auto 16px;
    width: 104px;
    height: 104px;
}

.c-settings-preview {
    margin: 8px 0;
    padding: 10px;
    border: 1px solid var(--border-color);
    background: rgba(255,255,255,.03);
    border-radius: 8px;
}

.c-settings-preview img {
    display: block;
    max-width: 100%;
    max-height: 64px;
}

.c-settings-save {
    width: 100%;
    margin-top: auto;
}

.c-settings-preview--favicon img {
    max-height: 34px;
}

.c-check-label {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 6px 0 10px;
    font-size: 11px;
    color: var(--text-secondary);
}

.c-avatar-preview img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

@media (max-width: 1100px) {
    .c-settings-account-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
    <p>Configure identidade visual, tema e dados da sua conta.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
