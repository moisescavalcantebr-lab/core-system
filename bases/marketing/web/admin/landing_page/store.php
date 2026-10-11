<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

landingPageRequireAdmin();
landingPageEnsureSchema($pdo);
csrf_verify();

$title = trim((string)($_POST['title'] ?? ''));
$slug = landingPageSlug((string)($_POST['slug'] ?? $title));
$templateKey = (string)($_POST['template_key'] ?? 'servico');
$template = landingPageTemplate($templateKey);
$headline = trim((string)($_POST['headline'] ?? $template['headline']));
$status = in_array((string)($_POST['status'] ?? 'draft'), ['draft', 'published'], true) ? (string)$_POST['status'] : 'draft';
$theme = in_array((string)($_POST['theme'] ?? 'dark'), ['dark', 'clean', 'contrast'], true) ? (string)$_POST['theme'] : 'dark';
$formLanguage = landingPageFormLanguage((string)($_POST['form_language'] ?? 'pt'));
$formTexts = landingPageFormTexts($formLanguage);
$actionType = landingPageActionType((string)($_POST['action_type'] ?? 'capture'));
$destinationUrl = landingPageExternalUrl((string)($_POST['destination_url'] ?? ''));
$offerImage = isset($_FILES['offer_image']) && is_array($_FILES['offer_image']) ? landingPageUploadOfferImage($_FILES['offer_image']) : '';
$offerImage2 = isset($_FILES['offer_image_2']) && is_array($_FILES['offer_image_2']) ? landingPageUploadOfferImage($_FILES['offer_image_2']) : '';

if ($title === '' || $headline === '') {
    flash('error', 'Informe titulo e headline.');
    redirect(PROJECT_URL . '/admin/landing_page/create.php');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO landing_page_pages
            (title, slug, template_key, theme, form_language, headline, subtitle, body, offer_image, offer_image_2, cta_text, whatsapp, action_type, destination_url, success_message, status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title,
        $slug,
        $templateKey,
        $theme,
        $formLanguage,
        $headline,
        trim((string)($_POST['subtitle'] ?? '')),
        trim((string)($_POST['body'] ?? '')),
        $offerImage,
        $offerImage2,
        trim((string)($_POST['cta_text'] ?? $template['cta'])),
        trim((string)($_POST['whatsapp'] ?? '')),
        $actionType,
        $destinationUrl,
        trim((string)($_POST['success_message'] ?? $formTexts['success'])),
        $status,
    ]);

    flash('success', 'Pagina criada.');
    redirect(PROJECT_URL . '/admin/landing_page/index.php');
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel criar. Verifique se o slug ja existe.');
    redirect(PROJECT_URL . '/admin/landing_page/create.php');
}
