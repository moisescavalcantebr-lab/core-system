<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

divulgacaoRequireAdmin();
divulgacaoEnsureSchema($pdo);
csrf_verify();

$title = trim((string)($_POST['title'] ?? ''));
$slug = divulgacaoSlug((string)($_POST['slug'] ?? $title));
$templateKey = (string)($_POST['template_key'] ?? 'servico');
$template = divulgacaoTemplate($templateKey);
$headline = trim((string)($_POST['headline'] ?? $template['headline']));
$status = in_array((string)($_POST['status'] ?? 'draft'), ['draft', 'published'], true) ? (string)$_POST['status'] : 'draft';
$theme = in_array((string)($_POST['theme'] ?? 'dark'), ['dark', 'clean', 'contrast'], true) ? (string)$_POST['theme'] : 'dark';
$formLanguage = divulgacaoFormLanguage((string)($_POST['form_language'] ?? 'pt'));
$formTexts = divulgacaoFormTexts($formLanguage);
$actionType = divulgacaoActionType((string)($_POST['action_type'] ?? 'capture'));
$destinationUrl = divulgacaoExternalUrl((string)($_POST['destination_url'] ?? ''));
$offerImage = isset($_FILES['offer_image']) && is_array($_FILES['offer_image']) ? divulgacaoUploadOfferImage($_FILES['offer_image']) : '';
$offerImage2 = isset($_FILES['offer_image_2']) && is_array($_FILES['offer_image_2']) ? divulgacaoUploadOfferImage($_FILES['offer_image_2']) : '';

if ($title === '' || $headline === '') {
    flash('error', 'Informe titulo e headline.');
    redirect(PROJECT_URL . '/admin/divulgacao/create.php');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO divulgacao_pages
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
    redirect(PROJECT_URL . '/admin/divulgacao/index.php');
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel criar. Verifique se o slug ja existe.');
    redirect(PROJECT_URL . '/admin/divulgacao/create.php');
}
