<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';

requireProjectAdmin();

function teamEnsureBaseSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS team_profile (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL DEFAULT 'Meu Time',
            short_name VARCHAR(80) NULL,
            team_type ENUM('futsal','society','beach','field','other','custom') DEFAULT 'field',
            starters_count INT DEFAULT 11,
            reserves_count INT DEFAULT 7,
            custom_slots_json JSON NULL,
            city VARCHAR(100) NULL,
            venue VARCHAR(150) NULL,
            primary_color VARCHAR(20) NULL,
            secondary_color VARCHAR(20) NULL,
            responsible_name VARCHAR(150) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("INSERT INTO team_profile (id, name) VALUES (1, 'Meu Time') ON DUPLICATE KEY UPDATE id = id");
}

function teamEnsureCustomSchema(PDO $pdo): void
{
    teamEnsureBaseSchema($pdo);

    $columns = $pdo->query("SHOW COLUMNS FROM team_profile")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('custom_slots_json', $columns, true)) {
        $pdo->exec("ALTER TABLE team_profile ADD COLUMN custom_slots_json JSON NULL AFTER reserves_count");
    }

    $pdo->exec("ALTER TABLE team_profile MODIFY COLUMN team_type ENUM('futsal','society','beach','field','other','custom') DEFAULT 'field'");
}

teamEnsureCustomSchema($pdo);

$pdo->exec("INSERT INTO team_profile (id, name) VALUES (1, 'Meu Time') ON DUPLICATE KEY UPDATE id = id");
$team = $pdo->query("SELECT * FROM team_profile WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$currentType = 'custom';
$currentSetup = ['label' => 'Personalizado', 'starters' => 11, 'reserves' => 9];
$currentStarters = (int)($team['starters_count'] ?? $currentSetup['starters']);
$currentReserves = (int)($team['reserves_count'] ?? $currentSetup['reserves']);
$currentStarters = max(1, min(11, $currentStarters));
$currentReserves = max(0, min(9, $currentReserves));
$friendlyCardsEnabled = function_exists('getSetting')
    ? getSetting('friendly_cards_enabled', '1') !== '0'
    : true;
$customSlots = json_decode((string)($team['custom_slots_json'] ?? ''), true);
$customSlots = is_array($customSlots) ? $customSlots : [];
if (!$customSlots) {
    $customSlots = [
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
if (!empty($customSlots['ZC']) && empty($customSlots['ZC1']) && empty($customSlots['ZC2'])) {
    $customSlots['ZC1'] = 1;
    if ((int)$customSlots['ZC'] > 1) {
        $customSlots['ZC2'] = 1;
    }
}
if (!empty($customSlots['MAT']) && empty($customSlots['MAT1']) && empty($customSlots['MAT2'])) {
    $customSlots['MAT1'] = 1;
    if ((int)$customSlots['MAT'] > 1) {
        $customSlots['MAT2'] = 1;
    }
}
if (!empty($customSlots['VOL']) && empty($customSlots['VOL1']) && empty($customSlots['VOL2'])) {
    $customSlots['VOL1'] = 1;
    if ((int)$customSlots['VOL'] > 1) {
        $customSlots['VOL2'] = 1;
    }
}
if (!empty($customSlots['CA']) && empty($customSlots['CA1']) && empty($customSlots['CA2'])) {
    $customSlots['CA1'] = 1;
    if ((int)$customSlots['CA'] > 1) {
        $customSlots['CA2'] = 1;
    }
}
$customLabels = [
    'GO' => ['label' => 'Goleiro', 'field_label' => 'GO', 'x' => 50, 'y' => 88],
    'ZC1' => ['label' => 'Zagueiro 1', 'field_label' => 'ZC', 'x' => 38, 'y' => 72],
    'ZC2' => ['label' => 'Zagueiro 2', 'field_label' => 'ZC', 'x' => 62, 'y' => 72],
    'LE' => ['label' => 'Lateral esquerdo', 'field_label' => 'LE', 'x' => 18, 'y' => 62],
    'LD' => ['label' => 'Lateral direito', 'field_label' => 'LD', 'x' => 82, 'y' => 62],
    'VOL1' => ['label' => 'Volante 1', 'field_label' => 'VOL', 'x' => 42, 'y' => 55],
    'VOL2' => ['label' => 'Volante 2', 'field_label' => 'VOL', 'x' => 58, 'y' => 55],
    'MAT1' => ['label' => 'Meia atacante 1', 'field_label' => 'MAT', 'x' => 38, 'y' => 40],
    'MAT2' => ['label' => 'Meia atacante 2', 'field_label' => 'MAT', 'x' => 62, 'y' => 40],
    'PTE' => ['label' => 'Ponta esquerda', 'field_label' => 'PTE', 'x' => 24, 'y' => 24],
    'PTD' => ['label' => 'Ponta direita', 'field_label' => 'PTD', 'x' => 76, 'y' => 24],
    'CA1' => ['label' => 'Atacante 1', 'field_label' => 'CA', 'x' => 38, 'y' => 20],
    'CA2' => ['label' => 'Atacante 2', 'field_label' => 'CA', 'x' => 62, 'y' => 20],
];
$customGroups = [
    'Goleiro' => ['GO'],
    'Zagueiros' => ['ZC1', 'ZC2'],
    'Laterais' => ['LE', 'LD'],
    'Volantes' => ['VOL1', 'VOL2'],
    'Meias' => ['MAT1', 'MAT2'],
    'Pontas' => ['PTE', 'PTD'],
    'Atacantes' => ['CA1', 'CA2'],
];

$title = 'Configurar Time';

ob_start();
?>

<div class="c-page">
    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Configurar Time</h1>
            <p class="c-page-subtitle">Identidade, modalidade, técnico e composição planejada</p>
        </div>

        <a href="<?= PROJECT_URL ?>/admin/meu_time/index.php" class="c-btn-secondary">
            Voltar
        </a>
    </div>

    <div class="c-page-content">
        <?php flash_show(); ?>

        <form action="<?= PROJECT_URL ?>/admin/meu_time/save.php" method="POST" class="c-card c-team-config-card">
            <?= csrf_field(); ?>

            <input type="hidden" name="team_type" value="custom">

            <div class="c-form-grid c-team-config-main-grid">
                <div class="c-form-group">
                    <label>Nome do time</label>
                    <input type="text" name="name" class="c-input" value="<?= htmlspecialchars($team['name'] ?? '') ?>">
                </div>

                <div class="c-form-group">
                    <label>Apelido/Sigla</label>
                    <input type="text" name="short_name" class="c-input" value="<?= htmlspecialchars($team['short_name'] ?? '') ?>">
                </div>

                <div class="c-form-group">
                    <label>Titulares</label>
                    <select
                        name="starters_count"
                        class="c-input"
                        id="teamStartersInput"
                        required
                    >
                        <?php for ($i = 1; $i <= 11; $i++): ?>
                            <option value="<?= $i ?>" <?= $currentStarters === $i ? 'selected' : '' ?>>
                                <?= $i ?> titular<?= $i > 1 ? 'es' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="c-form-group">
                    <label>Reservas</label>
                    <select
                        name="reserves_count"
                        class="c-input"
                        id="teamReservesInput"
                        required
                    >
                        <?php for ($i = 0; $i <= 9; $i++): ?>
                            <option value="<?= $i ?>" <?= $currentReserves === $i ? 'selected' : '' ?>>
                                <?= $i ?> reserva<?= $i !== 1 ? 's' : '' ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="c-team-config-custom-card" id="customSlotsCard">
                <div class="c-team-config-section-head">
                    <div>
                        <h3>Posições do campo</h3>
                        <p class="c-text-muted">Marque as subposições que devem aparecer no campo.</p>
                    </div>
                    <span id="customSlotsCounter">0/11 no campo</span>
                </div>

                <div class="c-team-config-custom-layout">
                    <div class="c-custom-slots-grid">
                        <?php foreach ($customGroups as $groupName => $codes): ?>
                            <div class="c-custom-slot-group">
                                <strong><?= htmlspecialchars($groupName) ?></strong>
                                <div class="c-custom-slot-group-options">
                                    <?php foreach ($codes as $code): ?>
                                        <?php $slot = $customLabels[$code]; ?>
                                        <label class="c-custom-slot-check">
                                            <input
                                                type="checkbox"
                                                name="custom_slots[<?= htmlspecialchars($code) ?>]"
                                                class="custom-slot-input"
                                                value="1"
                                                data-code="<?= htmlspecialchars($code) ?>"
                                                <?= !empty($customSlots[$code]) ? 'checked' : '' ?>
                                            >
                                            <span><?= htmlspecialchars($code) ?></span>
                                            <small><?= htmlspecialchars($slot['label']) ?></small>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <aside class="c-team-config-preview">
                        <div class="c-config-field-preview" aria-label="Prévia do campo personalizado">
                            <div class="c-config-field-line c-config-field-center"></div>
                            <div class="c-config-field-circle"></div>
                            <div class="c-config-field-spot c-config-field-spot-center"></div>
                            <div class="c-config-field-spot c-config-field-spot-top"></div>
                            <div class="c-config-field-spot c-config-field-spot-bottom"></div>
                            <div class="c-config-field-box c-config-field-box-top"></div>
                            <div class="c-config-field-box c-config-field-box-bottom"></div>
                            <div class="c-config-field-goal c-config-field-goal-top"></div>
                            <div class="c-config-field-goal c-config-field-goal-bottom"></div>

                            <?php foreach ($customLabels as $code => $slot): ?>
                                <span
                                    class="c-config-slot-preview"
                                    data-slot-preview="<?= htmlspecialchars($code) ?>"
                                    style="left: <?= (int)$slot['x'] ?>%; top: <?= (int)$slot['y'] ?>%;"
                                >
                                    <?= htmlspecialchars($slot['field_label']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <h4>Prévia do campo</h4>
                    </aside>

                    <div class="c-team-config-reserved" aria-hidden="true"></div>
                </div>
            </div>

            <div class="c-form-grid c-team-config-count-grid">
                <div class="c-form-group">
                    <label>Composição planejada</label>
                    <input
                        type="text"
                        class="c-input"
                        id="teamSetupPreview"
                        value="<?= $currentStarters ?> titulares + <?= $currentReserves ?> reservas"
                        readonly
                    >
                </div>
            </div>

            <div class="c-form-grid c-team-config-details-grid">
                <div class="c-form-group">
                    <label>Cartões no amistoso</label>
                    <select name="friendly_cards_enabled" class="c-input">
                        <option value="1" <?= $friendlyCardsEnabled ? 'selected' : '' ?>>Usar cartões</option>
                        <option value="0" <?= !$friendlyCardsEnabled ? 'selected' : '' ?>>Ocultar cartões</option>
                    </select>
                </div>
            </div>

            <div class="c-form-grid c-team-config-details-grid">
                <div class="c-form-group">
                    <label>Cidade/Bairro</label>
                    <input type="text" name="city" class="c-input" value="<?= htmlspecialchars($team['city'] ?? '') ?>">
                </div>

                <div class="c-form-group">
                    <label>Local principal</label>
                    <input type="text" name="venue" class="c-input" value="<?= htmlspecialchars($team['venue'] ?? '') ?>">
                </div>

                <div class="c-form-group">
                    <label>Técnico</label>
                    <input type="text" name="responsible_name" class="c-input" value="<?= htmlspecialchars($team['responsible_name'] ?? '') ?>">
                </div>
            </div>

            <div class="c-team-config-actions">
                <button class="c-btn-secondary">Salvar Time</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const setupPreview = document.getElementById('teamSetupPreview');
    const startersInput = document.getElementById('teamStartersInput');
    const reservesInput = document.getElementById('teamReservesInput');
    const customSlotInputs = document.querySelectorAll('.custom-slot-input');
    const customSlotsCounter = document.getElementById('customSlotsCounter');
    const customSlotPreviews = document.querySelectorAll('[data-slot-preview]');

    if (!setupPreview || !startersInput || !reservesInput) {
        return;
    }

    const syncSetup = function () {
        setupPreview.value = startersInput.value + ' titulares + ' + reservesInput.value + ' reservas';
    };

    const syncCustomStarters = function () {
        const starterLimit = Math.max(1, Math.min(11, parseInt(startersInput.value || '11', 10)));
        let total = 0;
        customSlotInputs.forEach(function (input) {
            if (!input.checked) {
                return;
            }

            total += 1;
            if (total > starterLimit) {
                input.checked = false;
                total -= 1;
            }
        });

        customSlotInputs.forEach(function (input) {
            const wrapper = input.closest('.c-custom-slot-check');
            if (!wrapper) {
                return;
            }

            wrapper.classList.toggle('is-hidden-by-limit', total >= starterLimit && !input.checked);
        });

        customSlotPreviews.forEach(function (preview) {
            const code = preview.getAttribute('data-slot-preview');
            const input = document.querySelector('.custom-slot-input[data-code="' + code + '"]');
            const isVisible = Boolean(input && input.checked && !input.closest('.c-custom-slot-check')?.classList.contains('is-hidden-by-limit'));
            preview.classList.toggle('is-active', isVisible);
            preview.classList.toggle('is-unused', !isVisible);
        });

        const balanceGroup = function (codes, singleLeft, leftA, leftB) {
            const activeCodes = codes.filter(function (code) {
                const input = document.querySelector('.custom-slot-input[data-code="' + code + '"]');
                return Boolean(input && input.checked);
            });

            codes.forEach(function (code, index) {
                const preview = document.querySelector('[data-slot-preview="' + code + '"]');
                if (!preview) {
                    return;
                }

                if (activeCodes.length === 1 && activeCodes[0] === code) {
                    preview.style.left = singleLeft + '%';
                    return;
                }

                preview.style.left = (index === 0 ? leftA : leftB) + '%';
            });
        };

        balanceGroup(['ZC1', 'ZC2'], 50, 38, 62);
        balanceGroup(['VOL1', 'VOL2'], 50, 42, 58);
        balanceGroup(['MAT1', 'MAT2'], 50, 38, 62);
        balanceGroup(['CA1', 'CA2'], 50, 38, 62);

        if (customSlotsCounter) {
            customSlotsCounter.textContent = total + '/' + starterLimit + ' no campo';
        }

        syncSetup();
    };

    startersInput.addEventListener('input', function () {
        syncCustomStarters();
    });
    startersInput.addEventListener('change', syncCustomStarters);
    reservesInput.addEventListener('input', function () {
        syncSetup();
    });
    customSlotInputs.forEach(function (input) {
        input.addEventListener('change', syncCustomStarters);
    });

    syncSetup();
    syncCustomStarters();
});
</script>

<style>
.c-team-config-card {
    display: grid;
    gap: 18px;
}

.c-team-config-main-grid {
    grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(150px, .55fr) minmax(150px, .55fr);
}

.c-team-config-count-grid {
    grid-template-columns: minmax(0, 1fr);
}

.c-team-config-details-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.c-team-config-section-head {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 14px;
}

.c-team-config-section-head h3,
.c-team-config-preview h4 {
    margin: 0;
}

.c-team-config-section-head p {
    margin: 8px 0 0;
}

.c-team-config-section-head span {
    flex: 0 0 auto;
    min-width: 92px;
    padding: 7px 10px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    border-radius: 7px;
    background: rgba(59, 130, 246, .12);
    color: #93c5fd;
    font-size: 12px;
    font-weight: 800;
    text-align: center;
}

.c-team-config-custom-card {
    margin: 0;
    padding: 14px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: rgba(255,255,255,.025);
}

.c-team-config-custom-layout {
    display: grid;
    grid-template-columns: minmax(260px, .92fr) minmax(260px, .66fr) minmax(150px, .42fr);
    gap: 18px;
    align-items: start;
}

.c-custom-slots-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 7px;
}

.c-custom-slot-group {
    display: grid;
    grid-template-columns: 96px minmax(0, 1fr);
    gap: 8px;
    align-items: stretch;
    min-height: 48px;
    padding: 7px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: rgba(255,255,255,.028);
}

.c-custom-slot-group > strong {
    display: flex;
    align-items: center;
    padding: 0 8px;
    border-right: 1px solid rgba(255,255,255,.1);
    font-size: 12px;
}

.c-custom-slot-group-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
}

.c-custom-slot-check {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-areas:
        "input code"
        "input label";
    gap: 1px 8px;
    align-items: center;
    min-height: 34px;
    padding: 5px 7px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: rgba(2,10,24,.2);
    cursor: pointer;
}

.c-custom-slot-check input {
    grid-area: input;
}

.c-custom-slot-check span {
    grid-area: code;
    font-weight: 700;
    line-height: 1.1;
}

.c-custom-slot-check small {
    grid-area: label;
    opacity: .72;
    font-size: 11px;
    line-height: 1.15;
}

.c-custom-slot-check.is-hidden-by-limit {
    display: none;
}

.c-team-config-preview {
    display: grid;
    gap: 12px;
    justify-items: center;
}

.c-team-config-preview h4 {
    width: min(100%, 260px);
    text-align: center;
}

.c-team-config-reserved {
    min-height: 100%;
    border: 1px solid rgba(255,255,255,.05);
    background: rgba(255,255,255,.012);
}

.c-config-field-preview {
    position: relative;
    overflow: hidden;
    width: min(100%, 260px);
    aspect-ratio: 3 / 4;
    min-height: 0;
    border: 1px solid rgba(255,255,255,.28);
    background:
        radial-gradient(circle at 50% 50%, rgba(255,255,255,.08) 0 1px, transparent 2px),
        linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px),
        linear-gradient(180deg, rgba(255,255,255,.05) 1px, transparent 1px),
        #145c38;
    background-size: 48px 48px;
}

.c-config-field-preview::before,
.c-config-field-preview::after {
    content: "";
    position: absolute;
    left: 50%;
    width: 42%;
    height: 22%;
    transform: translateX(-50%);
    border: 1px solid rgba(255,255,255,.52);
}

.c-config-field-preview::before {
    top: -1px;
}

.c-config-field-preview::after {
    bottom: -1px;
}

.c-config-field-line,
.c-config-field-circle,
.c-config-field-spot,
.c-config-field-box,
.c-config-field-goal {
    position: absolute;
    pointer-events: none;
}

.c-config-field-center {
    left: 0;
    right: 0;
    top: 50%;
    height: 1px;
    background: rgba(255,255,255,.42);
}

.c-config-field-circle {
    left: 50%;
    top: 50%;
    width: 24%;
    aspect-ratio: 1;
    border: 1px solid rgba(255,255,255,.45);
    border-radius: 50%;
    transform: translate(-50%, -50%);
}

.c-config-field-spot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,.68);
    transform: translate(-50%, -50%);
}

.c-config-field-spot-center {
    left: 50%;
    top: 50%;
}

.c-config-field-spot-top {
    left: 50%;
    top: 16%;
}

.c-config-field-spot-bottom {
    left: 50%;
    bottom: 16%;
}

.c-config-field-box {
    left: 50%;
    width: 18%;
    height: 8%;
    transform: translateX(-50%);
    border: 1px solid rgba(255,255,255,.5);
}

.c-config-field-box-top {
    top: -1px;
}

.c-config-field-box-bottom {
    bottom: -1px;
}

.c-config-field-goal {
    left: 50%;
    width: 14%;
    height: 4%;
    transform: translateX(-50%);
    border: 1px solid rgba(255,255,255,.5);
}

.c-config-field-goal-top {
    top: -1px;
}

.c-config-field-goal-bottom {
    bottom: -1px;
}

.c-config-slot-preview {
    position: absolute;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    transform: translate(-50%, -50%);
    border: 1px dashed rgba(255,255,255,.34);
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.11), rgba(2,10,24,.08) 62%);
    box-shadow: inset 0 0 0 1px rgba(2,10,24,.14);
    color: rgba(255,255,255,.58);
    font-size: 10px;
    font-weight: 700;
    transition: .16s ease;
}

.c-config-slot-preview.is-active {
    border-style: dashed;
    border-color: rgba(255,255,255,.34);
    background: radial-gradient(circle, rgba(255,255,255,.11), rgba(2,10,24,.08) 62%);
    color: rgba(255,255,255,.58);
}

.c-config-slot-preview.is-unused {
    display: none;
}

.c-team-config-actions {
    display: flex;
    justify-content: flex-start;
}

@media (max-width: 900px) {
    .c-team-config-main-grid,
    .c-team-config-count-grid,
    .c-team-config-details-grid,
    .c-team-config-custom-layout {
        grid-template-columns: 1fr;
    }

    .c-custom-slots-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .c-config-field-preview {
        width: min(100%, 320px);
        margin: 0 auto;
    }

    .c-team-config-reserved {
        display: none;
    }
}

@media (max-width: 560px) {
    .c-team-config-section-head {
        display: grid;
    }

    .c-team-config-custom-card {
        padding: 12px;
    }

.c-custom-slot-group {
        grid-template-columns: 1fr;
    }

    .c-custom-slot-group > strong {
        min-height: 26px;
        border-right: 0;
        border-bottom: 1px solid rgba(255,255,255,.1);
    }

    .c-team-config-preview {
        justify-items: center;
    }

    .c-config-field-preview {
        width: min(100%, calc(100vw - 84px), 320px);
    }
}
</style>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
