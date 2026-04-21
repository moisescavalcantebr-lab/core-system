<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
csrf_verify();

$settingsService = new SettingsService($pdo);

$settingsService->set('app_name', trim($_POST['app_name'] ?? ''));

$themes = array_map(function($file){
    return basename($file, '.css');
}, glob(PUBLIC_PATH . '/assets/css/themes/*.css'));

$theme = $_POST['theme'] ?? 'dark';

if (!in_array($theme, $themes)) {
    $theme = 'dark';
}

$settingsService->set('theme', $theme);

$themes = array_map(function($file){
    return basename($file, '.css');
}, glob(PUBLIC_PATH . '/assets/css/themes/*.css'));

/*
|--------------------------------------------------------------------------
| REMOVER LOGO
|--------------------------------------------------------------------------
*/

if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {

    $currentLogo = $settingsService->get('app_logo');

    if ($currentLogo) {

        $path = PUBLIC_PATH . '/assets/uploads/' . $currentLogo;

        if (file_exists($path)) {
            unlink($path);
        }
    }

    $settingsService->set('app_logo', '');
}

/*
|--------------------------------------------------------------------------
| UPLOAD NOVA LOGO
|--------------------------------------------------------------------------
*/

if (!empty($_FILES['app_logo']['name'])) {

    $allowed = ['image/png','image/jpeg','image/webp'];

    if (!in_array($_FILES['app_logo']['type'], $allowed)) {
        flash('error', 'Formato inválido. Use PNG, JPG ou WEBP.');
        redirect('/public/admin/settings/index.php');
        exit;
    }

    $uploadPath = PUBLIC_PATH . '/assets/uploads/';

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $ext = pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION);
    $fileName = 'core_logo_' . time() . '.' . $ext;
    $dest = $uploadPath . $fileName;

    move_uploaded_file($_FILES['app_logo']['tmp_name'], $dest);

    $settingsService->set('app_logo', $fileName);
}
/*
|--------------------------------------------------------------------------
| REMOVER FAVICON
|--------------------------------------------------------------------------
*/

if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] === '1') {

    $current = $settingsService->get('app_favicon');

    if ($current) {
        $path = PUBLIC_PATH . '/assets/uploads/' . $current;
        if (file_exists($path)) {
            unlink($path);
        }
    }

    $settingsService->set('app_favicon', '');
}

/*
|--------------------------------------------------------------------------
| UPLOAD FAVICON
|--------------------------------------------------------------------------
*/

if (!empty($_FILES['app_favicon']['name'])) {

    $allowed = ['image/png','image/x-icon','image/vnd.microsoft.icon'];

    if (!in_array($_FILES['app_favicon']['type'], $allowed)) {
        flash('error', 'Favicon deve ser PNG ou ICO.');
        redirect('/public/admin/settings/index.php');
        exit;
    }

    $uploadPath = PUBLIC_PATH . '/assets/uploads/';

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    $ext = pathinfo($_FILES['app_favicon']['name'], PATHINFO_EXTENSION);
    $fileName = 'core_favicon_' . time() . '.' . $ext;
    $dest = $uploadPath . $fileName;

    move_uploaded_file($_FILES['app_favicon']['tmp_name'], $dest);

    $settingsService->set('app_favicon', $fileName);
}


flash('success', 'Configurações salvas com sucesso.');
    redirect('/public/admin/settings/index.php');
