<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

tipsRequireAdmin();
tipsEnsureSchema($pdo);

$title = 'Configuracoes - Tips Survivor';
$settings = $pdo->query("SELECT * FROM tips_settings ORDER BY setting_key ASC")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configuracoes</h1>
            <p class="c-page-subtitle">Valores padrao para vidas, pontos e consumo de tokens.</p>
        </div>
        <?= tipsNav('settings') ?>
    </div>

    <div class="c-page-content">
        <div class="c-card">
            <h3>Configuracoes atuais</h3>
            <div class="c-table-wrap">
                <table class="c-table">
                    <thead><tr><th>Chave</th><th>Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($settings as $setting): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$setting['setting_key']) ?></strong></td>
                            <td><?= htmlspecialchars((string)($setting['setting_value'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($settings)): ?>
                        <tr><td colspan="2">Nenhuma configuracao criada ainda.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/styles.php'; ?>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
