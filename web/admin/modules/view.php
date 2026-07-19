<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

$moduleSlug = preg_replace('/[^a-z0-9\-_]/', '', strtolower((string)($_GET['module'] ?? '')));
$modulePath = ROOT_PATH . '/modules/' . $moduleSlug;
$manifestPath = $modulePath . '/module.json';

if ($moduleSlug === '' || !is_file($manifestPath)) {
    die('Módulo inválido.');
}

$manifest = json_decode((string)file_get_contents($manifestPath), true);

if (!is_array($manifest)) {
    die('Manifesto inválido.');
}

$installedBases = [];

if (is_dir(BASES_PATH)) {
    foreach (scandir(BASES_PATH) as $baseSlug) {
        if ($baseSlug === '.' || $baseSlug === '..' || $baseSlug === 'base') {
            continue;
        }

        if (is_dir(BASES_PATH . '/' . $baseSlug . '/modules/' . $moduleSlug)) {
            $installedBases[] = $baseSlug;
        }
    }
}

$tables = $manifest['database']['tables'] ?? [];
$copies = $manifest['copy'] ?? [];
$kind = (string)($manifest['kind'] ?? 'main');
$recommendedBases = array_values(array_filter(array_map('strval', $manifest['recommended_bases'] ?? [])));
$attachTo = array_values(array_filter(array_map('strval', $manifest['attach_to'] ?? [])));
$segments = array_values(array_filter(array_map('strval', $manifest['segments'] ?? [])));
$tags = array_values(array_filter(array_map('strval', $manifest['tags'] ?? [])));
$menu = is_array($manifest['menu'] ?? null) ? $manifest['menu'] : [];

function moduleDetailListLabel(array $items, string $empty = '-'): string
{
    return empty($items) ? $empty : implode(', ', $items);
}

$title = 'Detalhes do Módulo';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title"><?= htmlspecialchars($manifest['label'] ?? $moduleSlug) ?></h1>
            <p class="c-page-subtitle"><?= htmlspecialchars($manifest['description'] ?? '') ?></p>
        </div>

        <a class="c-btn-secondary" href="/web/admin/modules/index.php">
            Voltar
        </a>
    </div>

    <div class="c-page-content">

        <div class="c-card">
            <h3>Informações</h3>

            <div class="c-settings-grid">
                <div class="c-form-group">
                    <label>Slug</label>
                    <div><?= htmlspecialchars($moduleSlug) ?></div>
                </div>

                <div class="c-form-group">
                    <label>Versão</label>
                    <div><?= htmlspecialchars($manifest['version'] ?? '-') ?></div>
                </div>

                <div class="c-form-group">
                    <label>Status</label>
                    <span class="c-badge c-badge--warning">
                        <?= htmlspecialchars($manifest['status'] ?? 'draft') ?>
                    </span>
                </div>

                <div class="c-form-group">
                    <label>Tipo</label>
                    <span class="c-badge c-badge--<?= $kind === 'addon' ? 'info' : 'neutral' ?>">
                        <?= $kind === 'addon' ? 'Addon' : 'Principal' ?>
                    </span>
                </div>
            </div>

            <div class="c-form-group c-module-description">
                <label>Descrição</label>
                <p><?= htmlspecialchars($manifest['description'] ?? '-') ?></p>
            </div>
        </div>

        <div class="c-card">
            <h3>Recomendação e vínculos</h3>

            <div class="c-settings-grid">
                <div class="c-form-group">
                    <label>Recomendado para bases</label>
                    <p><?= htmlspecialchars(moduleDetailListLabel($recommendedBases, 'Oculto')) ?></p>
                </div>

                <div class="c-form-group">
                    <label>Acoplável a módulos</label>
                    <p><?= htmlspecialchars(moduleDetailListLabel($attachTo, $kind === 'addon' ? 'Nao definido' : 'Nao se aplica')) ?></p>
                </div>

                <div class="c-form-group">
                    <label>Segmentos</label>
                    <p><?= htmlspecialchars(moduleDetailListLabel($segments)) ?></p>
                </div>

                <div class="c-form-group">
                    <label>Menu no projeto</label>
                    <p><?= !empty($menu) ? htmlspecialchars((string)($menu['label'] ?? 'Sim')) : 'Nao' ?></p>
                </div>
            </div>

            <?php if (!empty($tags)): ?>
                <div class="c-module-tags">
                    <?php foreach ($tags as $tag): ?>
                        <span class="c-badge c-badge--neutral"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Tabelas</h3>

            <?php if (empty($tables)): ?>
                <p>Nenhuma tabela declarada.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$table) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="c-card">
            <h3>Cópias</h3>

            <?php if (empty($copies)): ?>
                <p>Nenhum destino declarado.</p>
            <?php else: ?>
                <div class="c-table-wrapper">
                    <table class="c-table">
                        <thead>
                            <tr>
                                <th>Origem</th>
                                <th>Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($copies as $copy): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($copy['from'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($copy['to'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<style>
.c-module-description {
    margin-top: 16px;
}

.c-module-description p {
    margin: 6px 0 0;
    max-width: 760px;
}

.c-module-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 12px;
}
</style>

<?php
$content = ob_get_clean();

$rightSidebarEnabled = true;
$rightSidebarContent = '
<div class="c-card">
    <h3>Uso</h3>
    <p><strong>Bases instaladas:</strong> '.count($installedBases).'</p>
    <p>'.(empty($installedBases) ? 'Nenhuma base usando este módulo.' : htmlspecialchars(implode(', ', $installedBases))).'</p>
</div>

<br>

<div class="c-card">
    <h3>Origem</h3>
    <p>Este é o módulo original do core. Bases recebem uma cópia adaptável.</p>
</div>
';

require APP_PATH . '/views/layout_admin.php';
