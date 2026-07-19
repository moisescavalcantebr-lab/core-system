<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/quick_links.php';

requireAdmin();

$settingsService = new SettingsService($pdo);
$links = coreQuickLinksDecode($settingsService->get('quick_links'), $config);

while (count($links) < 8) {
    $links[] = [
        'label' => '',
        'url' => '',
        'description' => '',
        'category' => '',
        'enabled' => true,
    ];
}

$enabledLinks = coreQuickLinksEnabled($links);
$title = 'Acessos Rápidos';

ob_start();
?>

<div class="c-page c-quick-links-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Acessos Rápidos</h1>
            <p class="c-page-subtitle">Links importantes do sistema, servidor e serviços externos</p>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <div class="c-quick-links-grid">
            <?php foreach ($enabledLinks as $link): ?>
                <a class="c-quick-link-card" href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                    <span><?= htmlspecialchars($link['category'] ?: 'Link') ?></span>
                    <strong><?= htmlspecialchars($link['label']) ?></strong>
                    <small><?= htmlspecialchars($link['description'] ?: $link['url']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="post" action="/app/actions/quick_links/save.php" class="c-card c-quick-links-form">
            <?= csrf_field(); ?>

            <h3>Gerenciar links</h3>

            <div class="c-table-wrapper">
                <table class="c-table c-quick-links-table">
                    <thead>
                        <tr>
                            <th>Ativo</th>
                            <th>Nome</th>
                            <th>URL</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($links as $index => $link): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="enabled[<?= $index ?>]" value="1" <?= !empty($link['enabled']) ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <input class="c-input" name="label[<?= $index ?>]" value="<?= htmlspecialchars($link['label']) ?>">
                                </td>
                                <td>
                                    <input class="c-input" name="url[<?= $index ?>]" value="<?= htmlspecialchars($link['url']) ?>" placeholder="https://...">
                                </td>
                                <td>
                                    <input class="c-input" name="category[<?= $index ?>]" value="<?= htmlspecialchars($link['category']) ?>">
                                </td>
                                <td>
                                    <input class="c-input" name="description[<?= $index ?>]" value="<?= htmlspecialchars($link['description']) ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button class="c-btn-secondary">Salvar acessos</button>
        </form>
    </div>
</div>

<style>
.c-quick-links-page .c-page-content {
    gap: 14px;
}

.c-quick-links-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px;
}

.c-quick-link-card {
    display: grid;
    gap: 6px;
    min-height: 96px;
    padding: 14px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-primary);
    text-decoration: none;
}

.c-quick-link-card:hover {
    border-color: var(--primary-color);
    background: var(--bg-hover);
}

.c-quick-link-card span,
.c-quick-link-card small {
    color: var(--text-secondary);
}

.c-quick-link-card strong {
    font-size: 15px;
}

.c-quick-links-form {
    display: grid;
    gap: 12px;
}

.c-quick-links-table th:first-child,
.c-quick-links-table td:first-child {
    width: 54px;
    text-align: center;
}

.c-quick-links-table input[type="checkbox"] {
    width: 16px;
    height: 16px;
}

.c-quick-links-table .c-input {
    min-width: 150px;
}
</style>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Informações</h3>
    <p>Use esta página para centralizar links de operação do core.</p>
    <p>Evite guardar senhas nos campos de descrição.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
