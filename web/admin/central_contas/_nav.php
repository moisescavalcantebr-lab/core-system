<?php if(!isset($mailOwner)){http_response_code(404);exit;} ?>
<link rel="stylesheet" href="/web/assets/css/admin/central_contas.css?v=<?= (int)filemtime(PUBLIC_PATH.'/assets/css/admin/central_contas.css') ?>">
<nav class="mail-nav" aria-label="Central de E-mail"><a class="c-btn-secondary <?= ($mailTab??'')==='overview'?'is-active':'' ?>" href="/web/admin/central_contas/index.php">Resumo</a><a class="c-btn-secondary <?= ($mailTab??'')==='accounts'?'is-active':'' ?>" href="/web/admin/central_contas/accounts.php">Contas e funções</a><a class="c-btn-secondary" href="/web/admin/central_contas/unified.php">Voltar para Central de E-mail</a></nav>

