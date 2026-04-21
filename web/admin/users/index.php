<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();
$statusFilter = $_GET['status'] ?? '';
/*
|--------------------------------------------------------------------------
| BUSCAR USUÁRIOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
SELECT
id,
name,
email,
role,
status,
created_at
FROM core_users
ORDER BY id DESC
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT * FROM core_users";

if ($statusFilter !== '') {
    $sql .= " WHERE status = :status";
    $stmt = $pdo->prepare($sql . " ORDER BY id DESC");
    $stmt->execute(['status' => $statusFilter]);
} else {
    $stmt = $pdo->query($sql . " ORDER BY id DESC");
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Usuários';

ob_start();
?>
<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Usuários</h1>
            <p class="c-page-subtitle">Gerenciamento de acesso ao sistema</p>
        </div>

    </div>
<div class="c-page-actions">

    <a href="?status=" class="c-btn-secondary">Todos</a>
    <a href="?status=0" class="c-btn-secondary">Pendentes</a>
    <a href="?status=1" class="c-btn-secondary">Ativos</a>
    <a href="?status=2" class="c-btn-secondary">Bloqueados</a>

</div>

    <div class="c-page-content">

        <div class="c-card">

            <?php if(empty($users)): ?>

                <p>Nenhum usuário cadastrado.</p>

            <?php else: ?>

                <div class="c-table-wrapper">

                    <table class="c-table">

                        <thead>
                        <tr class="<?= $user['status'] == 0 ? 'c-row-highlight' : '' ?>">
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach($users as $user): ?>

                            <?php
                            $statusLabel = match((int)$user['status']) {
    0 => 'Pendente',
    1 => 'Ativo',
    2 => 'Bloqueado',
};

                            $roleClass = match($user['role']) {
                                'SUPER_ADMIN' => 'c-badge--danger',
                                'ADMIN'       => 'c-badge--info',
                                default       => 'c-badge--neutral'
                            };
                            ?>

                            <tr>

                                <td><?= $user['id'] ?></td>

                                <td>
                                    <strong><?= htmlspecialchars($user['name']) ?></strong>
                                </td>

                                <td><?= htmlspecialchars($user['email']) ?></td>

                                <td>
                                    <span class="c-badge <?= $roleClass ?>">
                                        <?= $user['role'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="c-badge <?= $statusClass ?>">
                                        <?= $user['status'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>

                                <td>
                                    <?= $user['created_at'] ?? '-' ?>
                                </td>

<td style="text-align:right;">

<?php if ($_SESSION['core_user']['role'] === 'SUPER_ADMIN'): ?>

    <?php if ($user['status'] == 0): ?>

        <a href="/app/actions/users/toggle_status.php?id=<?= $user['id'] ?>"
           class="c-btn-secondary btn-sm">
            Aprovar
        </a>

    <?php endif; ?>

    <?php if ($user['role'] === 'USER' && $user['status'] == 1): ?>

        <a href="/app/actions/users/promote.php?id=<?= $user['id'] ?>"
           class="c-btn-secondary btn-sm">
            Tornar Admin
        </a>

    <?php endif; ?>

    <a href="/app/actions/users/toggle_status.php?id=<?= $user['id'] ?>"
       class="c-btn-secondary btn-sm">

        <?= $user['status'] == 1 ? 'Bloquear' : 'Ativar' ?>

    </a>

<?php else: ?>

    <span class="c-badge c-badge--neutral">—</span>

<?php endif; ?>

</td>
							
							
							</tr>

                        <?php endforeach ?>

                        </tbody>

                    </table>

                </div>

            <?php endif ?>

        </div>

    </div>

</div>

<?php
$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$totalUsers = count($users);
$totalAdmins = count(array_filter($users, fn($u) => $u['role'] === 'ADMIN'));

$rightSidebarContent = '

<div class="c-card">

<h3>Resumo</h3>

<p>Total: '.$totalUsers.'</p>
<p>Admins: '.$totalAdmins.'</p>

</div>

<br>

<div class="c-card">

<h3>Controle</h3>

<p>
Usuários são criados como <strong>USER</strong>.<br>
A liberação para <strong>ADMIN</strong> é feita manualmente.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';