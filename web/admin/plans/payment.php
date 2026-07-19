<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

function coreSetting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare("SELECT setting_value FROM core_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string)$value;
}

$pixKey = coreSetting($pdo, 'upgrade_pix_key');
$pixType = coreSetting($pdo, 'upgrade_pix_type');
$pixHolder = coreSetting($pdo, 'upgrade_pix_holder');
$pixNotes = coreSetting($pdo, 'upgrade_pix_notes');

$title = 'Pix da Carteira';
ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Pix da Carteira</h1>
            <p class="c-page-subtitle">Chave usada nas solicitações de crédito da carteira dos projetos</p>
        </div>

        <div class="c-page-actions">
            <a href="/web/admin/financeiro/saldo.php" class="c-btn-secondary">Carteira</a>
            <a href="/web/admin/financeiro/index.php" class="c-btn-secondary">Financeiro</a>
            <a href="/web/admin/plans/index.php" class="c-btn-secondary">Planos</a>
        </div>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form method="post" action="/app/actions/plans/payment_save.php" class="c-card">
            <?= csrf_field() ?>

            <div class="c-settings-grid">
                <div class="c-form-group">
                    <label>Tipo de chave</label>
                    <select class="c-input" name="upgrade_pix_type">
                        <?php foreach ([
                            'phone' => 'Telefone',
                            'email' => 'Email',
                            'cpf' => 'CPF',
                            'cnpj' => 'CNPJ',
                            'random' => 'Chave aleatoria',
                        ] as $typeValue => $typeLabel): ?>
                            <option value="<?= htmlspecialchars($typeValue) ?>" <?= $pixType === $typeValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Chave Pix</label>
                    <input class="c-input" name="upgrade_pix_key" value="<?= htmlspecialchars($pixKey) ?>" placeholder="CPF, CNPJ, email, telefone ou chave aleatoria">
                </div>

                <div class="c-form-group">
                    <label>Recebedor</label>
                    <input class="c-input" name="upgrade_pix_holder" value="<?= htmlspecialchars($pixHolder) ?>" placeholder="Nome que aparece no Pix">
                </div>
            </div>

            <div class="c-form-group">
                <label>Observações</label>
                <textarea class="c-input" name="upgrade_pix_notes" rows="4" placeholder="Mensagem opcional para o cliente"><?= htmlspecialchars($pixNotes) ?></textarea>
            </div>

            <button class="c-btn-secondary">Salvar Pix</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
