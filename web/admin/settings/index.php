<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$settingsService = new SettingsService($pdo);
$settings = $settingsService->all();

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
            <p class="c-page-subtitle">Identidade visual do sistema</p>
        </div>
    </div>

    <div class="c-page-content">

        <div class="c-card">
            <p>Gerencie identidade visual e aparência do sistema.</p>
        </div>

        <form method="post"
              action="/app/actions/settings/save.php"
              enctype="multipart/form-data">

            <?= csrf_field(); ?>

            <div class="c-settings-grid">

                <!-- COLUNA 1 -->
                <div class="c-card">

                    <h3>Identidade</h3>

                    <div class="c-form-group">
                        <label>Nome do Sistema</label>
                        <input class="c-input"
                               name="app_name"
                               value="<?= htmlspecialchars($settings['app_name'] ?? 'CORE') ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Logo</label>
                        <input type="file" name="app_logo" class="c-input">

                        <?php if (!empty($settings['app_logo'])): ?>

                            <div style="margin-top:10px;">
                                <img src="/public/assets/uploads/<?= htmlspecialchars($settings['app_logo']) ?>"
                                     style="max-height:80px;">
                            </div>

                            <label style="font-size:12px;">
                                <input type="checkbox" name="remove_logo" value="1">
                                Remover logo
                            </label>

                        <?php endif; ?>
                    </div>

                    <div class="c-form-group">
                        <label>Favicon</label>
                        <input type="file" name="app_favicon" class="c-input">

                        <?php if (!empty($settings['app_favicon'])): ?>

                            <div style="margin-top:10px;">
                                <img src="/public/assets/uploads/<?= htmlspecialchars($settings['app_favicon']) ?>"
                                     style="max-height:40px;">
                            </div>

                            <label style="font-size:12px;">
                                <input type="checkbox" name="remove_favicon" value="1">
                                Remover favicon
                            </label>

                        <?php endif; ?>
                    </div>

                </div>

                <!-- COLUNA 2 -->
                <div class="c-card">

                    <h3>Temas</h3>

                    <div class="c-form-group">
                        <label>Selecionar Tema</label>

                        <select class="c-input" name="theme">

                            <?php foreach ($themes as $themeItem): ?>

<option value="<?= $themeItem ?>"
    <?= ($coreSettings['theme'] ?? '') === $themeItem ? 'selected' : '' ?>>
    <?= ucfirst($themeItem) ?>
</option>
                            <?php endforeach; ?>

                        </select>

                    </div>

                    <p style="font-size:12px;opacity:.7;">
                        Os temas controlam cores e aparência global do sistema.
                    </p>

                </div>

                <!-- COLUNA 3 -->
                <div class="c-card">

                    <h3>Avançado</h3>

                    <p style="font-size:13px;opacity:.7;">
                        Espaço reservado para futuras configurações:
                    </p>

                    <ul style="font-size:12px;opacity:.7;padding-left:15px;">
                        <li>Configuração de módulos</li>
                        <li>Integrações</li>
                        <li>Preferências globais</li>
                    </ul>

                </div>

            </div>

            <div style="margin-top:20px;">
                <button class="c-btn-secondary">
                    Salvar Configurações
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
Configure logo, favicon e tema do sistema.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';