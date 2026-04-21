<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

/* =========================
CSRF
========================= */

csrf_verify();

/* =========================
CONFIG
========================= */

$uploadDir = PUBLIC_PATH . '/storage/uploads/system/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$allowed = ['jpg','jpeg','png','webp','ico'];

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
setSetting('site_description', trim($_POST['site_description'] ?? ''));

$selectedTheme = $_POST['theme'] ?? 'light';

if (in_array($selectedTheme, $themes)) {
    setSetting('theme', $selectedTheme);
}

setSetting('primary_color', $_POST['primary_color'] ?? '#111111');

/* =========================
LOGO
========================= */

if (!empty($_FILES['logo']['name'])) {

    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        flash('error', 'Formato de logo inválido.');
        redirect(PROJECT_URL . '/admin/settings/index.php');
    }

    $fileName = 'logo_' . time() . '.' . $ext;
    $dest = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {

        $old = getSetting('logo');

        if ($old && file_exists(PUBLIC_PATH . '/' . $old)) {
            @unlink(PUBLIC_PATH . '/' . $old);
        }

        setSetting('logo', 'storage/uploads/system/' . $fileName, 'branding');
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

    $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        flash('error', 'Formato de favicon inválido.');
        redirect(PROJECT_URL . '/admin/settings/index.php');
    }

    $fileName = 'favicon_' . time() . '.' . $ext;
    $dest = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['favicon']['tmp_name'], $dest)) {

        $old = getSetting('favicon');

        if ($old && file_exists(PUBLIC_PATH . '/' . $old)) {
            @unlink(PUBLIC_PATH . '/' . $old);
        }

        setSetting('favicon', 'storage/uploads/system/' . $fileName, 'branding');
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