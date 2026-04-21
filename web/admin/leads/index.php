<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| BUSCAR LEADS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
SELECT
id,
name,
email,
phone,
state,
city,
created_at
FROM leads
ORDER BY id DESC
");

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title = 'Leads';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Leads</h1>
            <p class="c-page-subtitle">Contatos capturados no sistema</p>
        </div>

    </div>

    <div class="c-page-content">

        <div class="c-card">

            <?php if(empty($leads)): ?>

                <p>Nenhum lead registrado ainda.</p>

            <?php else: ?>

                <div class="c-table-wrapper">

                    <table class="c-table">

                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Estado</th>
                            <th>Cidade</th>
                            <th>Data</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach($leads as $lead): ?>

                            <tr>

                                <td><?= $lead['id'] ?></td>

                                <td>
                                    <strong><?= htmlspecialchars($lead['name']) ?></strong>
                                </td>

                                <td><?= htmlspecialchars($lead['email']) ?></td>

                                <td><?= htmlspecialchars($lead['phone']) ?></td>

                                <td><?= htmlspecialchars($lead['state']) ?></td>

                                <td><?= htmlspecialchars($lead['city']) ?></td>

                                <td><?= $lead['created_at'] ?? '-' ?></td>

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

$rightSidebarContent = '

<div class="c-card">

<h3>Leads</h3>

<p>
Aqui ficam registrados todos os contatos realizados
através do formulário da landing page.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';