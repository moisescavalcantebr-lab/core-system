<!DOCTYPE html>

<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? 'Página' ?></title>

<link rel="stylesheet" href="/web/assets/css/core_base.css">
<?= $extraCss ?? '' ?>

<?php
global $coreSettings;
$theme = $coreSettings['theme'] ?? 'dark';
?>

<link rel="stylesheet" href="/web/assets/css/themes/<?= $theme ?>.css?v=<?= time() ?>">
<?php if (!empty($coreSettings['app_favicon'])): ?>
<link rel="icon" href="/web/assets/uploads/<?= htmlspecialchars($coreSettings['app_favicon']) ?>">
<?php endif; ?>
    
</head>

<body class="c-site <?= $bodyClass ?? '' ?>">
<?= $content ?>

<?= $extraJs ?? '' ?>

</body>
</html>
