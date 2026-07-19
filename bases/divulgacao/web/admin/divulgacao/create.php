<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/bootstrap/project_bootstrap.php';
require __DIR__ . '/helpers.php';

divulgacaoRequireAdmin();
divulgacaoEnsureSchema($pdo);

$title = 'Nova pagina';
$templates = divulgacaoTemplates();
$selectedTemplate = (string)($_GET['template'] ?? 'servico');
$template = divulgacaoTemplate($selectedTemplate);
$page = [
    'title' => '',
    'slug' => '',
    'template_key' => $selectedTemplate,
    'theme' => 'dark',
    'form_language' => 'pt',
    'headline' => $template['headline'],
    'subtitle' => $template['subtitle'],
    'body' => $template['body'],
    'offer_image' => '',
    'offer_image_2' => '',
    'cta_text' => $template['cta'],
    'whatsapp' => '',
    'action_type' => 'capture',
    'destination_url' => '',
    'success_message' => 'Recebido. Em breve entraremos em contato.',
    'status' => 'draft',
];
$action = PROJECT_URL . '/admin/divulgacao/store.php';

ob_start();
require __DIR__ . '/form.php';
$content = ob_get_clean();
require APP_PATH . '/views/layout_admin.php';
