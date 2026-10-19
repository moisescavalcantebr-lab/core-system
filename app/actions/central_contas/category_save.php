<?php
declare(strict_types=1);
require __DIR__ . '/../../bootstrap/bootstrap.php';
$owner = mailCenterRequireOwner($pdo, $config);
mailCenterRequirePost();
try {
    (new MailAccountService($pdo, $owner))->saveCategory($_POST);
    flash('success', 'Função do e-mail salva.');
    redirect('/web/admin/central_contas/accounts.php');
} catch (Throwable $error) { mailCenterActionError($error); }
