<?php

$title    = $config['title'] ?? '';
$subtitle = $config['subtitle'] ?? '';
$ctaText  = $config['cta_text'] ?? '';
$ctaUrl = trim((string)($config['cta_url'] ?? ''));
$ctaEnabled = (string)($config['cta_enabled'] ?? '0') === '1';
$ctaTarget = (string)($config['cta_target'] ?? '_self');
$ctaTarget = in_array($ctaTarget, ['_self', '_blank'], true) ? $ctaTarget : '_self';

if ($title !== '' || $subtitle !== ''):
?>

<section class="<?= $blockClass ?>">

    <div class="container">
        <div class="block-inner">

            <?php if ($title): ?>
                <h1 class="hero-title">
                    <?= htmlspecialchars($title) ?>
                </h1>
            <?php endif; ?>

            <?php if ($subtitle): ?>
                <p class="hero-subtitle">
                    <?= htmlspecialchars($subtitle) ?>
                </p>
            <?php endif; ?>

            <?php if ($ctaEnabled && $ctaText && $ctaUrl !== ''): ?>
                <div class="hero-cta">
                    <a href="<?= htmlspecialchars($ctaUrl) ?>" class="btn btn-primary" target="<?= htmlspecialchars($ctaTarget) ?>">
                        <?= htmlspecialchars($ctaText) ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

</section>

<?php endif; ?>
