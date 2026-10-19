<?php
declare(strict_types=1);
require __DIR__ . '/../../bootstrap/bootstrap.php';
$owner = mailCenterRequireOwner($pdo, $config);
mailCenterRequirePost();
try {
    if (array_diff(array_keys($_POST), ['_csrf','id','revision','is_active']) || !in_array($_POST['is_active'] ?? null, ['0','1'], true)) {
        throw new InvalidArgumentException('Alteração inválida.');
    }
    (new MailAccountService($pdo, $owner))->setActive(mailCenterId($_POST['id'] ?? null), mailCenterId($_POST['revision'] ?? null), $_POST['is_active'] === '1');
    flash('success', 'Situação do cadastro atualizada.');
    redirect('/web/admin/central_contas/accounts.php');
} catch (Throwable $error) { mailCenterActionError($error); }
