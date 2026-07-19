<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| BUSCAR REQUESTS
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
        l.site_name,
        l.slug,
        l.created_at,
        b.name AS base_name
    FROM leads l
    LEFT JOIN bases b ON b.id = l.base_id
    WHERE l.implementation_status = 'ready'
    ORDER BY l.id DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Solicitações';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Solicitações de Projetos</h1>
            <p class="c-page-subtitle">Leads qualificados aguardando criação</p>
        </div>

        <div class="c-page-actions">
            <a class="c-btn-secondary" href="/web/admin/projects/index.php">
                Voltar para Projetos
            </a>
        </div>

    </div>

    <div class="c-page-content">

        <?php if (empty($requests)): ?>

            <div class="c-card">
                Nenhuma solicitação aguardando criação.
            </div>

        <?php else: ?>

            <div class="c-table-wrapper">

                <table class="c-table">

                    <thead>
                        <tr>
                            <th>Projeto</th>
                            <th>Cliente</th>
                            <th>Contato</th>
                            <th>Base</th>
                            <th>Slug</th>
                            <th>Data</th>
                            <th style="text-align:right;">Ação</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($requests as $request): ?>

                        <tr>

                            <td>
                                <strong><?= htmlspecialchars($request['site_name'] ?: '-') ?></strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($request['name'] ?: '-') ?>
                                <br>
                                <span><?= htmlspecialchars(trim(($request['city'] ?? '') . ' / ' . ($request['state'] ?? ''), ' /')) ?></span>
                            </td>

                            <td>
                                <?= htmlspecialchars($request['email'] ?: '-') ?>
                                <br>
                                <span><?= htmlspecialchars($request['phone'] ?: '-') ?></span>
                            </td>

                            <td><?= htmlspecialchars($request['base_name'] ?: 'Definir no painel') ?></td>

                            <td><?= htmlspecialchars($request['slug'] ?: '-') ?></td>

                            <td><?= $request['created_at'] ?? '-' ?></td>

                            <td style="text-align:right;">
                                <a class="c-btn-secondary"
                                   href="/web/admin/projects/create.php?lead_id=<?= $request['id'] ?>">
                                    Criar Projeto
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;

$rightSidebarContent = '
<div class="c-card">
    <h3>Informações</h3>
    <p>
        Aqui ficam as solicitações enviadas pela landing page.
    </p>
    <p>
        O cliente informa os dados iniciais. A base do projeto é definida pelo administrador na criação.
    </p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
