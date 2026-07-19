<?php
$competition = $competition ?? [];
$formAction = $formAction ?? PROJECT_URL . '/admin/competicoes/store.php';
$submitLabel = $submitLabel ?? 'Salvar Competicao';

$context = (string)($competition['context'] ?? 'external');
$type = (string)($competition['type'] ?? 'tournament');
$status = (string)($competition['status'] ?? 'active');
$limitedEdit = !empty($hasRelatedData);

$externalTypes = [
    'championship' => 'Campeonato',
    'cup' => 'Copa',
    'league' => 'Liga',
    'tournament' => 'Torneio',
    'friendly' => 'Amistoso',
    'challenge' => 'Desafio',
    'ranking' => 'Ranking',
    'other' => 'Outro',
];

$allowedExternalTypes = projectPlanList('external_competition_types', array_keys($externalTypes));
$externalTypes = array_intersect_key($externalTypes, array_flip($allowedExternalTypes));

$internalTypes = [
    'training' => 'Treino',
];

if (!function_exists('projectModuleProvides') || !projectModuleProvides('internal_competitions')) {
    $internalTypes = [];
}

$hasInternalOption = !empty($internalTypes);

if (!$hasInternalOption && $context === 'internal') {
    $context = 'external';
}

if ($context === 'external' && !array_key_exists($type, $externalTypes)) {
    $type = array_key_first($externalTypes) ?: 'friendly';
}

$seasonTypes = ['championship', 'cup', 'league', 'tournament', 'challenge', 'ranking', 'other'];
?>

<form action="<?= htmlspecialchars($formAction) ?>" method="POST" class="c-card">
    <?= csrf_field(); ?>

    <div class="c-form-grid">
        <div class="c-form-group">
            <label>Nome</label>
            <input type="text" name="name" class="c-input" value="<?= htmlspecialchars((string)($competition['name'] ?? '')) ?>" required>
        </div>

        <?php if ($hasInternalOption): ?>
            <div class="c-form-group">
                <label>Contexto</label>
                <select name="context" class="c-input" id="competitionContext" <?= $limitedEdit ? 'disabled' : '' ?>>
                    <option value="external" <?= $context === 'external' ? 'selected' : '' ?>>Externa</option>
                    <option value="internal" <?= $context === 'internal' ? 'selected' : '' ?>>Interna</option>
                </select>
                <?php if ($limitedEdit): ?>
                    <input type="hidden" name="context" value="<?= htmlspecialchars($context) ?>">
                <?php endif; ?>
            </div>
        <?php else: ?>
            <input type="hidden" name="context" value="external">
        <?php endif; ?>

        <div class="c-form-group">
            <label>Tipo</label>
            <select name="type" class="c-input" id="competitionType" <?= $limitedEdit ? 'disabled' : '' ?>>
                <?php foreach ($externalTypes as $value => $label): ?>
                    <option
                        value="<?= htmlspecialchars($value) ?>"
                        data-context="external"
                        data-season="<?= in_array($value, $seasonTypes, true) ? '1' : '0' ?>"
                        <?= $context === 'external' && $type === $value ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
                <?php foreach ($internalTypes as $value => $label): ?>
                    <option
                        value="<?= htmlspecialchars($value) ?>"
                        data-context="internal"
                        data-season="0"
                        <?= $context === 'internal' && $type === $value ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($limitedEdit): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="c-form-grid">
        <div class="c-form-group" id="seasonGroup">
            <label>Temporada</label>
            <input type="text" name="season" class="c-input" value="<?= htmlspecialchars((string)($competition['season'] ?? '')) ?>">
        </div>

        <div class="c-form-group">
            <label>Início</label>
            <input type="date" name="starts_at" class="c-input" value="<?= htmlspecialchars((string)($competition['starts_at'] ?? '')) ?>" <?= $limitedEdit ? 'disabled' : '' ?>>
            <?php if ($limitedEdit): ?>
                <input type="hidden" name="starts_at" value="<?= htmlspecialchars((string)($competition['starts_at'] ?? '')) ?>">
            <?php endif; ?>
        </div>

        <div class="c-form-group">
            <label>Fim</label>
            <input type="date" name="ends_at" class="c-input" value="<?= htmlspecialchars((string)($competition['ends_at'] ?? '')) ?>" <?= $limitedEdit ? 'disabled' : '' ?>>
            <?php if ($limitedEdit): ?>
                <input type="hidden" name="ends_at" value="<?= htmlspecialchars((string)($competition['ends_at'] ?? '')) ?>">
            <?php endif; ?>
        </div>

        <div class="c-form-group">
            <label>Status</label>
            <select name="status" class="c-input" <?= $limitedEdit ? 'disabled' : '' ?>>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ativa</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                <option value="finished" <?= $status === 'finished' ? 'selected' : '' ?>>Finalizada</option>
                <option value="canceled" <?= $status === 'canceled' ? 'selected' : '' ?>>Cancelada</option>
            </select>
            <?php if ($limitedEdit): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="c-competition-context-note" id="externalNote">
        Competição externa direciona o fluxo para jogos do Meu Time contra outros times.
    </div>

    <div class="c-competition-context-note" id="internalNote" style="display:none;">
        Competição interna direciona o fluxo para disputas entre jogadores ou equipes internas.
    </div>

    <div class="c-form-group">
        <label>Observações</label>
        <textarea name="notes" class="c-input" rows="3" <?= $limitedEdit ? 'disabled' : '' ?>><?= htmlspecialchars((string)($competition['notes'] ?? '')) ?></textarea>
        <?php if ($limitedEdit): ?>
            <input type="hidden" name="notes" value="<?= htmlspecialchars((string)($competition['notes'] ?? '')) ?>">
        <?php endif; ?>
    </div>

    <button class="c-btn-secondary"><?= htmlspecialchars($submitLabel) ?></button>
</form>

<style>
.c-competition-context-note {
    margin: 0 0 14px;
    padding: 10px;
    border: 1px solid var(--border-color, rgba(255,255,255,.15));
    background: rgba(255,255,255,.03);
    font-size: 12px;
    opacity: .86;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const context = document.getElementById('competitionContext');
    const type = document.getElementById('competitionType');
    const externalNote = document.getElementById('externalNote');
    const internalNote = document.getElementById('internalNote');
    const seasonGroup = document.getElementById('seasonGroup');

    if (!externalNote || !internalNote) {
        return;
    }

    const syncNotes = function () {
        const contextValue = context ? context.value : 'external';

        externalNote.style.display = contextValue === 'external' ? '' : 'none';
        internalNote.style.display = contextValue === 'internal' ? '' : 'none';

        if (!type) {
            return;
        }

        let hasVisibleSelected = false;
        Array.from(type.options).forEach(function (option) {
            const visible = option.dataset.context === contextValue;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && option.selected) {
                hasVisibleSelected = true;
            }
        });

        if (!hasVisibleSelected) {
            const firstVisible = Array.from(type.options).find(function (option) {
                return !option.disabled;
            });

            if (firstVisible) {
                firstVisible.selected = true;
            }
        }

        if (seasonGroup) {
            const selectedOption = type.options[type.selectedIndex];
            seasonGroup.style.display = selectedOption && selectedOption.dataset.season === '1' ? '' : 'none';
        }
    };

    if (context) {
        context.addEventListener('change', syncNotes);
    }
    if (type) {
        type.addEventListener('change', syncNotes);
    }
    syncNotes();
});
</script>
