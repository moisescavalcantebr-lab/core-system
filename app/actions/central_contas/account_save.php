<?php
declare(strict_types=1);
require __DIR__ . '/../../bootstrap/bootstrap.php';
$owner = mailCenterRequireOwner($pdo, $config);
mailCenterRequirePost();
try {
    (new MailAccountService($pdo, $owner))->save($_POST);
    flash('success', 'Conta salva. A conexão com o provedor ainda não está disponível.');
    redirect('/web/admin/central_contas/accounts.php');
} catch (Throwable $error) { mailCenterActionError($error); }
