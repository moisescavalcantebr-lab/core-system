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
l.id,
l.name,
l.email,
l.phone,
l.state,
l.city,
l.base_id,
l.referer,
l.content_campaign_key,
l.content_source,
l.created_at,
b.name AS base_name
FROM leads l
LEFT JOIN bases b ON b.id = l.base_id
ORDER BY l.id DESC
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
                            <th>Origem</th>
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
                                    <strong><?= htmlspecialchars((string)($lead['name'] ?? 'Sem nome')) ?></strong>
                                </td>

                                <td><?= htmlspecialchars((string)($lead['email'] ?? '-')) ?></td>

                                <td><?= htmlspecialchars((string)($lead['phone'] ?? '-')) ?></td>

                                <td>
                                    <?php
                                    $origin = trim((string)($lead['base_name'] ?? ''));
                                    if ($origin === '') {
                                        $origin = trim((string)($lead['content_source'] ?? ''));
                                    }
                                    if ($origin === '') {
                                        $origin = trim((string)($lead['content_campaign_key'] ?? ''));
                                    }
                                    if ($origin === '') {
                                        $origin = !empty($lead['referer']) ? 'Pagina externa' : 'Sem base';
                                    }
                                    ?>
                                    <?= htmlspecialchars($origin) ?>
                                </td>

                                <td><?= htmlspecialchars((string)($lead['state'] ?? '-')) ?></td>

                                <td><?= htmlspecialchars((string)($lead['city'] ?? '-')) ?></td>

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
