<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$bases = $pdo->query("
    SELECT id, slug, name, description, showcase_title, showcase_summary, showcase_cover_image,
           showcase_banner_image, showcase_featured, showcase_order, showcase_status
    FROM bases
    WHERE slug != 'base'
    ORDER BY showcase_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Vitrine';
ob_start();
?>

<div class="c-page c-vitrines-admin">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Vitrine</h1>
            <p class="c-page-subtitle">Organize como as bases aparecem na loja pública</p>
        </div>

        <a href="/web/admin/bases/index.php" class="c-btn-secondary">Bases</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-table-wrapper c-vitrines-table-wrapper">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Base</th>
                        <th>Status</th>
                        <th>Destaque</th>
                        <th>Ordem</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$bases): ?>
                        <tr>
                            <td colspan="5">Nenhuma base cadastrada.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($bases as $base): ?>
                        <tr>
                            <td data-label="Base">
                                <strong><?= htmlspecialchars((string)($base['showcase_title'] ?: $base['name'])) ?></strong><br>
                                <small><?= htmlspecialchars((string)$base['slug']) ?></small>
                            </td>
                            <td data-label="Status">
                                <span class="c-badge <?= (int)$base['showcase_status'] === 1 ? 'c-badge--success' : 'c-badge--warning' ?>">
                                    <?= (int)$base['showcase_status'] === 1 ? 'Visível' : 'Oculta' ?>
                                </span>
                            </td>
                            <td data-label="Destaque"><?= (int)$base['showcase_featured'] === 1 ? 'Sim' : 'Não' ?></td>
                            <td data-label="Ordem"><?= (int)$base['showcase_order'] ?></td>
                            <td data-label="Ações" class="c-vitrine-row-actions">
                                <a href="/web/admin/bases/vitrine.php?id=<?= (int)$base['id'] ?>" class="c-btn-secondary">Editar vitrine</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media (max-width: 760px) {
    .c-vitrines-admin .c-page-header {
        align-items: flex-start;
    }

    .c-vitrines-table-wrapper {
        overflow: visible;
        background: transparent;
        box-shadow: none;
    }

    .c-vitrines-table-wrapper table,
    .c-vitrines-table-wrapper thead,
    .c-vitrines-table-wrapper tbody,
    .c-vitrines-table-wrapper tr,
    .c-vitrines-table-wrapper td {
        display: block;
        width: 100%;
    }

    .c-vitrines-table-wrapper thead {
        display: none;
    }

    .c-vitrines-table-wrapper tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px 12px;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid var(--border-color);
        background: var(--bg-card);
    }

    .c-vitrines-table-wrapper td {
        padding: 0;
        border: 0;
        min-width: 0;
    }

    .c-vitrines-table-wrapper td::before {
        content: attr(data-label);
        display: block;
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .c-vitrine-row-actions {
        grid-column: 1 / -1;
    }

    .c-vitrine-row-actions .c-btn-secondary {
        width: 100%;
        justify-content: center;
        min-height: 40px;
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
