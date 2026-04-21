<!DOCTYPE html>

<html lang="pt-br">
<head>
<meta charset="UTF-8">
    
<title><?= $title ?? 'Página' ?></title>

<link rel="stylesheet" href="/public/assets/css/core_base.css">
<?= $extraCss ?? '' ?>

<?php
global $coreSettings;
$theme = $coreSettings['theme'] ?? 'dark';
?>

<link rel="stylesheet" href="/public/assets/css/themes/<?= $theme ?>.css?v=<?= time() ?>">
    
</head>

<body class="c-site <?= $bodyClass ?? '' ?>">
<?= $content ?>

<?= $extraJs ?? '' ?>

</body>
</html>