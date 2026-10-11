<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php
$authSiteName = function_exists('getSetting') ? getSetting('site_name', $project['name'] ?? 'Projeto') : ($project['name'] ?? 'Projeto');
?>

<title><?= htmlspecialchars($title ?? 'Acesso') ?> - <?= htmlspecialchars((string)$authSiteName) ?></title>

<meta name="theme-color" content="#3b82f6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string)$authSiteName) ?>">

<link rel="manifest" href="<?= PROJECT_URL ?>/manifest.php">
<link rel="apple-touch-icon" href="<?= PROJECT_URL ?>/pwa-icon.php?size=192">
<link rel="stylesheet" href="<?= PROJECT_URL ?>/assets/css/core_auth.css">

<?php
$theme = getSetting('theme', 'dark');
?>

<link rel="stylesheet" href="<?= PROJECT_URL ?>/assets/css/themes/<?= htmlspecialchars($theme) ?>.css">

</head>

<body class="c-auth theme-<?= htmlspecialchars($theme) ?>">
<?= $content ?>

<script src="<?= PROJECT_URL ?>/assets/js/pwa-install.js"></script>
</body>
</html>
