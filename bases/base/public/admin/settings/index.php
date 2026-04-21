<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

/* =========================
DADOS
========================= */

$themes = array_map(function($file){
    return basename($file, '.css');
}, glob(PUBLIC_PATH . '/assets/css/themes/*.css') ?: []);

$siteName = getSetting('site_name', $project['name'] ?? '');
$theme = getSetting('theme', 'light');

$logo = getSetting('logo') ?? '';
$favicon = getSetting('favicon') ?? '';

$title = 'Configurações';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configurações</h1>
            <p class="c-page-subtitle">Personalize seu projeto</p>
        </div>
    </div>

    <div class="c-page-content">

        <?php if (function_exists('flash_show')) flash_show(); ?>

        <form method="post"
              action="<?= PROJECT_URL ?>/admin/settings/save.php"
              enctype="multipart/form-data">

            <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

            <div class="c-profile-grid">

                <!-- IDENTIDADE -->
                <div class="c-dashboard-card">
                    <h4>Identidade</h4>

                    <div class="c-form-group">
                        <label>Nome do Site</label>
                        <input class="c-input"
                               name="site_name"
                               value="<?= htmlspecialchars($siteName) ?>">
                    </div>

                    <div class="c-form-group">
                        <label>Tema</label>
                        <select class="c-input" name="theme">
                            <?php foreach ($themes as $t): ?>
                                <option value="<?= $t ?>" <?= $theme === $t ? 'selected' : '' ?>>
                                    <?= ucfirst($t) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- LOGO -->
                <div class="c-dashboard-card">
                    <h4>Logo</h4>

                    <?php if (!empty($logo)): ?>
                        <div style="margin-bottom:10px;">
                            <img src="<?= media($logo) ?>" style="max-height:60px;">
                        </div>
                    <?php endif; ?>

                    <input type="file" name="logo" class="c-input">

                    <?php if (!empty($logo)): ?>
                        <label style="font-size:12px;">
                            <input type="checkbox" name="remove_logo"> Remover
                        </label>
                    <?php endif; ?>
                </div>

                <!-- FAVICON -->
                <div class="c-dashboard-card">
                    <h4>Favicon</h4>

                    <?php if (!empty($favicon)): ?>
                        <div style="margin-bottom:10px;">
                            <img src="<?= media($favicon) ?>" style="max-height:32px;">
                        </div>
                    <?php endif; ?>

                    <input type="file" name="favicon" class="c-input">

                    <?php if (!empty($favicon)): ?>
                        <label style="font-size:12px;">
                            <input type="checkbox" name="remove_favicon"> Remover
                        </label>
                    <?php endif; ?>
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
require APP_PATH . '/views/layout_admin.php';