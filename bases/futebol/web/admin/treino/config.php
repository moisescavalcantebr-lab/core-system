<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function trainingConfigDefaultSlots(): array
{
    return [
        'GO' => 1,
        'ZC1' => 1,
        'ZC2' => 1,
        'LE' => 1,
        'LD' => 1,
        'VOL1' => 1,
        'MAT1' => 1,
        'PTE' => 1,
        'PTD' => 1,
        'CA1' => 1,
        'CA2' => 1,
    ];
}

function trainingConfigEnsureSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            custom_slots_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

trainingConfigEnsureSchema($pdo);

$team = $pdo->query("SELECT custom_slots_json FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$settings = $pdo->query("SELECT custom_slots_json FROM training_settings WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$settings) {
    $teamSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
    $teamSlots = is_array($teamSlots) && $teamSlots ? $teamSlots : trainingConfigDefaultSlots();
    $stmt = $pdo->prepare("INSERT INTO training_settings (id, custom_slots_json) VALUES (1, ?)");
    $stmt->execute([json_encode($teamSlots, JSON_UNESCAPED_UNICODE)]);
    $settings = ['custom_slots_json' => json_encode($teamSlots, JSON_UNESCAPED_UNICODE)];
}

$customSlots = json_decode((string)($settings['custom_slots_json'] ?? ''), true);
$customSlots = is_array($customSlots) ? $customSlots : trainingConfigDefaultSlots();

$labels = [
    'GO' => ['group' => 'Goleiro', 'label' => 'Goleiro', 'field' => 'GO', 'x' => 50, 'y' => 88],
    'ZC1' => ['group' => 'Zagueiros', 'label' => 'Zagueiro 1', 'field' => 'ZC', 'x' => 38, 'y' => 72],
    'ZC2' => ['group' => 'Zagueiros', 'label' => 'Zagueiro 2', 'field' => 'ZC', 'x' => 62, 'y' => 72],
    'LE' => ['group' => 'Laterais', 'label' => 'Lateral esquerdo', 'field' => 'LE', 'x' => 18, 'y' => 62],
    'LD' => ['group' => 'Laterais', 'label' => 'Lateral direito', 'field' => 'LD', 'x' => 82, 'y' => 62],
    'VOL1' => ['group' => 'Volantes', 'label' => 'Volante 1', 'field' => 'VOL', 'x' => 42, 'y' => 55],
    'VOL2' => ['group' => 'Volantes', 'label' => 'Volante 2', 'field' => 'VOL', 'x' => 58, 'y' => 55],
    'MAT1' => ['group' => 'Meias', 'label' => 'Meia atacante 1', 'field' => 'MAT', 'x' => 38, 'y' => 40],
    'MAT2' => ['group' => 'Meias', 'label' => 'Meia atacante 2', 'field' => 'MAT', 'x' => 62, 'y' => 40],
    'PTE' => ['group' => 'Pontas', 'label' => 'Ponta esquerda', 'field' => 'PTE', 'x' => 24, 'y' => 24],
    'PTD' => ['group' => 'Pontas', 'label' => 'Ponta direita', 'field' => 'PTD', 'x' => 76, 'y' => 24],
    'CA1' => ['group' => 'Atacantes', 'label' => 'Atacante 1', 'field' => 'CA', 'x' => 38, 'y' => 20],
    'CA2' => ['group' => 'Atacantes', 'label' => 'Atacante 2', 'field' => 'CA', 'x' => 62, 'y' => 20],
];

$groups = [];
foreach ($labels as $code => $info) {
    $groups[$info['group']][] = $code;
}

$title = 'Posições do treino';
ob_start();
?>

<div class="c-page c-training-config-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Posições do treino</h1>
            <p class="c-page-subtitle">Ajuste o campo do treino sem alterar o Meu Time.</p>
        </div>
        <a href="<?= PROJECT_URL ?>/admin/treino/index.php" class="c-btn-secondary">Voltar</a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/treino/config_save.php" method="POST" class="c-card c-training-config-card">
            <?= csrf_field(); ?>

            <div class="c-training-config-head">
                <div>
                    <h2>Campo do treino</h2>
                    <p class="c-text-muted">Marque as posições usadas nos dois campos do treino.</p>
                </div>
                <span id="trainingSlotsCounter">0/11 no campo</span>
            </div>

            <div class="c-training-config-layout">
                <div class="c-training-slots-grid">
                    <?php foreach ($groups as $groupName => $codes): ?>
                        <section class="c-training-slot-group">
                            <strong><?= htmlspecialchars($groupName) ?></strong>
                            <div>
                                <?php foreach ($codes as $code): ?>
                                    <?php $info = $labels[$code]; ?>
                                    <label class="c-training-slot-option">
                                        <input type="checkbox" name="custom_slots[<?= htmlspecialchars($code) ?>]" value="1" data-code="<?= htmlspecialchars($code) ?>" <?= !empty($customSlots[$code]) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($code) ?></span>
                                        <small><?= htmlspecialchars($info['label']) ?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <aside class="c-training-config-preview">
                    <div class="c-training-preview-field">
                        <div class="field-line"></div>
                        <div class="field-circle"></div>
                        <div class="field-box field-box-top"></div>
                        <div class="field-box field-box-bottom"></div>
                        <div class="field-goal field-goal-top"></div>
                        <div class="field-goal field-goal-bottom"></div>
                        <?php foreach ($labels as $code => $info): ?>
                            <span data-slot-preview="<?= htmlspecialchars($code) ?>" data-default-x="<?= (int)$info['x'] ?>" style="left:<?= (int)$info['x'] ?>%;top:<?= (int)$info['y'] ?>%;">
                                <?= htmlspecialchars($info['field']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <h3>Prévia do campo</h3>
                </aside>
            </div>

            <button class="c-btn-secondary">Salvar posições</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputs = Array.from(document.querySelectorAll('[name^="custom_slots"]'));
    const counter = document.getElementById('trainingSlotsCounter');

    const sync = function () {
        let total = 0;
        inputs.forEach(function (input) {
            if (input.checked) total++;
        });

        inputs.forEach(function (input) {
            const preview = document.querySelector('[data-slot-preview="' + input.dataset.code + '"]');
            if (!preview) return;

            preview.classList.toggle('is-active', input.checked);
            preview.style.left = preview.dataset.defaultX + '%';
        });

        const balanceGroup = function (codes, centerX, leftX, rightX) {
            const active = codes
                .map(function (code) { return document.querySelector('[data-slot-preview="' + code + '"]'); })
                .filter(function (preview) { return preview && preview.classList.contains('is-active'); });

            if (active.length === 1) {
                active[0].style.left = centerX + '%';
                return;
            }

            if (active.length >= 2) {
                active[0].style.left = leftX + '%';
                active[1].style.left = rightX + '%';
            }
        };

        balanceGroup(['ZC1', 'ZC2'], 50, 38, 62);
        balanceGroup(['VOL1', 'VOL2'], 50, 42, 58);
        balanceGroup(['MAT1', 'MAT2'], 50, 38, 62);
        balanceGroup(['CA1', 'CA2'], 50, 38, 62);

        if (counter) counter.textContent = total + '/11 no campo';
    };

    inputs.forEach(function (input) {
        input.addEventListener('change', sync);
    });

    sync();
});
</script>

<style>
.c-training-config-card {
    display: grid;
    gap: 16px;
}

.c-training-config-head {
    display: flex;
    justify-content: space-between;
    gap: 14px;
}

.c-training-config-head h2 {
    margin: 0 0 6px;
}

.c-training-config-head span {
    align-self: start;
    min-width: 110px;
    padding: 8px 10px;
    border: 1px solid rgba(96, 165, 250, .42);
    border-radius: 7px;
    color: #93c5fd;
    text-align: center;
    font-weight: 800;
}

.c-training-config-layout {
    display: grid;
    grid-template-columns: minmax(300px, .9fr) minmax(260px, .55fr);
    gap: 18px;
    align-items: start;
}

.c-training-slots-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.c-training-slot-group {
    border: 1px solid rgba(148, 163, 184, .28);
    padding: 8px;
}

.c-training-slot-group strong {
    display: block;
    margin-bottom: 8px;
}

.c-training-slot-group > div {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
}

.c-training-slot-option {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 2px 7px;
    padding: 6px;
    border: 1px solid rgba(148, 163, 184, .24);
}

.c-training-slot-option input {
    grid-row: span 2;
}

.c-training-slot-option span {
    font-weight: 800;
}

.c-training-slot-option small {
    color: #9fb1c7;
}

.c-training-config-preview {
    display: grid;
    justify-items: center;
    gap: 10px;
}

.c-training-config-preview h3 {
    margin: 0;
}

.c-training-preview-field {
    position: relative;
    width: min(100%, 260px);
    aspect-ratio: 3 / 4;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.28);
    background:
        radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.05) 1px, transparent 1px),
        #145c38;
    background-size: 42px 42px;
}

.c-training-preview-field .field-line {
    position: absolute;
    left: 0;
    top: 50%;
    width: 100%;
    border-top: 1px solid rgba(255,255,255,.48);
}

.c-training-preview-field .field-circle {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 34%;
    aspect-ratio: 1;
    transform: translate(-50%, -50%);
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 50%;
}

.c-training-preview-field .field-box {
    position: absolute;
    left: 26%;
    width: 48%;
    height: 15%;
    border: 1px solid rgba(255,255,255,.48);
}

.c-training-preview-field .field-box-top { top: 0; border-top: 0; }
.c-training-preview-field .field-box-bottom { bottom: 0; border-bottom: 0; }

.c-training-preview-field .field-goal {
    position: absolute;
    left: 42%;
    width: 16%;
    height: 4%;
    border: 1px solid rgba(255,255,255,.48);
}

.c-training-preview-field .field-goal-top { top: 0; border-top: 0; }
.c-training-preview-field .field-goal-bottom { bottom: 0; border-bottom: 0; }

.c-training-preview-field span {
    display: none;
    position: absolute;
    place-items: center;
    width: 38px;
    height: 38px;
    transform: translate(-50%, -50%);
    border: 1px dashed rgba(255,255,255,.35);
    border-radius: 50%;
    color: rgba(255,255,255,.7);
    font-size: 10px;
    font-weight: 800;
}

.c-training-preview-field span.is-active {
    display: grid;
}

@media (max-width: 760px) {
    .c-training-config-head,
    .c-training-config-layout {
        grid-template-columns: 1fr;
        display: grid;
    }

    .c-training-slots-grid {
        grid-template-columns: 1fr;
    }

    .c-training-preview-field {
        width: min(100%, calc(100vw - 60px), 340px);
    }
}
</style>

<?php
$content = ob_get_clean();
require __DIR__ . '/../../../app/views/layout_admin.php';
