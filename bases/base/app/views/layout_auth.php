<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= $title ?? 'Acesso' ?></title>

<link rel="stylesheet" href="<?= PROJECT_URL ?>/assets/css/core_auth.css">

<?php
$theme = getSetting('theme', 'dark');
?>

<link rel="stylesheet" href="<?= PROJECT_URL ?>/assets/css/themes/<?= htmlspecialchars($theme) ?>.css">

</head>

<body class="c-auth theme-<?= htmlspecialchars($theme) ?>">

<div class="c-auth-layout">
    <div class="c-auth-wrapper">
        <?= $content ?>
    </div>
</div>

</body>
</html>