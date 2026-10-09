<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

/* =========================
CSRF
========================= */

csrf_verify();

/* =========================
THEMES
========================= */

$themesDir = PUBLIC_PATH . '/assets/css/themes';
$themes = [];

if (is_dir($themesDir)) {
    foreach (scandir($themesDir) as $file) {
        if (str_ends_with($file, '.css')) {
            $themes[] = str_replace('.css', '', $file);
        }
    }
}

/* =========================
SALVAR DADOS
========================= */

setSetting('site_name', trim($_POST['site_name'] ?? ''));

$selectedTheme = $_POST['theme'] ?? 'light';

if (in_array($selectedTheme, $themes)) {
    setSetting('theme', $selectedTheme);
}

/* =========================
LOGO
========================= */

if (!empty($_FILES['logo']['name'])) {

    $uploadedLogo = function_exists('uploadLogo')
        ? uploadLogo($_FILES['logo'])
        : null;

    if ($uploadedLogo) {
        $old = getSetting('logo');

        if ($old && file_exists(PUBLIC_PATH . '/' . $old)) {
            @unlink(PUBLIC_PATH . '/' . $old);
        }

        setSetting('logo', $uploadedLogo, 'branding');
    } else {
        flash('error', 'Formato de logo inválido.');
        redirect(PROJECT_URL . '/admin/settings/index.php');
    }
}

/* REMOVER LOGO */

if (!empty($_POST['remove_logo'])) {

    $old = getSetting('logo');

    if ($old && file_exists(PUBLIC_PATH . '/' . $old)) {
        @unlink(PUBLIC_PATH . '/' . $old);
    }

    setSetting('logo', '', 'branding');
}

/* =========================
FAVICON
========================= */

if (!empty($_FILES['favicon']['name'])) {

    $uploadedFavicon = function_exists('uploadFavicon')
        ? uploadFavicon($_FILES['favicon'])
        : null;

    if ($uploadedFavicon) {
        $old = getSetting('favicon');

        if ($old && file_exists(PUBLIC_PATH . '/' . $old)) {
            @unlink(PUBLIC_PATH . '/' . $old);
        }

        setSetting('favicon', $uploadedFavicon, 'branding');
    } else {
        flash('error', 'Formato de favicon inválido.');
        redirect(PROJECT_URL . '/admin/settings/index.php');
    }
}

/* REMOVER FAVICON */

if (!empty($_POST['remove_favicon'])) {

    $old = getSetting('favicon');

    if ($old && file_exists(PUBLIC_PATH . '/' . $old)) {
        @unlink(PUBLIC_PATH . '/' . $old);
    }

    setSetting('favicon', '', 'branding');
}

/* =========================
FINAL
========================= */

flash('success', 'Configurações salvas com sucesso.');

redirect(PROJECT_URL . '/admin/settings/index.php');
