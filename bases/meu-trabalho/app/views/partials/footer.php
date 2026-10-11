<?php
$footerPlanName = trim((string)($project['plan_name'] ?? 'Plano Gratis'));
$footerPlanKey = strtolower($footerPlanName);
$footerPlanLabel = 'Gratis';

if (str_contains($footerPlanKey, 'plus')) {
    $footerPlanLabel = 'Plus';
} elseif (str_contains($footerPlanKey, 'start')) {
    $footerPlanLabel = 'Start';
}
?>

<footer class="c-footer">
    <div class="c-footer-inner">

        <div class="c-footer-brand">
            <span>
                © <?= date('Y') ?> <?= htmlspecialchars(getSetting('site_name', 'Projeto')) ?>
            </span>
        </div>

        <span class="c-footer-plan c-footer-plan--<?= htmlspecialchars(strtolower($footerPlanLabel)) ?>">
            <?= htmlspecialchars($footerPlanLabel) ?>
        </span>

    </div>
</footer>
