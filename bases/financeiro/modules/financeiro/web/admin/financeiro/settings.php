<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

requireProjectRole(['ADMIN', 'FINANCE']);

$pixKey = financeSetting($pdo, 'pix_key');
$pixKeyType = financeSetting($pdo, 'pix_key_type', 'random');
$pixReceiverName = financeSetting($pdo, 'pix_receiver_name');
$usesParticipants = financeUsesParticipants($pdo);
$financeUserLabelPlural = financeUserLabel(true);

$title = 'Configurações Financeiras';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configurações Financeiras</h1>
            <p class="c-page-subtitle"><?= $usesParticipants ? 'Recebimentos por Pix vinculados a ' . htmlspecialchars(strtolower($financeUserLabelPlural)) : 'Configuração do financeiro pessoal' ?></p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/financeiro/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/financeiro/settings_save.php" method="POST" class="c-card">
            <?= csrf_field(); ?>

            <?php if (!$usesParticipants): ?>
                <div class="c-card" style="margin:0;">
                    <strong>Projeto financeiro pessoal</strong>
                    <p style="margin:8px 0 0;">Esta base nao usa participantes. O financeiro funciona em modo pessoal e nao exibe solicitacoes de saldo por pessoa vinculada.</p>
                </div>
            <?php else: ?>
                <div class="c-form-grid">
                    <div class="c-form-group">
                        <label>Tipo da chave</label>
                        <select name="pix_key_type" class="c-input">
                            <option value="random" <?= $pixKeyType === 'random' ? 'selected' : '' ?>>Aleatória</option>
                            <option value="email" <?= $pixKeyType === 'email' ? 'selected' : '' ?>>E-mail</option>
                            <option value="phone" <?= $pixKeyType === 'phone' ? 'selected' : '' ?>>Telefone</option>
                            <option value="document" <?= $pixKeyType === 'document' ? 'selected' : '' ?>>CPF/CNPJ</option>
                        </select>
                    </div>

                    <div class="c-form-group">
                        <label>Nome do recebedor</label>
                        <input type="text" name="pix_receiver_name" class="c-input" value="<?= htmlspecialchars($pixReceiverName) ?>">
                    </div>
                </div>

                <div class="c-form-group">
                    <label>Chave Pix</label>
                    <input type="text" name="pix_key" class="c-input" value="<?= htmlspecialchars($pixKey) ?>">
                </div>
            <?php endif; ?>

            <button class="c-btn-secondary">
                Salvar Configurações
            </button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
