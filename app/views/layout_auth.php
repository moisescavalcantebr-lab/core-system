<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? 'Login' ?></title>

<meta name="theme-color" content="#3b82f6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars((string)($coreSettings['app_name'] ?? 'CORE')) ?>">

<link rel="manifest" href="/web/manifest.php">
<link rel="apple-touch-icon" href="/web/pwa-icon.php?size=192">
<link rel="stylesheet" href="/web/assets/css/core_base.css">
<link rel="stylesheet" href="/web/assets/css/themes/dark.css">
<link rel="stylesheet" href="/web/assets/css/core_auth.css">
<?php if (!empty($coreSettings['app_favicon'])): ?>
<link rel="icon" href="/web/assets/uploads/<?= htmlspecialchars($coreSettings['app_favicon']) ?>">
<?php endif; ?>
</head>
<body class="c-auth">

<?= $content ?>

<script src="/web/assets/js/pwa-install.js"></script>
</body>
</html>
