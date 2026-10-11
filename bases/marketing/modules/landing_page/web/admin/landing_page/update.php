<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

landingPageRequireAdmin();
landingPageEnsureSchema($pdo);
csrf_verify();

$id = (int)($_GET['id'] ?? 0);
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
$currentStmt = $pdo->prepare('SELECT offer_image, offer_image_2, banners_json, lead_fields_json FROM landing_page_pages WHERE id = ? LIMIT 1');
$currentStmt->execute([$id]);
$currentPage = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
try {
    $leadFieldsJson = landingPageSaveLeadFields($_POST, $currentPage);
} catch (InvalidArgumentException $e) {
    flash('error', $e->getMessage());
    redirect(PROJECT_URL . '/admin/landing_page/edit.php?id=' . $id);
}
$offerImage = (string)($currentPage['offer_image'] ?? '');
$bannersJson = landingPageSaveBanners($currentPage, $_POST, $_FILES);
if (!empty($_POST['remove_offer_image'])) {
    $offerImage = '';
}
$uploadedImage = isset($_FILES['offer_image']) && is_array($_FILES['offer_image']) ? landingPageUploadOfferImage($_FILES['offer_image']) : '';
if ($uploadedImage !== '') {
    $offerImage = $uploadedImage;
}

if ($id <= 0 || $title === '' || $headline === '') {
    flash('error', 'Dados invalidos.');
    redirect(PROJECT_URL . '/admin/landing_page/index.php');
}

try {
    $stmt = $pdo->prepare("
        UPDATE landing_page_pages
        SET title = ?,
            slug = ?,
            template_key = ?,
            theme = ?,
            form_language = ?,
            headline = ?,
            subtitle = ?,
            body = ?,
            offer_image = ?,
            banners_json = ?,
            lead_fields_json = ?,
            cta_text = ?,
            whatsapp = ?,
            action_type = ?,
            destination_url = ?,
            success_message = ?,
            status = ?
        WHERE id = ?
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
        $bannersJson,
        $leadFieldsJson,
        trim((string)($_POST['cta_text'] ?? $template['cta'])),
        trim((string)($_POST['whatsapp'] ?? '')),
        $actionType,
        $destinationUrl,
        trim((string)($_POST['success_message'] ?? $formTexts['success'])),
        $status,
        $id,
    ]);

    flash('success', 'Pagina atualizada.');
    redirect(PROJECT_URL . '/admin/landing_page/edit.php?id=' . $id);
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel salvar. Verifique se o slug ja existe.');
    redirect(PROJECT_URL . '/admin/landing_page/edit.php?id=' . $id);
}
