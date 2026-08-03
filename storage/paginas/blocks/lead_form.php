<?php

$showSuccess = !empty($config['success']) || (($_GET['lead'] ?? '') === 'success');

$title       = $config['title'] ?? 'Comece pelo e-mail';
$description = $config['description'] ?? 'Informe seu e-mail para receber o link de continuação.';
$align       = $config['align'] ?? 'center';
$baseId      = (int)($config['base_id'] ?? 0);
$baseSlug    = trim((string)($config['base_slug'] ?? ''));
$externalUrl = trim((string)($config['external_url'] ?? ''));
$pageSlug    = trim((string)($config['page_slug'] ?? ''));

?>

<section id="lead" class="block block-lead">

    <div class="c-container text-<?= htmlspecialchars($align) ?>">

        <?php if ($showSuccess): ?>
            <p class="form-success">
                Obrigado! Confira seu e-mail para continuar.
            </p>
        <?php endif; ?>

        <?php if ($title): ?>
            <h2 class="block-title">
                <?= htmlspecialchars($title) ?>
            </h2>
        <?php endif; ?>

        <?php if ($description): ?>
            <p class="block-description">
                <?= htmlspecialchars($description) ?>
            </p>
        <?php endif; ?>

        <form method="post" action="/web/lead_submit.php" class="lead-form-inner">

            <input type="hidden" name="_lead_form" value="1">
            <?php if ($baseId > 0): ?>
                <input type="hidden" name="base_id" value="<?= $baseId ?>">
            <?php elseif ($baseSlug !== ''): ?>
                <input type="hidden" name="base_slug" value="<?= htmlspecialchars($baseSlug) ?>">
            <?php endif; ?>
            <?php if ($externalUrl !== ''): ?>
                <input type="hidden" name="external_url" value="<?= htmlspecialchars($externalUrl) ?>">
            <?php endif; ?>
            <?php if ($pageSlug !== ''): ?>
                <input type="hidden" name="content_source" value="<?= htmlspecialchars($pageSlug) ?>">
            <?php endif; ?>

            <input type="email" name="email" placeholder="Seu e-mail" maxlength="150" autocomplete="email" required>

            <button class="btn btn-primary" type="submit">
                Receber link
            </button>

        </form>

    </div>

</section>
